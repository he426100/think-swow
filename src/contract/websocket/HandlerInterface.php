<?php

namespace think\swow\contract\websocket;

use Swow\Psr7\Message\WebSocketFrame;
use Swow\Psr7\Server\ServerConnection;
use think\Request;

interface HandlerInterface
{
    /**
     * "onOpen" listener.
     * @param ServerConnection $connection
     * @param Request $request
     * 
     */
    public function onOpen(ServerConnection $connection, Request $request);

    /**
     * "onMessage" listener.
     * @param ServerConnection $connection
     * @param WebSocketFrame $frame
     */
    public function onMessage(ServerConnection $connection, WebSocketFrame $frame);

    /**
     * "onClose" listener.
     * @param ServerConnection $connection
     */
    public function onClose(ServerConnection $connection);
}
