# DOCS — evo-ai (Evolution CMS + Laravel AI SDK)

This document is the detailed, structured guide for integrating Laravel AI SDK into Evolution CMS using the evo-ai package. It expands on the README with deeper configuration and operational details.

## 1) Overview
- evo-ai is a thin Evo‑native wrapper around `laravel/ai`.
- No `illuminate/foundation` is required; minimal shims are provided for SDK compatibility.
- sTask is the primary queue backend; `sync` is a fallback.
- AI actions run under a dedicated **AI** manager role (service account), not by end‑users.

## 2) Requirements
- Evolution CMS 3.5.2+
- PHP 8.3+
- Composer 2.2+

Optional:
- sTask for async execution (package constraint: `^1.0`)

## 3) Install
```bash
cd core
php artisan package:installrequire evolution-cms/evo-ai "*"
```

## 4) Publish & Migrate
Auto‑publish is enabled, but you may force it:

```bash
php artisan vendor:publish --provider="EvolutionCMS\\evoAi\\evoAiServiceProvider" --tag=evoai-config
php artisan vendor:publish --provider="EvolutionCMS\\evoAi\\evoAiServiceProvider" --tag=evoai-ai-config
php artisan vendor:publish --provider="EvolutionCMS\\evoAi\\evoAiServiceProvider" --tag=evoai-stubs
```

Migrations:
```bash
php artisan migrate
```

## 5) Configuration Reference

### 5.1 `core/custom/config/ai.php`
This is the Laravel AI SDK config. Common values:

```php
return [
    'default' => 'openai',
    'providers' => [
        'openai' => [
            'driver' => 'openai',
            'key' => env('OPENAI_API_KEY'),
        ],
        'ollama' => [
            'driver' => 'ollama',
            'key' => env('OLLAMA_API_KEY', ''),
            'url' => env('OLLAMA_BASE_URL', 'http://localhost:11434'),
        ],
    ],
];
```

Notes:
- `default_*` keys define provider defaults for images, audio, etc.
- Provider keys can be in `.env` or directly in config.

### 5.2 `core/custom/config/cms/settings/evoAi.php`
Evo integration settings:

```php
return [
    'enable' => true,
    'queue_driver' => 'stask',
    'queue_failover' => 'sync',
    'context' => 'auto',

    'ai_actor_mode' => 'service',
    'ai_actor_email' => null,
    'ai_actor_autocreate' => true,
    'ai_actor_block_login' => true,
    'ai_actor_role' => 'AI',
    'ai_actor_role_autocreate' => true,
];
```

Field notes:
- `queue_driver`: `stask` (primary) or `sync` (fallback).
- `queue_failover`: used when `stask` is missing.
- `context`: `mgr|web|auto` (execution context).
- `ai_actor_*`: controls the AI service account (manager user + role **AI**).

## 6) Identity & Security Model
- `conversation_user_id`: real user in history. If unknown (CLI/no session), fallback is `1`.
- `actor_user_id`: AI service account when enabled (role **AI**).
- `initiated_by_user_id`: who triggered the action (for auditing).

Rules:
- Write actions are only allowed via manager ACL, never via direct DB writes.
- AI user is a standard manager user with role **AI** (ApiUser‑style, no extra columns).
- Interactive login is blocked (`user_attributes.blocked=1`).
- The **AI** role is read‑only by default; to allow saving/publishing, elevate manually.
- If you need access to package interfaces, grant permissions `stask` and/or `sapi` (group `sPackages`).

## 7) Queues & sTask
- sTask is the primary backend. `sync` is a fallback for missing sTask or local smoke‑tests.
- evoAi does not implement Laravel Queue; it provides SDK‑compatible dispatching only.

### 7.1 sTask UI workers
Visible workers:
- `evoai_smoke` — fixed prompt, quick smoke test.
- `evoai_prompt` — custom prompt from widget.

Internal worker:
- `evoai` — used only for queued SDK jobs (not for manual execution).

Process tasks:
```bash
php artisan stask:worker
```

### 7.2 Payload contract (sTask)
Queued jobs contain:
- `job_class`
- `job_payload` (serialized)
- `attempts`, `max_attempts`
- `actor_user_id`, `conversation_user_id`, `initiated_by_user_id`
- `context` (`mgr|web|cli`)

`then/catch` are treated as minimal metadata; Laravel Bus compatibility is not guaranteed.

## 8) Artisan Commands

### 8.1 Smoke test
```bash
php artisan ai:test
```
Options:
- `--provider=openai|ollama|...`
- `--model=...`
- `--prompt="..."`

### 8.2 Generators
```bash
php artisan make:agent SalesCoach
php artisan make:agent SalesCoach --structured
php artisan make:tool RandomNumberGenerator
```

Generated classes:
- `core/custom/app/Ai/Agents/*`
- `core/custom/app/Ai/Tools/*`

If autoloading is stale:
```bash
composer dumpautoload
```

## 9) Usage Examples

### Agent prompt
```php
use App\Ai\Agents\SupportAgent;

$agent = new SupportAgent();
$response = $agent->prompt('Hello from Evo');

echo $response->text;
```

### Anonymous agent (quick)
```php
use function Laravel\Ai\agent;

$response = agent(
    instructions: 'You are a helpful assistant.',
    messages: [],
    tools: []
)->prompt('Summarize this page');

echo $response->text;
```

### Structured output
```php
use App\Ai\Agents\SalesCoach;

$response = (new SalesCoach())->prompt('Analyze this sales transcript...');

echo $response['score'];
```

### Tools
```php
use App\Ai\Tools\RandomNumberGenerator;

class SupportAgent implements \Laravel\Ai\Contracts\Agent, \Laravel\Ai\Contracts\HasTools
{
    use \Laravel\Ai\Promptable;

    public function instructions(): string { return 'You are helpful.'; }
    public function tools(): iterable { return [new RandomNumberGenerator]; }
}
```

### Queueing agent prompts
```php
use App\Ai\Agents\SalesCoach;

(new SalesCoach)
    ->queue('Analyze this transcript...')
    ->then(function ($response) {
        // handle response
    });
```

### Images
```php
use Laravel\Ai\Image;

$image = Image::of('A donut on the table')->generate();
$path = $image->storePubliclyAs('ai/donut.png');
```

### Audio (TTS)
```php
use Laravel\Ai\Audio;

$audio = Audio::of('Hello from Evo')->generate();
$path = $audio->storePubliclyAs('ai/hello.mp3');
```

### Transcription (STT)
```php
use Laravel\Ai\Transcription;

$text = Transcription::fromPath('/path/to/audio.mp3')->generate();
```

### Embeddings
```php
use Laravel\Ai\Embeddings;

$response = Embeddings::for(['Napa Valley has great wine.'])->generate();
$vector = $response->embeddings[0];
```

### Reranking
```php
use Laravel\Ai\Reranking;

$response = Reranking::of([
    'Django is a Python web framework.',
    'Laravel is a PHP web application framework.',
])->rerank('PHP frameworks');

echo $response->first()->document;
```

### Files & attachments
```php
use Laravel\Ai\Files\Document;
use App\Ai\Agents\SupportAgent;

$stored = Document::fromPath('/path/to/report.pdf')->put();

$response = (new SupportAgent())->prompt(
    'Summarize this report.',
    [Document::fromId($stored->id)]
);
```

## 10) Troubleshooting
- **`Target class [prism] does not exist`**
  Ensure dependencies are installed and autoload is rebuilt (`composer dumpautoload`). This error happens when the container binding for Prism is missing.

- **Rate limited by provider**
  The provider returned a rate limit error. Try another model/provider or wait.

- **No provider with configured API key**
  Set a key in `.env` or in `core/custom/config/ai.php`.

## 11) File Locations (Quick Map)
- Config: `core/custom/config/ai.php`
- Evo settings: `core/custom/config/cms/settings/evoAi.php`
- Agents: `core/custom/app/Ai/Agents/*`
- Tools: `core/custom/app/Ai/Tools/*`
- Stubs: `core/stubs/*`
