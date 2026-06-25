<?php

use EvolutionCMS\Models\User;
use EvolutionCMS\Models\UserAttribute;
use EvolutionCMS\Models\UserRole;
use Illuminate\Support\Facades\Schema;
use Seiger\sTask\Models\sWorker;

if (!function_exists('evoAi_log')) {
    function evoAi_log(string $message, int $type = 2): void
    {
        if (function_exists('evo')) {
            evo()->logEvent(0, $type, $message, 'evoAi');
        }
    }
}

if (!function_exists('evoAi_settings')) {
    function evoAi_settings(): array
    {
        $settings = config('cms.settings.evoAi', []);
        return is_array($settings) ? $settings : [];
    }
}

if (!function_exists('evoAi_enabled')) {
    function evoAi_enabled(): bool
    {
        $settings = evoAi_settings();
        return (bool)($settings['enable'] ?? false);
    }
}

if (!function_exists('evoAi_resolve_ids')) {
    function evoAi_resolve_ids(): array
    {
        evoAi_register_stask_worker();

        $conversationUserId = 1;
        $initiatedByUserId = null;
        $context = 'cli';

        if (function_exists('evo') && evo()->isLoggedIn('mgr')) {
            $conversationUserId = (int)evo()->getLoginUserID('mgr');
            $initiatedByUserId = $conversationUserId;
            $context = 'mgr';
        } elseif (function_exists('evo') && evo()->isLoggedIn()) {
            $conversationUserId = (int)evo()->getLoginUserID();
            $initiatedByUserId = $conversationUserId;
            $context = 'web';
        }

        $actorUserId = evoAi_actor_user_id() ?? $conversationUserId;

        return [
            'conversation_user_id' => $conversationUserId,
            'actor_user_id' => $actorUserId,
            'initiated_by_user_id' => $initiatedByUserId,
            'context' => $context,
        ];
    }
}

if (!function_exists('evoAi_register_stask_worker')) {
    function evoAi_register_stask_worker(): void
    {
        $settings = evoAi_settings();
        if (($settings['queue_driver'] ?? 'stask') !== 'stask') {
            return;
        }

        if (!class_exists(\Seiger\sTask\Facades\sTask::class) || !class_exists(sWorker::class)) {
            return;
        }

        try {
            if (!class_exists(Schema::class) || !Schema::hasTable('s_workers')) {
                return;
            }
        } catch (Throwable $e) {
            return;
        }

        $workers = [
            [
                'identifier' => 'evoai',
                'scope' => 'system',
                'class' => \EvolutionCMS\evoAi\sTask\AiJobWorker::class,
                'active' => true,
                'hidden' => 1,
            ],
            [
                'identifier' => 'evoai_smoke',
                'scope' => 'evoAi',
                'class' => \EvolutionCMS\evoAi\sTask\AiSmokeWorker::class,
                'active' => true,
                'hidden' => 0,
            ],
            [
                'identifier' => 'evoai_prompt',
                'scope' => 'evoAi',
                'class' => \EvolutionCMS\evoAi\sTask\AiPromptWorker::class,
                'active' => true,
                'hidden' => 0,
            ],
        ];

        $position = (int) (sWorker::max('position') ?? 0);

        foreach ($workers as $worker) {
            $existing = sWorker::query()->where('identifier', $worker['identifier'])->first();
            if ($existing) {
                $changed = false;
                if ($existing->class !== $worker['class']) {
                    $existing->class = $worker['class'];
                    $changed = true;
                }
                if ((int)$existing->hidden !== (int)$worker['hidden']) {
                    $existing->hidden = $worker['hidden'];
                    $changed = true;
                }
                if ((string)$existing->scope !== (string)$worker['scope']) {
                    $existing->scope = $worker['scope'];
                    $changed = true;
                }
                if ($changed) {
                    $existing->save();
                }
                continue;
            }

            $position++;
            sWorker::query()->create([
                'identifier' => $worker['identifier'],
                'scope' => $worker['scope'],
                'class' => $worker['class'],
                'active' => $worker['active'],
                'position' => $position,
                'settings' => [],
                'hidden' => $worker['hidden'],
            ]);
        }
    }
}

if (!function_exists('evoAi_actor_user_id')) {
    function evoAi_actor_user_id(): ?int
    {
        $settings = evoAi_settings();
        if (($settings['ai_actor_mode'] ?? 'none') !== 'service') {
            return null;
        }

        $roleId = evoAi_ai_role_id();
        if (!$roleId) {
            return null;
        }

        $identifier = evoAi_actor_username($settings);
        $user = $identifier !== '' ? User::query()->where('username', $identifier)->first() : null;

        if (!$user) {
            if (class_exists(UserAttribute::class)) {
                $attr = UserAttribute::query()->where('role', $roleId)->orderBy('internalKey')->first();
                if ($attr) {
                    $user = User::query()->whereKey($attr->internalKey)->first();
                }
            }
        }
        if (!$user && !empty($settings['ai_actor_autocreate'])) {
            $user = evoAi_create_actor_user($identifier !== '' ? $identifier : 'AI');
        }

        if (!$user) {
            return null;
        }

        if (class_exists(UserAttribute::class)) {
            $roleId = evoAi_ai_role_id();
            if ($roleId) {
                UserAttribute::query()
                    ->where('internalKey', $user->getKey())
                    ->update(['role' => $roleId]);
            }

            if (!empty($settings['ai_actor_block_login'])) {
                UserAttribute::query()
                    ->where('internalKey', $user->getKey())
                    ->update(['blocked' => 1]);
            }
        }

        return (int)$user->getKey();
    }
}

if (!function_exists('evoAi_actor_username')) {
    function evoAi_actor_username(?array $settings = null): string
    {
        $settings = $settings ?? evoAi_settings();
        $roleName = trim((string)($settings['ai_actor_role'] ?? 'AI'));
        return $roleName !== '' ? $roleName : 'AI';
    }
}

if (!function_exists('evoAi_create_actor_user')) {
    function evoAi_create_actor_user(string $identifier): ?User
    {
        if (!class_exists(User::class) || !class_exists(UserAttribute::class)) {
            return null;
        }

        $settings = evoAi_settings();
        $email = (string)($settings['ai_actor_email'] ?? '');
        if ($email === '') {
            $host = 'localhost';
            if (function_exists('evo')) {
                $siteUrl = (string)evo()->getConfig('site_url');
                if ($siteUrl === '' && defined('EVO_SITE_URL')) {
                    $siteUrl = EVO_SITE_URL;
                }
                if ($siteUrl === '' && defined('MODX_SITE_URL')) {
                    $siteUrl = MODX_SITE_URL;
                }
                if ($siteUrl !== '') {
                    $parsed = parse_url($siteUrl);
                    if (is_array($parsed) && !empty($parsed['host'])) {
                        $host = $parsed['host'];
                    }
                }
            }
            $email = 'ai@' . $host;
        }

        $password = bin2hex(random_bytes(16));

        try {
            $user = \UserManager::create([
                'username' => $identifier,
                'password' => $password,
                'password_confirmation' => $password,
                'email' => $email,
                'fullname' => 'AI Service Account',
            ]);
        } catch (Throwable $e) {
            evoAi_log('evoAi: failed to create AI service user: ' . $e->getMessage(), 3);
            return null;
        }

        if (!$user instanceof User) {
            $user = User::query()->where('username', $identifier)->first();
        }

        if ($user && class_exists(UserAttribute::class)) {
            $roleId = evoAi_ai_role_id();
            if ($roleId) {
                UserAttribute::query()
                    ->where('internalKey', $user->getKey())
                    ->update(['role' => $roleId]);
            }

            if (!empty($settings['ai_actor_block_login'])) {
                UserAttribute::query()
                    ->where('internalKey', $user->getKey())
                    ->update(['blocked' => 1]);
            }
        }

        return $user;
    }
}

if (!function_exists('evoAi_ai_role_id')) {
    function evoAi_ai_role_id(): ?int
    {
        if (!class_exists(UserRole::class)) {
            return null;
        }

        $settings = evoAi_settings();
        $roleName = trim((string)($settings['ai_actor_role'] ?? 'AI'));
        if ($roleName === '') {
            $roleName = 'AI';
        }

        $role = UserRole::query()->where('name', $roleName)->first();
        if ($role) {
            return (int)$role->getKey();
        }

        if (empty($settings['ai_actor_role_autocreate'])) {
            evoAi_log('evoAi: AI role not found and auto-create disabled.', 2);
            return null;
        }

        try {
            $role = UserRole::query()->create([
                'name' => $roleName,
                'description' => 'AI service account (read-only baseline)',
            ]);
            return (int)$role->getKey();
        } catch (Throwable $e) {
            evoAi_log('evoAi: failed to create AI role: ' . $e->getMessage(), 2);
            return null;
        }
    }
}

Event::listen('evolution.OnManagerPageInit', function () {
    if (!evoAi_enabled()) {
        return;
    }

    evoAi_register_stask_worker();
    evoAi_actor_user_id();
});
