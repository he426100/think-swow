<?php
// 参考了hyperf
namespace think\swow\concerns;

use Swow\Socket;
use Swow\Http\Status;
use Swow\Psr7\Psr7;
use Swow\Psr7\Server\ServerConnection;
use Swow\Psr7\Message\ServerRequest;
use Swow\Psr7\Message\Response as Psr7Response;
use Swow\Psr7\Message\ResponsePlusInterface;
use Swow\Psr7\Message\UploadedFile;
use think\App;
use think\Cookie;
use think\Event;
use think\exception\Handle;
use think\helper\Arr;
use think\helper\Str;
use think\Http;
use think\swow\Coroutine;
use think\swow\server\http\Server;
use think\swow\App as SwowApp;
use think\swow\Http as SwowHttp;
use think\swow\response\File as FileResponse;
use think\swow\Cookie as SwowCookie;
use Throwable;
use function substr;

/**
 * Trait InteractsWithHttp
 * @package think\swow\concerns
 * @property App $app
 * @property App $container
 */
trait InteractsWithHttp
{
    use InteractsWithWebsocket, ModifyProperty;

    public function createHttpServer()
    {
        $this->preloadHttp();

        $host    = $this->getConfig('http.host');
        $port    = $this->getConfig('http.port');
        $options = $this->getConfig('http.options', []);

        $server = new Server($this->getContainer());
        foreach ($options as $key => $value) {
            $method = Str::camel(sprintf('set_%s', $key));
            if (method_exists($server, $method)) {
                $server->{$method}($value);
            }
        }
        $server->bind($host, $port, Socket::BIND_FLAG_REUSEPORT);

        $server->handle(function (ServerRequest $request, ServerConnection $connection) {
            if ($this->wsEnable && $this->isWebsocketRequest($request)) {
                $this->onHandShake($request, $connection);
            } else {
                $this->onRequest($request, $connection);
            }
        });

        $server->start();
    }

    protected function preloadHttp()
    {
        $http = $this->app->http;
        $this->app->invokeMethod([$http, 'loadMiddleware'], [], true);

        if ($this->app->config->get('app.with_route', true)) {
            $this->app->invokeMethod([$http, 'loadRoutes'], [], true);
            $route = clone $this->app->route;
            $this->modifyProperty($route, null);
            unset($this->app->route);

            $this->app->resolving(SwowHttp::class, function ($http, App $app) use ($route) {
                $newRoute = clone $route;
                $this->modifyProperty($newRoute, $app);
                $app->instance('route', $newRoute);
            });
        }

        $middleware = clone $this->app->middleware;
        $this->modifyProperty($middleware, null);
        unset($this->app->middleware);

        $this->app->resolving(SwowHttp::class, function ($http, App $app) use ($middleware) {
            $newMiddleware = clone $middleware;
            $this->modifyProperty($newMiddleware, $app);
            $app->instance('middleware', $newMiddleware);
        });

        unset($this->app->http);
        $this->app->bind(Http::class, SwowHttp::class);
    }

    protected function isWebsocketRequest(ServerRequest $req)
    {
        $header = $req->getHeaders();
        return strcasecmp(implode(", ", Arr::get($header, 'Connection', [])), 'upgrade') === 0 &&
            strcasecmp(implode(", ", Arr::get($header, 'Upgrade', [])), 'websocket') === 0;
    }

    protected function prepareHttp()
    {
        if ($this->getConfig('http.enable', true)) {
            $this->wsEnable = $this->getConfig('websocket.enable', false);

            if ($this->wsEnable) {
                $this->prepareWebsocket();
            }

            $this->addWorker([$this, 'createHttpServer'], 'http server');
        }
    }

    /**
     * "onRequest" listener.
     *
     * @param ServerRequest $req
     * @param ServerConnection $res
     */
    public function onRequest($req, $con)
    {
        $this->runWithBarrier([$this, 'runInSandbox'], function (Http $http, Event $event, SwowApp $app) use ($req, $con) {
            $app->setInConsole(false);

            $request = $this->prepareRequest($app, $req);

            try {
                $response = $this->handleRequest($http, $request);
            } catch (Throwable $e) {
                $response = $app
                    ->make(Handle::class)
                    ->render($request, $e);
            }

            $res = new Psr7Response();
            $this->setCookie($res, $app->cookie);
            $this->sendResponse($con, $res, $request, $response);
        });
    }

    protected function handleRequest(Http $http, $request)
    {
        $response = $http->run($request);

        $http->end($response);

        return $response;
    }

    protected function prepareRequest(SwowApp $app, ServerRequest $req)
    {
        $header = $req->getHeaders();
        $server = $req->getServerParams();

        foreach ($header as $key => $values) {
            $server['http_' . str_replace('-', '_', $key)] = implode(", ", $values);
        }

        // 重新实例化请求对象 处理swow请求数据
        /** @var \think\Request $request */
        $request = $app->make('request', [], true);

        return $request
            ->withHeader($header)
            ->withServer($server)
            ->withGet($req->getQueryParams())
            ->withPost($req->getParsedBody())
            ->withCookie($req->getCookieParams())
            ->withFiles($this->getFiles($req))
            ->withInput($req->getBody())
            ->setMethod($req->getMethod())
            ->setBaseUrl($req->getUri()->getHost())
            ->setUrl($req->getUri()->getPath() . (!empty($req->getUri()->getQuery()) ? '?' . $req->getUri()->getQuery() : ''))
            ->setPathinfo(ltrim($req->getUri()->getPath(), '/'));
    }

    protected function getFiles(ServerRequest $req)
    {
        if (empty($req->getUploadedFiles())) {
            return [];
        }

        return array_map(function (UploadedFile $file) {
            $tmp = $file->toArray();
            return [
                ...$tmp,
                'tmp_name' => $tmp['tmp_file']
            ];
        }, $req->getUploadedFiles());
    }

    protected function setCookie(Psr7Response $res, Cookie $cookie)
    {
        $res->setHeader('Set-Cookie', SwowCookie::toArray($cookie));
    }

    protected function setHeader(Psr7Response $res, array $headers)
    {
        foreach ($headers as $key => $val) {
            $res->setHeader($key, $val);
        }
    }

    protected function setStatus(Psr7Response $res, $code)
    {
        $res->setStatus($code, Status::getReasonPhraseOf($code));
    }

    protected function sendResponse(ServerConnection $con, Psr7Response $res, \think\Request $request, \think\Response $response)
    {
        switch (true) {
            case $response instanceof FileResponse:
                $this->sendFile($con, $res, $request, $response);
                break;
            default:
                $this->sendContent($con, $res, $response);
        }
    }

    protected function sendFile(ServerConnection $con, Psr7Response $res, \think\Request $request, FileResponse $response)
    {
        $ifNoneMatch = $request->header('If-None-Match');
        $ifRange     = $request->header('If-Range');

        $code         = $response->getCode();
        $file         = $response->getFile();
        $eTag         = $response->getHeader('ETag');
        $lastModified = $response->getHeader('Last-Modified');

        $fileSize = $file->getSize();
        $offset   = 0;
        $length   = -1;

        if ($ifNoneMatch == $eTag) {
            $code = 304;
        } elseif (!$ifRange || $ifRange === $eTag || $ifRange === $lastModified) {
            $range = $request->header('Range', '');
            if (Str::startsWith($range, 'bytes=')) {
                [$start, $end] = explode('-', substr($range, 6), 2) + [0];

                $end = ('' === $end) ? $fileSize - 1 : (int) $end;

                if ('' === $start) {
                    $start = $fileSize - $end;
                    $end   = $fileSize - 1;
                } else {
                    $start = (int) $start;
                }

                if ($start <= $end) {
                    $end = min($end, $fileSize - 1);
                    if ($start < 0 || $start > $end) {
                        $code = 416;
                        $response->header([
                            'Content-Range' => sprintf('bytes */%s', $fileSize),
                        ]);
                    } elseif ($end - $start < $fileSize - 1) {
                        $length = $end < $fileSize ? $end - $start + 1 : -1;
                        $offset = $start;
                        $code   = 206;
                        $response->header([
                            'Content-Range'  => sprintf('bytes %s-%s/%s', $start, $end, $fileSize),
                            'Content-Length' => $end - $start + 1,
                        ]);
                    }
                }
            }
        }

        $this->setStatus($res, $code);
        $this->setHeader($res, $response->getHeader());

        if ($code >= 200 && $code < 300 && $length !== 0) {
            $con->sendHttpResponse($res);
            $con->send(file_get_contents($file->getPathname()), $offset, $length);
        }
    }

    protected function sendContent(ServerConnection $con, Psr7Response $res, \think\Response $response)
    {
        $this->setStatus($res, $response->getCode());
        $this->setHeader($res, $response->getHeader());
        $res->setBody($response->getContent());

        try {
            if ($con->getProtocolType() === ServerConnection::PROTOCOL_TYPE_WEBSOCKET) {
                return;
            }

            $headers = $res->getHeaders();
            if ($res instanceof ResponsePlusInterface) {
                $headers = $res->getStandardHeaders();
            } else {
                $headers['Connection'] = $con->shouldKeepAlive() ? 'keep-alive' : 'closed';
                if (! $res->hasHeader('Content-Length')) {
                    $body = (string) $res->getBody();
                    $headers['Content-Length'] = strlen($body);
                }
            }

            $res = Psr7::setHeaders($res, $headers);
            
            $con->sendHttpResponse($res);
        } catch (Throwable $e) {
            $this->logServerError($e);
        }
    }
}
