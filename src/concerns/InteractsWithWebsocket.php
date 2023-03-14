<?php
// @link https://github.com/swow/swow/blob/develop/examples/http_server/mixed.php
// @link https://github.com/hyperf/engine-swow/blob/master/src/WebSocket/WebSocket.php
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
use think\swow\contract\websocket\RoomInterface;
use think\swow\Middleware;
use think\swow\Websocket;
use think\swow\websocket\message\PushMessage;
use think\swow\websocket\Room;
use think\swow\App as SwowApp;
use think\swow\Coroutine;
use think\swow\Channel;
use Throwable;

/**
 * Trait InteractsWithWebsocket
 * @package think\swow\concerns
 *
 * @property App $app
 * @property App $container
 */
trait InteractsWithWebsocket
{
    /**
     * @var RoomInterface
     */
    protected $wsRoom;

    /**
     * @var Channel[]
     */
    protected $wsMessageChannel = [];

    protected $wsEnable = false;

    /**
     * "onHandShake" listener.
     *
     * @param ServerRequest $req
     * @param ServerConnection $res
     */
    public function onHandShake($req, $con)
    {
        $this->runInSandbox(function (SwowApp $app, Websocket $websocket, HandlerInterface $handler) use ($req, $con) {
            $con = $this->upgradeToWebSocket($con, $req);

            $websocket->setClient($con);

            $fd = $con->getFd();

            $this->wsMessageChannel[$fd] = $wsMessageChannel = new Channel();

            Coroutine::create(function () use ($websocket, $con, $wsMessageChannel) {
                //推送消息
                while ($message = $wsMessageChannel->pop()) {
                    try {
                        $con->sendWebSocketFrame(Psr7::createWebSocketTextFrame($message));
                    } catch (Throwable $e) {
                        $this->logServerError($e);
                    }
                    $websocket->setConnected($con->isAvailable());
                }
            });

            try {
                $pid = posix_getpid();
                $id = "{$pid}.{$fd}";

                $websocket->setSender($id);
                $websocket->join($id);

                $this->runWithBarrier(function () use ($req, $app, $handler) {
                    $request = $this->prepareRequest($app, $req);
                    try {
                        $request = $this->setRequestThroughMiddleware($app, $request);
                        $handler->onOpen($request);
                    } catch (Throwable $e) {
                        $this->logServerError($e);
                    }
                });

                $this->runWithBarrier(function () use ($handler, $con) {
                    while (true) {
                        try {
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
                                    $handler->onClose();
                                    break 2;
                                default:
                                    $handler->onMessage($frame);
                            }
                        } catch (Throwable $e) {
                            $this->logServerError($e);
                        }  
                    }
                });
            } finally {
                // leave all rooms
                $websocket->leave();
                if (isset($this->wsMessageChannel[$fd])) {
                    $this->wsMessageChannel[$fd]->close();
                    unset($this->wsMessageChannel[$fd]);
                }
                $websocket->setConnected(false);
            }
        });
    }

    protected function upgradeToWebSocket(ServerConnection $connection, RequestPlusInterface $request)
    {
        $upgradeType = Psr7::detectUpgradeType($request);

        if (($upgradeType & UpgradeType::UPGRADE_TYPE_WEBSOCKET) === 0) {
            throw new HttpProtocolException(Status::BAD_REQUEST, 'Unsupported Upgrade Type');
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
        $this->prepareWebsocketRoom();

        $this->onEvent('message', function ($message) {
            if ($message instanceof PushMessage) {
                if (isset($this->wsMessageChannel[$message->fd])) {
                    $this->wsMessageChannel[$message->fd]->push($message->data);
                }
            }
        });

        $this->onEvent('workerStart', function () {
            $this->bindWebsocketRoom();
            $this->bindWebsocketHandler();
            $this->prepareWebsocketListener();
        });
    }

    /**
     * Prepare websocket room.
     */
    protected function prepareWebsocketRoom()
    {
        $this->wsRoom = $this->container->make(Room::class);
        $this->wsRoom->prepare();
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

    /**
     * Bind room instance to app container.
     */
    protected function bindWebsocketRoom(): void
    {
        $this->app->instance(Room::class, $this->wsRoom);
    }
}
