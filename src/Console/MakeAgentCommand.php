<?php

namespace EvolutionCMS\evoAi\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeAgentCommand extends Command
{
    protected $signature = 'make:agent
        {name : Agent class name}
        {--structured : Generate an agent with structured output}
        {--force : Overwrite existing file}';

    protected $description = 'Create a new AI agent class (Evolution CMS)';

    public function handle(): int
    {
        $name = trim((string)$this->argument('name'));
        if ($name === '') {
            $this->error('Agent name is required.');
            return self::FAILURE;
        }

        [$namespace, $class, $path] = $this->resolveTarget($name, 'Agents');

        if (file_exists($path) && !$this->option('force')) {
            $this->error('Agent already exists: ' . $path);
            return self::FAILURE;
        }

        $stub = $this->getStub($this->option('structured') ? 'structured-agent.stub' : 'agent.stub');
        if ($stub === null) {
            $this->error('Stub not found.');
            return self::FAILURE;
        }

        $contents = str_replace(
            ['{{ namespace }}', '{{ class }}'],
            [$namespace, $class],
            $stub
        );

        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $contents);

        $this->ensureCustomAutoload();

        $this->info('Agent created: ' . $path);
        $this->line('Run composer dumpautoload if the class is not found.');

        return self::SUCCESS;
    }

    protected function resolveTarget(string $name, string $type): array
    {
        $normalized = str_replace(['\\', '.'], '/', $name);
        $parts = array_values(array_filter(explode('/', $normalized), fn ($p) => $p !== ''));

        $class = Str::studly(array_pop($parts) ?? $type);
        $subParts = array_map(fn ($p) => Str::studly($p), $parts);
        $subNamespace = $subParts ? '\\' . implode('\\', $subParts) : '';
        $subPath = $subParts ? '/' . implode('/', $subParts) : '';

        $namespace = 'App\\Ai\\' . $type . $subNamespace;
        $path = $this->customAppPath("Ai/{$type}{$subPath}/{$class}.php");

        return [$namespace, $class, $path];
    }

    protected function getStub(string $name): ?string
    {
        $custom = $this->corePath("stubs/{$name}");
        if (is_file($custom)) {
            return file_get_contents($custom);
        }

        $package = dirname(__DIR__, 2) . "/stubs/{$name}";
        if (is_file($package)) {
            return file_get_contents($package);
        }

        return null;
    }

    protected function ensureCustomAutoload(): void
    {
        $composerPath = $this->corePath('custom/composer.json');

        $data = [
            'name' => 'evolutioncms/custom',
            'require' => [],
            'autoload' => [
                'psr-4' => [],
            ],
        ];

        if (is_file($composerPath)) {
            $decoded = json_decode((string)file_get_contents($composerPath), true);
            if (is_array($decoded)) {
                $data = $decoded;
            }
        }

        $autoload = $data['autoload'] ?? [];
        $psr4 = $autoload['psr-4'] ?? [];

        if (!is_array($psr4)) {
            $psr4 = [];
        }

        if (!isset($psr4['App\\'])) {
            $psr4['App\\'] = 'custom/app/';
            $autoload['psr-4'] = $psr4;
            $data['autoload'] = $autoload;
            file_put_contents($composerPath, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        }
    }

    protected function corePath(string $path): string
    {
        if (defined('EVO_CORE_PATH')) {
            return rtrim(EVO_CORE_PATH, '/\\') . '/' . ltrim($path, '/\\');
        }

        return base_path(ltrim($path, '/\\'));
    }

    protected function customAppPath(string $path): string
    {
        return $this->corePath('custom/app/' . ltrim($path, '/\\'));
    }
}
