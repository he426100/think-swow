<?php
// 代码来自open-smf/connection-pool
namespace think\swow\connection;

use Swow\Channel;
use think\swow\Coroutine;
use think\swow\coroutine\Timer;
use think\swow\connection\Connectors\ConnectorInterface;

class ConnectionPool implements ConnectionPoolInterface
{
    /**@var int The timeout of the operation channel */
    const CHANNEL_TIMEOUT = 1;

    /**@var int The minimum interval to check the idle connections */
    const MIN_CHECK_IDLE_INTERVAL = 10;

    /**@var string The key about the last active time of connection */
    const KEY_LAST_ACTIVE_TIME = '__lat';

    /**@var bool Whether the connection pool is initialized */
    protected $initialized;

    /**@var bool Whether the connection pool is closed */
    protected $closed;

    /**@var Channel The connection pool */
    protected $pool;

    /**@var ConnectorInterface The connector */
    protected $connector;

    /**@var array The config of connection */
    protected $connectionConfig;

    /**@var int Current all connection count */
    protected $connectionCount = 0;

    /**@var int The minimum number of active connections */
    protected $minActive = 1;

    /**@var int The maximum number of active connections */
    protected $maxActive = 1;

    /**@var int The maximum waiting time for connection, when reached, an exception will be thrown */
    protected $maxWaitTime = 5000;

    /**@var int The maximum idle time for the connection, when reached, the connection will be removed from pool, and keep the least $minActive connections in the pool */
    protected $maxIdleTime = 5000;

    /**@var int The interval to check idle connection */
    protected $idleCheckInterval = 5000;

    /**@var int The timer id of balancer */
    protected $balancerTimerId;

    /**
     * ConnectionPool constructor.
     * @param array $poolConfig The minimum number of active connections, the detail keys:
     * int minActive The minimum number of active connections
     * int maxActive The maximum number of active connections
     * float maxWaitTime The maximum waiting time for connection, when reached, an exception will be thrown
     * float maxIdleTime The maximum idle time for the connection, when reached, the connection will be removed from pool, and keep the least $minActive connections in the pool
     * float idleCheckInterval The interval to check idle connection
     * @param ConnectorInterface $connector The connector instance of ConnectorInterface
     * @param array $connectionConfig The config of connection
     */
    public function __construct(array $poolConfig, ConnectorInterface $connector, array $connectionConfig)
    {
        $this->initialized = false;
        $this->closed = false;
        $this->minActive = $poolConfig['minActive'] ?? 20;
        $this->maxActive = $poolConfig['maxActive'] ?? 100;
        $this->maxWaitTime = $poolConfig['maxWaitTime'] ?? 5000;
        $this->maxIdleTime = $poolConfig['maxIdleTime'] ?? 30000;
        $poolConfig['idleCheckInterval'] = $poolConfig['idleCheckInterval'] ?? 15000;
        $this->idleCheckInterval = $poolConfig['idleCheckInterval'] >= static::MIN_CHECK_IDLE_INTERVAL ? $poolConfig['idleCheckInterval'] : static::MIN_CHECK_IDLE_INTERVAL;
        $this->connectionConfig = $connectionConfig;
        $this->connector = $connector;
    }

    /**
     * Initialize the connection pool
     * @return bool
     */
    public function init(): bool
    {
        if ($this->initialized) {
            return false;
        }
        $this->initialized = true;
        $this->pool = new Channel($this->maxActive);
        $this->balancerTimerId = $this->startBalanceTimer($this->idleCheckInterval);
        Coroutine::create(function () {
            for ($i = 0; $i < $this->minActive; $i++) {
                $connection = $this->createConnection();
                try {
                    $this->pool->push($connection, static::CHANNEL_TIMEOUT);
                } catch (\Throwable) {
                    $this->removeConnection($connection);
                }
            }
        });
        return true;
    }

    /**
     * Borrow a connection from the connection pool, throw an exception if timeout
     * @return mixed The connection resource
     * @throws BorrowConnectionTimeoutException
     * @throws \RuntimeException
     */
    public function borrow()
    {
        if (!$this->initialized) {
            throw new \RuntimeException('Please initialize the connection pool first, call $pool->init().');
        }
        if ($this->pool->isEmpty()) {
            // Create more connections
            if ($this->connectionCount < $this->maxActive) {
                return $this->createConnection();
            }
        }

        try {
            $connection = $this->pool->pop($this->maxWaitTime);
            if ($this->connector->isConnected($connection)) {
                // Reset the connection for the connected connection
                $this->connector->reset($connection, $this->connectionConfig);
            } else {
                // Remove the disconnected connection, then create a new connection
                $this->removeConnection($connection);
                $connection = $this->createConnection();
            }
            return $connection;
        } catch (\Throwable) {
            $exception = new BorrowConnectionTimeoutException(sprintf(
                'Borrow the connection timeout in %.2f(s), connections in pool: %d, all connections: %d',
                $this->maxWaitTime,
                $this->pool->getLength(),
                $this->connectionCount
            ));
            $exception->setTimeout($this->maxWaitTime);
            throw $exception;
        }
    }

    /**
     * Return a connection to the connection pool
     * @param mixed $connection The connection resource
     * @return bool
     */
    public function return($connection): bool
    {
        if (!$this->connector->validate($connection)) {
            throw new \RuntimeException('Connection of unexpected type returned.');
        }

        if (!$this->initialized) {
            throw new \RuntimeException('Please initialize the connection pool first, call $pool->init().');
        }
        if ($this->pool->isFull()) {
            // Discard the connection
            $this->removeConnection($connection);
            return false;
        }
        $connection->{static::KEY_LAST_ACTIVE_TIME} = time();
        try {
            $this->pool->push($connection, static::CHANNEL_TIMEOUT);
            return true;
        } catch (\Throwable) {
            $this->removeConnection($connection);
        }
        return false;
    }

    /**
     * Get the number of created connections
     * @return int
     */
    public function getConnectionCount(): int
    {
        return $this->connectionCount;
    }

    /**
     * Get the number of idle connections
     * @return int
     */
    public function getIdleCount(): int
    {
        return $this->pool->getLength();
    }

    /**
     * Close the connection pool and disconnect all connections
     * @return bool
     */
    public function close(): bool
    {
        if (!$this->initialized) {
            return false;
        }
        if ($this->closed) {
            return false;
        }
        $this->closed = true;
        Timer::deleteTimer($this->balancerTimerId);
        Coroutine::create(function () {
            while (true) {
                if ($this->pool->isEmpty()) {
                    break;
                }
                try {
                    $connection = $this->pool->pop(static::CHANNEL_TIMEOUT);
                    $this->connector->disconnect($connection);
                } catch (\Throwable) {}
            }
            $this->pool->close();
        });
        return true;
    }

    public function __destruct()
    {
        $this->close();
    }

    protected function startBalanceTimer(float $interval)
    {
        return Timer::repeat(round($interval) * 1000, function () {
            $now = time();
            $validConnections = [];
            while (true) {
                if ($this->closed) {
                    break;
                }
                if ($this->connectionCount <= $this->minActive) {
                    break;
                }
                if ($this->pool->isEmpty()) {
                    break;
                }
                
                try {
                    $connection = $this->pool->pop(static::CHANNEL_TIMEOUT);
                    $lastActiveTime = $connection->{static::KEY_LAST_ACTIVE_TIME} ?? 0;
                    if ($now - $lastActiveTime < $this->maxIdleTime) {
                        $validConnections[] = $connection;
                    } else {
                        $this->removeConnection($connection);
                    }
                } catch (\Throwable) {
                }
            }

            foreach ($validConnections as $validConnection) {
                try {
                    $this->pool->push($validConnection, static::CHANNEL_TIMEOUT);
                } catch (\Throwable) {
                    $this->removeConnection($validConnection);
                }
            }
        });
    }

    protected function createConnection()
    {
        $this->connectionCount++;
        $connection = $this->connector->connect($this->connectionConfig);
        $connection->{static::KEY_LAST_ACTIVE_TIME} = time();
        return $connection;
    }

    protected function removeConnection($connection)
    {
        $this->connectionCount--;
        Coroutine::create(function () use ($connection) {
            try {
                $this->connector->disconnect($connection);
            } catch (\Throwable $e) {
                // Ignore this exception.
            }
        });
    }
}
