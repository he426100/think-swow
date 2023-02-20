<?php

namespace think\swow\contract\websocket;

use Swow\Psr7\Message\WebSocketFrame;
use think\Request;

interface HandlerInterface
{
    /**
     * "onOpen" listener.
     *
     * @param Request $request
     */
    public function onOpen(Request $request);

    /**
     * "onMessage" listener.
     *
     * @param WebSocketFrame $frame
     */
    public function onMessage(WebSocketFrame $frame);

    /**
     * "onClose" listener.
     */
    public function onClose();

    public function encodeMessage($message);

}
