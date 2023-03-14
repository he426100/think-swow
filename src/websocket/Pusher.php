<?php

namespace think\swow\websocket;

use think\swow\contract\websocket\HandlerInterface;
use think\swow\Manager;
use think\swow\websocket\message\PushMessage;

/**
 * Class Pusher
 */
class Pusher
{

    /** @var Room */
    protected $room;

    /** @var Manager */
    protected $manager;

    /** @var HandlerInterface */
    protected $handler;

    protected $to = [];

    public function __construct(Manager $manager, Room $room, HandlerInterface $handler)
    {
        $this->manager = $manager;
        $this->room    = $room;
        $this->handler = $handler;
    }

    public function to(...$values)
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                $this->to(...$value);
            } elseif (!in_array($value, $this->to)) {
                $this->to[] = $value;
            }
        }

        return $this;
    }

    /**
     * Push message to related descriptors
     * @param $data
     * @return void
     */
    public function push($data): void
    {
        $fds = [];

        foreach ($this->to as $room) {
            $clients = $this->room->getClients((string)$room);
            if (!empty($clients)) {
                $fds = array_merge($fds, $clients);
            }
        }

        foreach (array_unique($fds) as $fd) {
            [$workerId, $fd] = explode('.', $fd);
            $data = $this->handler->encodeMessage($data);
            $this->manager->sendMessage((int) $workerId, new PushMessage($fd, $data));
        }
    }

    public function emit(string $event, ...$data): void
    {
        $this->push(new Event($event, $data));
    }
}
