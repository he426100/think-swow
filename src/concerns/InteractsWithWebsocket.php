<?php

namespace think\swow\concerns;

use Swow\Psr7\Server\ServerConnection;
use Swow\Psr7\Message\ServerRequest;
use Swow\Http\Protocol\ProtocolException as HttpProtocolException;
use Swow\Http\Status;
use Swow\Psr7\Message\RequestPlusInterface;
use Swow\Psr7\Message\UpgradeType;
use Swow\Psr7\Psr7;
use Swow\WebSocket\Opcode;
use Swow\WebSocket\WebSocket as SwowWebSocket;
use Swow\Psr7\Message\WebSocketFrame;
use think\helper\Str;
use think\swow\contract\websocket\HandlerInterface;
use think\swow\Middleware;
use think\swow\App as SwowApp;

/**
 * Trait InteractsWithWebsocket
 * @package think\swow\concerns
 *
 * @property App $app
 * @property App $container
 */
trait InteractsWithWebsocket
{
    protected $wsEnable = false;

    /**
     * "onHandShake" listener.
     *
     * @param ServerRequest $req
     * @param ServerConnection $res
     */
    public function onHandShake($req, $con)
    {
        $this->runInSandbox(function (SwowApp $app, HandlerInterface $handler) use ($req, $con) {
            $con = $this->upgradeToWebSocket($con, $req);

            $request = $this->prepareRequest($app, $req);
            $request = $this->setRequestThroughMiddleware($app, $request);

            $this->runWithBarrier(function () use ($handler, $request, $con) {
                $handler->onOpen($con, $request);

                while (true) {
                    /**
                     * @var WebSocketFrame
                     */
                    $frame = $con->recvWebSocketFrame();
                    $opcode = $frame->getOpcode();
                    switch ($opcode) {
                        case Opcode::PING:
                            $con->send(SwowWebSocket::PONG_FRAME);
                            break;
                        case Opcode::PONG:
                            break;
                        case Opcode::CLOSE:
                            $handler->onClose($con);
                            break 2;
                        default:
                            $handler->onMessage($con, $frame);
                    }
                }
            });
        });
    }

    protected function upgradeToWebSocket(ServerConnection $connection, RequestPlusInterface $request)
    {
        $upgradeType = Psr7::detectUpgradeType($request);

        if (($upgradeType & UpgradeType::UPGRADE_TYPE_WEBSOCKET) === 0) {
            $this->throwBadRequestException();
        }

        return $connection->upgradeToWebSocket($request);
    }

    /**
     * @param SwowApp $app
     * @param \think\Request $request
     * @return \think\Request
     */
    protected function setRequestThroughMiddleware(SwowApp $app, \think\Request $request)
    {
        $app->instance('request', $request);
        return Middleware::make($app, $this->getConfig('websocket.middleware', []))
            ->pipeline()
            ->send($request)
            ->then(function ($request) {
                return $request;
            });
    }

    /**
     * Prepare settings if websocket is enabled.
     */
    protected function prepareWebsocket()
    {
        $this->onEvent('workerStart', function () {
            $this->bindWebsocketHandler();
            $this->prepareWebsocketListener();
        });
    }

    protected function prepareWebsocketListener()
    {
        $listeners = $this->getConfig('websocket.listen', []);

        foreach ($listeners as $event => $listener) {
            $this->app->event->listen('swow.websocket.' . Str::studly($event), $listener);
        }

        $subscribers = $this->getConfig('websocket.subscribe', []);

        foreach ($subscribers as $subscriber) {
            $this->app->event->observe($subscriber, 'swow.websocket.');
        }
    }

    /**
     * Prepare websocket handler for onOpen and onClose callback
     */
    protected function bindWebsocketHandler()
    {
        $handlerClass = $this->getConfig('websocket.handler');
        $this->app->bind(HandlerInterface::class, $handlerClass);
    }

    private function throwBadRequestException()
    {
        throw new HttpProtocolException(Status::BAD_REQUEST, 'Unsupported Upgrade Type');
    }
}