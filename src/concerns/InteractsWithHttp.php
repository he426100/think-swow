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

        $server = new Server($this->getContainer());
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
        Coroutine::create(function () use ($req, $con) {
            $this->runInSandbox(function (Http $http, Event $event, SwowApp $app) use ($req, $con) {
                $app->setInConsole(false);

                $request = $this->prepareRequest($req);

                try {
                    $response = $this->handleRequest($http, $request);
                } catch (Throwable $e) {
                    $response = $this->app
                        ->make(Handle::class)
                        ->render($request, $e);
                }

                $res = new Psr7Response();
                // $this->setCookie($res, $app->cookie);
                $this->sendResponse($con, $res, $request, $response);
            });
        });
    }

    protected function handleRequest(Http $http, $request)
    {
        $level = ob_get_level();
        ob_start();

        $response = $http->run($request);

        $content = $response->getContent();

        if (ob_get_level() == 0) {
            ob_start();
        }

        $http->end($response);

        if (ob_get_length() > 0) {
            $response->content(ob_get_contents() . $content);
        }

        while (ob_get_level() > $level) {
            ob_end_clean();
        }

        return $response;
    }

    protected function prepareRequest(ServerRequest $req)
    {
        $header = $req->getHeaders();
        $server = $req->getServerParams();

        foreach ($header as $key => $values) {
            $server['http_' . str_replace('-', '_', $key)] = implode(", ", $values);
        }

        // 重新实例化请求对象 处理swow请求数据
        /** @var \think\Request $request */
        $request = $this->app->make('request', [], true);

        return $request
            ->withHeader($header)
            ->withServer($server)
            ->withGet($req->getQueryParams())
            ->withPost($req->getParsedBody())
            ->withCookie($req->getCookieParams())
            ->withFiles($this->getFiles($req))
            ->withInput($req->getBody())
            ->setBaseUrl($req->getUri()->getHost())
            ->setUrl($req->getUri()->getPath() . $req->getUri()->getQuery())
            ->setPathinfo(ltrim($req->getUri()->getPath(), '/'));
    }

    protected function getFiles(ServerRequest $req)
    {
        if (empty($req->getUploadedFiles())) {
            return [];
        }

        return array_map(function ($file) {
            if (!Arr::isAssoc($file)) {
                $files = [];
                foreach ($file as $f) {
                    $files['name'][]     = $f['name'];
                    $files['type'][]     = $f['type'];
                    $files['tmp_name'][] = $f['tmp_name'];
                    $files['error'][]    = $f['error'];
                    $files['size'][]     = $f['size'];
                }
                return $files;
            }
            return $file;
        }, $req->getUploadedFiles());
    }

    protected function setCookie(Psr7Response $res, Cookie $cookie)
    {
        throw new \Exception('暂不支持');
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
        throw new \Exception('暂不支持');
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
