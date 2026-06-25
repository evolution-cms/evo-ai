<p align="center">
<a href="https://packagist.org/packages/evolution-cms/eai"><img src="https://img.shields.io/packagist/dt/evolution-cms/eai" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/evolution-cms/eai"><img src="https://img.shields.io/packagist/v/evolution-cms/eai" alt="Latest Stable Version"></a>
<img src="https://img.shields.io/badge/license-MIT-green" alt="License">
</p>

# evoAi for Evolution CMS

eAi is the Evolution CMS integration layer for the Laravel AI SDK. It provides Evo‑native config publishing, minimal shims for missing `Illuminate\Foundation` classes, and an sTask‑first queue bridge.

If you only need a quick start and examples, use this README. For full details see `DOCS.md` (EN) or `DOCS.uk.md` (UA).

## Requirements
- Evolution CMS 3.5+
- PHP 8.3+
- Composer 2.2+

Optional:
- sTask for async tasks (package constraint: `^1.0`)

## Quick Start
From your Evo `core` directory:

```bash
cd core
php artisan package:installrequire evolution-cms/eai "*"
php artisan migrate
```

Publish configs and stubs (optional, auto‑publish is enabled):

```bash
php artisan vendor:publish --provider="EvolutionCMS\\eAi\\eAiServiceProvider" --tag=eai-config
php artisan vendor:publish --provider="EvolutionCMS\\eAi\\eAiServiceProvider" --tag=eai-ai-config
php artisan vendor:publish --provider="EvolutionCMS\\eAi\\eAiServiceProvider" --tag=eai-stubs
```

Set provider key in `.env` or `core/custom/config/ai.php`:

```
OPENAI_API_KEY=...
```

Run the built‑in smoke test:

```bash
php artisan ai:test
```

For local Ollama:

```bash
php artisan ai:test --provider=ollama
```

## Minimal Usage
```php
use App\Ai\Agents\SupportAgent;

$agent = new SupportAgent();
$response = $agent->prompt('Hello from Evo');

echo $response->text;
```

## Queues (sTask‑first)
- sTask is the primary backend; `sync` is a fallback.
- eAi does not implement Laravel Queue; it only provides SDK‑compatible dispatching.

Process queued tasks:

```bash
php artisan stask:worker
```

sTask UI workers (for testing):
- `eai_smoke` — fixed prompt
- `eai_prompt` — custom prompt from widget

## AI Service Account (Role‑based)
AI runs as a normal manager user with role **AI** (auto‑created). The role is read‑only by default; to allow saving/publishing, elevate permissions manually (e.g. Publisher).

Example settings in `core/custom/config/cms/settings/eAi.php`:
```
ai_actor_mode: service
ai_actor_email: ai@your-host
ai_actor_autocreate: true
ai_actor_block_login: true
ai_actor_role: AI
ai_actor_role_autocreate: true
```

If you need access to package interfaces, grant permissions `stask` and/or `sapi` to the **AI** role (grouped under `sPackages`).

## Artisan Generators
```bash
php artisan make:agent SalesCoach
php artisan make:agent SalesCoach --structured
php artisan make:tool RandomNumberGenerator
```

Generated classes are placed in `core/custom/app/Ai/...`. If autoloading is not updated, run `composer dumpautoload`.

## More Details
See `DOCS.md` for full configuration reference, identity rules, queue contract, and advanced SDK usage.
