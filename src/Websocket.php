<?php

namespace think\swow;

use Swow\Psr7\Server\ServerConnection;
use think\Event;
use think\swow\websocket\Pusher;
use think\swow\websocket\Room;

/**
 * Class Websocket
 */
class Websocket
{
    /**
     * @var \think\App
     */
    protected $app;

    /**
     * @var Room
     */
    protected $room;

    /**
     * Scoket sender's fd.
     *
     * @var string
     */
    protected $sender;

    /** @var Event */
    protected $event;

    /** @var ServerConnection */
    protected $client;

    protected $connected = true;

    /**
     * Websocket constructor.
     *
     * @param \think\App $app
     * @param Room $room
     * @param Event $event
     */
    public function __construct(\think\App $app, Room $room, Event $event)
    {
        $this->app   = $app;
        $this->room  = $room;
        $this->event = $event;
    }

    /**
     * @return Pusher
     */
    protected function makePusher()
    {
        return $this->app->invokeClass(Pusher::class);
    }

    public function to(...$values)
    {
        return $this->makePusher()->to(...$values);
    }

    public function push($data)
    {
        $this->makePusher()->to($this->getSender())->push($data);
    }

    public function emit(string $event, ...$data)
    {
        $this->makePusher()->to($this->getSender())->emit($event, ...$data);
    }

    /**
     * Join sender to multiple rooms.
     *
     * @param string|integer|array $rooms
     *
     * @return $this
     */
    public function join($rooms): self
    {
        $rooms = is_string($rooms) || is_int($rooms) ? func_get_args() : $rooms;

        $this->room->add($this->getSender(), $rooms);

        return $this;
    }

    /**
     * Make sender leave multiple rooms.
     *
     * @param array|string|integer $rooms
     *
     * @return $this
     */
    public function leave($rooms = []): self
    {
        $rooms = is_string($rooms) || is_int($rooms) ? func_get_args() : $rooms;

        $this->room->delete($this->getSender(), $rooms);

        return $this;
    }

    public function setConnected($connected)
    {
        $this->connected = $connected;
    }

    public function isEstablished()
    {
        return $this->connected;
    }

    /**
     * Close current connection.
     */
    public function close()
    {
        if ($this->client) {
            $this->client->close();
        }
    }

    /**
     * @param ServerConnection $connection
     */
    public function setClient($connection)
    {
        $this->client = $connection;
    }

    /**
     * Set sender fd.
     *
     * @param int
     *
     * @return $this
     */
    public function setSender(int $fd)
    {
        $this->sender = $fd;
        return $this;
    }

    /**
     * Get current sender fd.
     * 
     * @return int
     */
    public function getSender()
    {
        return $this->sender;
    }
}
