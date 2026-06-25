<?php

namespace EvolutionCMS\evoAi;

use EvolutionCMS\ServiceProvider;

class evoAiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->loadPluginsFrom(dirname(__DIR__) . '/plugins/');
        $this->registerFoundationShims();
        $this->registerPrismProvider();
        $this->loadViewsFrom(dirname(__DIR__) . '/views', 'evoAi');
    }

    public function boot(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/evoAiSettings.php', 'cms.settings.evoAi');
        $this->mergeConfigFrom(dirname(__DIR__) . '/config/ai.php', 'ai');

        $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishResources();
            $this->ensureConfigPublished();
            $this->registerConsoleCommands();
        }

        $this->app->booted(function () {
            if ($this->app->runningInConsole()) {
                $this->flattenPublishDirectories();
            }
        });
    }

    protected function publishResources(): void
    {
        $settingsPath = $this->customConfigPath('cms/settings/evoAi.php');
        $aiConfigPath = $this->customConfigPath('ai.php');

        $this->publishes([
            dirname(__DIR__) . '/config/evoAiSettings.php' => $settingsPath,
        ], 'evoai-config');

        $this->publishes([
            dirname(__DIR__) . '/config/ai.php' => $aiConfigPath,
        ], 'evoai-ai-config');

        $stubsPath = dirname(__DIR__) . '/stubs';
        if (is_dir($stubsPath)) {
            $this->publishes([
                $stubsPath . '/agent.stub' => base_path('stubs/agent.stub'),
                $stubsPath . '/structured-agent.stub' => base_path('stubs/structured-agent.stub'),
                $stubsPath . '/tool.stub' => base_path('stubs/tool.stub'),
            ], 'evoai-stubs');
        }
    }

    protected function registerConsoleCommands(): void
    {
        $commands = [];

        if (class_exists(\EvolutionCMS\evoAi\Console\AiTestCommand::class)) {
            $commands[] = \EvolutionCMS\evoAi\Console\AiTestCommand::class;
        }
        if (class_exists(\EvolutionCMS\evoAi\Console\MakeAgentCommand::class)) {
            $commands[] = \EvolutionCMS\evoAi\Console\MakeAgentCommand::class;
        }
        if (class_exists(\EvolutionCMS\evoAi\Console\MakeToolCommand::class)) {
            $commands[] = \EvolutionCMS\evoAi\Console\MakeToolCommand::class;
        }

        if ($commands !== []) {
            $this->commands($commands);
        }
    }

    protected function ensureConfigPublished(): void
    {
        $publishMap = [
            dirname(__DIR__) . '/config/evoAiSettings.php' => $this->customConfigPath('cms/settings/evoAi.php'),
            dirname(__DIR__) . '/config/ai.php' => $this->customConfigPath('ai.php'),
        ];

        foreach ($publishMap as $from => $to) {
            if (!is_file($from)) {
                continue;
            }
            if (is_file($to)) {
                continue;
            }
            $directory = dirname($to);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }
            @copy($from, $to);
        }
    }

    protected function customConfigPath(string $path): string
    {
        $path = ltrim($path, '/\\');

        if (function_exists('config_path')) {
            $candidate = config_path($path, true);
            if (is_string($candidate) && $candidate !== '') {
                $normalized = str_replace('\\', '/', $candidate);
                if (str_contains($normalized, '/custom/config/')) {
                    return $candidate;
                }
            }
        }

        if (defined('EVO_CORE_PATH')) {
            return rtrim(EVO_CORE_PATH, '/\\') . '/custom/config/' . $path;
        }

        return base_path('core/custom/config/' . $path);
    }

    protected function registerFoundationShims(): void
    {
        $this->aliasIfMissing(
            'Laravel\\Ai\\AiServiceProvider',
            \EvolutionCMS\evoAi\LaravelAi\AiServiceProvider::class
        );

        $this->aliasIfMissing(
            'Illuminate\\Foundation\\Queue\\Queueable',
            \EvolutionCMS\evoAi\Foundation\Queue\Queueable::class
        );

        $this->aliasIfMissing(
            'Illuminate\\Foundation\\Bus\\PendingDispatch',
            \EvolutionCMS\evoAi\Foundation\Bus\PendingDispatch::class
        );

        $this->aliasIfMissing(
            'Illuminate\\Foundation\\Bus\\Dispatchable',
            \EvolutionCMS\evoAi\Foundation\Bus\Dispatchable::class
        );
    }

    protected function registerPrismProvider(): void
    {
        if (class_exists(\Prism\Prism\PrismServiceProvider::class)) {
            $this->app->register(\Prism\Prism\PrismServiceProvider::class);
        }
    }

    protected function aliasIfMissing(string $alias, string $target): void
    {
        if (class_exists($alias, false)) {
            return;
        }

        if (class_exists($target)) {
            class_alias($target, $alias);
        }
    }

    protected function flattenPublishDirectories(): void
    {
        if (!class_exists(\Illuminate\Support\ServiceProvider::class)) {
            return;
        }

        $reflection = new \ReflectionClass(\Illuminate\Support\ServiceProvider::class);
        $publishesProperty = $reflection->getProperty('publishes');
        $publishesProperty->setAccessible(true);
        $publishGroupsProperty = $reflection->getProperty('publishGroups');
        $publishGroupsProperty->setAccessible(true);

        $publishes = $publishesProperty->getValue();
        $publishGroups = $publishGroupsProperty->getValue();

        foreach ($publishes as $provider => $paths) {
            $publishes[$provider] = $this->expandPublishPaths($paths);
        }

        foreach ($publishGroups as $group => $paths) {
            $publishGroups[$group] = $this->expandPublishPaths($paths);
        }

        $publishesProperty->setValue(null, $publishes);
        $publishGroupsProperty->setValue(null, $publishGroups);
    }

    protected function expandPublishPaths(array $paths): array
    {
        $expanded = [];

        foreach ($paths as $from => $to) {
            if (is_dir($from)) {
                $files = $this->collectPublishFiles($from, $to);
                if ($files !== []) {
                    $expanded = array_merge($expanded, $files);
                    continue;
                }
            }
            $expanded[$from] = $to;
        }

        return $expanded;
    }

    protected function collectPublishFiles(string $sourceDir, string $targetDir): array
    {
        if (!is_dir($sourceDir)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS)
        );

        $sourceDir = rtrim($sourceDir, DIRECTORY_SEPARATOR);
        $targetDir = rtrim($targetDir, DIRECTORY_SEPARATOR);

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            $relative = substr($path, strlen($sourceDir) + 1);
            $files[$path] = $targetDir . DIRECTORY_SEPARATOR . $relative;
        }

        return $files;
    }
}
