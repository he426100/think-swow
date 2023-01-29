<?php
// 代码来自open-smf/connection-pool
namespace think\swow\connection;

interface ConnectionPoolInterface
{

    /**
     * Initialize the connection pool
     * @return bool
     */
    public function init(): bool;

    /**
     * Return a connection to the connection pool
     * @param mixed $connection
     * @return bool
     */
    public function return($connection): bool;

    /**
     * Borrow a connection to the connection pool
     * @return mixed
     * @throws BorrowConnectionTimeoutException
     */
    public function borrow();

    /**
     * Close the connection pool, release the resource of all connections
     * @return bool
     */
    public function close(): bool;
}