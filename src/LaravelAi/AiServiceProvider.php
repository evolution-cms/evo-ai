<?php

namespace EvolutionCMS\evoAi\LaravelAi;

use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Stringable;
use Laravel\Ai\AiManager;
use Laravel\Ai\Contracts\ConversationStore;
use Laravel\Ai\Storage\DatabaseConversationStore;

class AiServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->scoped(AiManager::class, fn ($app): AiManager => new AiManager($app));
        $this->app->singleton(ConversationStore::class, DatabaseConversationStore::class);
    }

    public function boot()
    {
        if ($this->app->runningInConsole()) {
            // Commands are registered by evoAiServiceProvider.
        }

        Stringable::macro('toEmbeddings', function (
            ?string $provider = null,
            ?int $dimensions = null,
            ?string $model = null,
            bool|int|null $cache = null,
        ) {
            $request = \Laravel\Ai\Embeddings::for([$this->value]);

            if ($dimensions) {
                $request->dimensions($dimensions);
            }

            if ($cache !== false && ! is_null($cache)) {
                $request->cache(is_int($cache) ? $cache : null);
            }

            return $request->generate(provider: $provider, model: $model)->embeddings[0];
        });

        Collection::macro('rerank', function (
            Closure|array|string $by,
            string $query,
            ?int $limit = null,
            array|string|null $provider = null,
            ?string $model = null
        ) {
            $resolver = match (true) {
                $by instanceof Closure => $by,
                is_array($by) => fn ($item) => json_encode(
                    (new Collection($by))->mapWithKeys(fn ($field) => [$field => data_get($item, $field)])->all()
                ),
                default => fn ($item) => data_get($item, $by),
            };

            $response = \Laravel\Ai\Reranking::of($this->map($resolver)->values()->all())
                ->limit($limit)
                ->rerank($query, $provider, $model);

            return (new Collection($response->results))->map(
                fn ($result) => $this->values()[$result->index]
            );
        });
    }

    protected function registerCommands(): void
    {
        // no-op: commands are registered by evoAiServiceProvider
    }
}
