<?php

namespace think\swow\websocket\socketio;

use Exception;
use Swow\Psr7\Server\ServerConnection;
use Swow\Psr7\Message\WebSocketFrame;
use Swow\Psr7\Psr7;
use think\Config;
use think\Event;
use think\Request;
use think\swow\coroutine\Timer;
use think\swow\contract\websocket\HandlerInterface;
use think\swow\websocket\Event as WsEvent;

class Handler implements HandlerInterface
{
    /** @var Config */
    protected $config;

    protected $event;

    /**
     * @var ServerConnection
     */
    protected $websocket;

    protected $eio;

    protected $pingTimeoutTimer  = 0;
    protected $pingIntervalTimer = 0;

    protected $pingInterval;
    protected $pingTimeout;

    public function __construct(Event $event, Config $config)
    {
        $this->event        = $event;
        $this->config       = $config;
        $this->pingInterval = $this->config->get('swow.websocket.ping_interval', 25000);
        $this->pingTimeout  = $this->config->get('swow.websocket.ping_timeout', 60000);
    }

    /**
     * "onOpen" listener.
     *
     * @param Request $request
     */
    public function onOpen(ServerConnection $connection, Request $request)
    {
        $this->websocket = $connection;
        $this->eio = $request->param('EIO');

        $payload = json_encode(
            [
                'sid'          => base64_encode(uniqid()),
                'upgrades'     => [],
                'pingInterval' => $this->pingInterval,
                'pingTimeout'  => $this->pingTimeout,
            ]
        );

        $this->push(EnginePacket::open($payload));

        $this->event->trigger('swow.websocket.Open', $request);

        if ($this->eio < 4) {
            $this->resetPingTimeout($this->pingInterval + $this->pingTimeout);
            $this->onConnect();
        } else {
            $this->schedulePing();
        }
    }

    /**
     * "onMessage" listener.
     *
     * @param WebSocketFrame $frame
     */
    public function onMessage(ServerConnection $connection, WebSocketFrame $frame)
    {
        $enginePacket = EnginePacket::fromString($frame->getPayloadData());

        $this->event->trigger('swow.websocket.Message', $enginePacket);

        $this->resetPingTimeout($this->pingInterval + $this->pingTimeout);

        switch ($enginePacket->type) {
            case EnginePacket::MESSAGE:
                $packet = Packet::fromString($enginePacket->data);
                switch ($packet->type) {
                    case Packet::CONNECT:
                        $this->onConnect($packet->data);
                        break;
                    case Packet::EVENT:
                        $type   = array_shift($packet->data);
                        $data   = $packet->data;
                        $result = $this->event->trigger('swow.websocket.Event', new WsEvent($type, $data));

                        if ($packet->id !== null) {
                            $responsePacket = Packet::create(Packet::ACK, [
                                'id'   => $packet->id,
                                'nsp'  => $packet->nsp,
                                'data' => $result,
                            ]);

                            $this->push($responsePacket);
                        }
                        break;
                    case Packet::DISCONNECT:
                        $this->event->trigger('swow.websocket.Disconnect');
                        $this->websocket->close();
                        break;
                    default:
                        $this->websocket->close();
                        break;
                }
                break;
            case EnginePacket::PING:
                $this->push(EnginePacket::pong($enginePacket->data));
                break;
            case EnginePacket::PONG:
                $this->schedulePing();
                break;
            default:
                $this->websocket->close();
                break;
        }
    }

    /**
     * "onClose" listener.
     */
    public function onClose(ServerConnection $connection)
    {
        Timer::deleteTimer($this->pingTimeoutTimer);
        Timer::deleteTimer($this->pingIntervalTimer);
        $this->event->trigger('swow.websocket.Close');
    }

    protected function onConnect($data = null)
    {
        try {
            $this->event->trigger('swow.websocket.Connect', $data);
            $packet = Packet::create(Packet::CONNECT);
            if ($this->eio >= 4) {
                $packet->data = ['sid' => base64_encode(uniqid())];
            }
        } catch (Exception $exception) {
            $packet = Packet::create(Packet::CONNECT_ERROR, [
                'data' => ['message' => $exception->getMessage()],
            ]);
        }

        $this->push($packet);
    }

    protected function resetPingTimeout($timeout)
    {
        Timer::deleteTimer($this->pingTimeoutTimer);
        $this->pingTimeoutTimer = Timer::delay($timeout, function () {
            $this->websocket->close();
        });
    }

    protected function schedulePing()
    {
        Timer::deleteTimer($this->pingIntervalTimer);
        $this->pingIntervalTimer = Timer::delay($this->pingInterval, function () {
            $this->push(EnginePacket::ping());
            $this->resetPingTimeout($this->pingTimeout);
        });
    }

    public function encodeMessage($message)
    {
        if ($message instanceof WsEvent) {
            $message = Packet::create(Packet::EVENT, [
                'data' => array_merge([$message->type], $message->data),
            ]);
        }

        if ($message instanceof Packet) {
            $message = EnginePacket::message($message->toString());
        }

        if ($message instanceof EnginePacket) {
            $message = $message->toString();
        }

        return $message;
    }

    protected function push($data)
    {
        $this->websocket->send(Psr7::createWebSocketTextFrame($this->encodeMessage($data)));
    }
}
