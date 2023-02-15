<?php
// @link https://github.com/swow/swow/blob/develop/examples/http_server/mixed.php
// @link https://github.com/hyperf/engine-swow/blob/master/src/Http/Server.php

namespace think\swow\server\http;

use think\App;
use think\swow\Coroutine;
use Swow\CoroutineException;
use Swow\Errno;
use Swow\Http\Protocol\ProtocolException as HttpProtocolException;
use Swow\Psr7\Psr7;
use Swow\Psr7\Server\Server as HttpServer;
use Swow\Socket;
use Swow\SocketException;
use Throwable;

class Server extends HttpServer
{
    public ?string $host = null;

    public ?int $port = null;
    
    /**
     * @var App
     */
    protected $container;

    /**
     * @var callable
     */
    protected $handler;

    /**
     * Manager constructor.
     * @param App $container
     * @param integer $type
     */
    public function __construct(App $container, int $type = self::TYPE_TCP)
    {
        $this->container = $container;
        parent::__construct($type);
    }

    public function bind(string $name, int $port = 0, int $flags = Socket::BIND_FLAG_NONE): static
    {
        $this->host = $name;
        $this->port = $port;
        parent::bind($name, $port, $flags);
        return $this;
    }

    public function handle(callable $callable): static
    {
        $this->handler = $callable;
        return $this;
    }

    public function start(): void
    {
        $this->listen();
        while (true) {
            try {
                $connection = $this->acceptConnection();
                Coroutine::create(function () use ($connection) {
                    try {
                        while (true) {
                            $request = null;
                            try {
                                $request = $connection->recvHttpRequest();
                                $handler = $this->handler;
                                $handler($request, $connection);
                            } catch (HttpProtocolException $exception) {
                                $connection->error($exception->getCode(), $exception->getMessage());
                            }
                            if (! $request || ! Psr7::detectShouldKeepAlive($request)) {
                                break;
                            }
                        }
                    } catch (Throwable $exception) {
                        $this->container->log?->critical((string) $exception);
                    } finally {
                        $connection->close();
                    }
                });
            } catch (SocketException|CoroutineException $exception) {
                if (in_array($exception->getCode(), [Errno::EMFILE, Errno::ENFILE, Errno::ENOMEM], true)) {
                    $this->container->log?->warning('Socket resources have been exhausted.');
                    sleep(1);
                } elseif ($exception->getCode() === Errno::ECANCELED) {
                    $this->container->log?->info('Socket accept has been canceled.');
                    break;
                } else {
                    $this->container->log?->error((string) $exception);
                    break;
                }
            } catch (Throwable $exception) {
                $this->container->log?->error((string) $exception);
            }
        }
    }
}
