<?php

namespace EvolutionCMS\evoAi\Foundation\Bus;

trait Dispatchable
{
    public static function dispatch(...$arguments): PendingDispatch
    {
        return new PendingDispatch(new static(...$arguments));
    }

    public static function dispatchSync(...$arguments)
    {
        return (new PendingDispatch(new static(...$arguments)))->dispatchSync();
    }
}
