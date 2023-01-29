<?php
// 代码来自hyperf
namespace think\swow\exception;

use Throwable;

final class ExceptionThrower
{
    public function __construct(private Throwable $throwable)
    {
    }

    public function getThrowable(): Throwable
    {
        return $this->throwable;
    }
}
