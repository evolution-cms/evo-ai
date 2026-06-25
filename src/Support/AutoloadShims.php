<?php

// Load shims early (before providers are registered).

if (!class_exists('EvolutionCMS\\eAi\\eAiServiceProvider', false)) {
    class_alias(\EvolutionCMS\evoAi\evoAiServiceProvider::class, 'EvolutionCMS\\eAi\\eAiServiceProvider');
}

if (!class_exists('Laravel\\Ai\\AiServiceProvider', false)) {
    class_alias(\EvolutionCMS\evoAi\LaravelAi\AiServiceProvider::class, 'Laravel\\Ai\\AiServiceProvider');
}

if (!class_exists('Illuminate\\Foundation\\Queue\\Queueable', false)) {
    class_alias(\EvolutionCMS\evoAi\Foundation\Queue\Queueable::class, 'Illuminate\\Foundation\\Queue\\Queueable');
}

if (!class_exists('Illuminate\\Foundation\\Bus\\PendingDispatch', false)) {
    class_alias(\EvolutionCMS\evoAi\Foundation\Bus\PendingDispatch::class, 'Illuminate\\Foundation\\Bus\\PendingDispatch');
}

if (!class_exists('Illuminate\\Foundation\\Bus\\Dispatchable', false)) {
    class_alias(\EvolutionCMS\evoAi\Foundation\Bus\Dispatchable::class, 'Illuminate\\Foundation\\Bus\\Dispatchable');
}
