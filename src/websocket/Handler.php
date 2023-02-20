<?php

namespace think\swow\websocket;

use Swow\Psr7\Message\WebSocketFrame;
use think\Event;
use think\Request;
use think\swow\contract\websocket\HandlerInterface;
use think\swow\websocket\Event as WsEvent;

class Handler implements HandlerInterface
{
    protected $event;

    public function __construct(Event $event)
    {
        $this->event = $event;
    }

    /**
     * "onOpen" listener.
     * @param Request $request
     */
    public function onOpen(Request $request)
    {
        $this->event->trigger('swow.websocket.Open', $request);
    }

    /**
     * "onMessage" listener.
     * @param WebSocketFrame $frame
     */
    public function onMessage(WebSocketFrame $frame)
    {
        $this->event->trigger('swow.websocket.Message', $frame);

        $this->event->trigger('swow.websocket.Event', $this->decode($frame->getPayloadData()));
    }

    /**
     * "onClose" listener.
     */
    public function onClose()
    {
        $this->event->trigger('swow.websocket.Close');
    }

    protected function decode($payload)
    {
        $data = json_decode($payload, true);

        return new WsEvent($data['type'] ?? null, $data['data'] ?? null);
    }

    public function encodeMessage($message)
    {
        if ($message instanceof WsEvent) {
            return json_encode([
                'type' => $message->type,
                'data' => $message->data,
            ]);
        }
        return $message;
    }
}
