<?php

namespace EvolutionCMS\evoAi\Foundation\Queue;

use EvolutionCMS\evoAi\Foundation\Bus\PendingDispatch;

trait Queueable
{
    public $connection;
    public $queue;

    public function onConnection($connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    public function onQueue($queue): self
    {
        $this->queue = $queue;
        return $this;
    }

    public static function dispatch(...$arguments): PendingDispatch
    {
        return new PendingDispatch(new static(...$arguments));
    }

    public static function dispatchSync(...$arguments)
    {
        return (new PendingDispatch(new static(...$arguments)))->dispatchSync();
    }
}
