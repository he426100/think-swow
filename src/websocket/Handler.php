<?php

namespace think\swow\websocket;

use Swow\Psr7\Message\WebSocketFrame;
use Swow\Psr7\Server\ServerConnection;
use think\Event;
use think\Request;
use think\swow\contract\websocket\HandlerInterface;

class Handler implements HandlerInterface
{
    protected $event;

    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    /**
     * "onOpen" listener.
     * @param ServerConnection $connection
     * @param Request $request
     */
    public function onOpen(ServerConnection $connection, Request $request)
    {
        $this->event->trigger('swow.websocket.Open', $request);
    }

    /**
     * "onMessage" listener.
     * @param ServerConnection $connection
     * @param WebSocketFrame $frame
     */
    public function onMessage(ServerConnection $connection, WebSocketFrame $frame)
    {
        $this->event->trigger('swow.websocket.Message', $frame);
    }

    /**
     * "onClose" listener.
     * @param ServerConnection $connection
     */
    public function onClose(ServerConnection $connection)
    {
        $this->event->trigger('swow.websocket.Close', $connection);
    }
}
