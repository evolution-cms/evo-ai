<?php

namespace EvolutionCMS\evoAi\Console;

use Illuminate\Console\Command;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Throwable;

class AiTestCommand extends Command
{
    protected $signature = 'ai:test
        {--provider= : AI provider name from config/ai.php}
        {--model= : Optional model override}
        {--prompt= : Prompt to send (default: \"Reply with ok\") }';

    protected $description = 'Run a basic smoke test for the Evolution CMS AI integration';

    public function handle(): int
    {
        $provider = $this->option('provider');
        $model = $this->option('model');
        $prompt = (string) ($this->option('prompt') ?: 'Reply with ok.');

        $providers = config('ai.providers', []);
        if (!is_array($providers) || $providers === []) {
            $this->error('No providers configured in config/ai.php.');
            return self::FAILURE;
        }

        $selected = $this->resolveProvider($provider, $providers);
        if ($selected === null) {
            $this->error('No provider with a configured API key was found.');
            $this->line('Add OPENAI_API_KEY (or another provider key) to .env and retry,');
            $this->line('or run with --provider=ollama for a local setup.');
            return self::FAILURE;
        }

        if ($this->requiresApiKey($selected) && !$this->providerHasKey($providers[$selected] ?? null)) {
            $this->error('Selected provider requires an API key.');
            $this->line('Set the provider key in .env or core/custom/config/ai.php and retry.');
            return self::FAILURE;
        }

        $this->info('Using provider: ' . $selected);
        if (is_string($model) && $model !== '') {
            $this->line('Model override: ' . $model);
        }

        $agent = new class implements Agent, Conversational, HasTools {
            use Promptable;

            public function instructions(): string
            {
                return 'You are a helpful assistant.';
            }

            public function messages(): iterable
            {
                return [];
            }

            public function tools(): iterable
            {
                return [];
            }
        };

        try {
            $response = $agent->prompt($prompt, provider: $selected, model: $model ?: null);
        } catch (Throwable $e) {
            $this->error('AI call failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->line('Response:');
        $this->line(is_string($response->text ?? null) ? $response->text : (string) $response);

        return self::SUCCESS;
    }

    protected function resolveProvider(?string $explicit, array $providers): ?string
    {
        if (is_string($explicit) && $explicit !== '') {
            if (array_key_exists($explicit, $providers)) {
                return $explicit;
            }
            return null;
        }

        $default = config('ai.default');
        if (is_string($default) && $default !== '' && $this->providerHasKey($providers[$default] ?? null)) {
            return $default;
        }

        foreach ($providers as $name => $config) {
            if ($this->providerHasKey($config)) {
                return is_string($name) ? $name : null;
            }
        }

        return null;
    }

    protected function providerHasKey(mixed $config): bool
    {
        if (!is_array($config)) {
            return false;
        }
        $key = $config['key'] ?? null;

        return is_string($key) && trim($key) !== '';
    }

    protected function requiresApiKey(string $provider): bool
    {
        return $provider !== 'ollama';
    }
}
