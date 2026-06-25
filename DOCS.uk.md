# DOCS — evo-ai (Evolution CMS + Laravel AI SDK)

Це детальний, структурований гайд для інтеграції Laravel AI SDK в Evolution CMS через пакет evo-ai. Він розширює README поясненнями, конфігами та операційними деталями.

## 1) Огляд
- evo-ai — thin‑wrapper над `laravel/ai` у стилі Evo.
- `illuminate/foundation` не потрібен: є мінімальні shims для сумісності SDK.
- sTask — primary бекенд черг; `sync` — fallback.
- AI‑дії виконуються від імені сервісного користувача з роллю **AI**.

## 2) Вимоги
- Evolution CMS 3.5.2+
- PHP 8.3+
- Composer 2.2+

Опційно:
- sTask для асинхронного виконання (constraint пакета: `^1.0`)

## 3) Встановлення
```bash
cd core
php artisan package:installrequire evolution-cms/evo-ai "*"
```

## 4) Publish та міграції
Auto‑publish увімкнений, але можна примусово:

```bash
php artisan vendor:publish --provider="EvolutionCMS\\evoAi\\evoAiServiceProvider" --tag=evoai-config
php artisan vendor:publish --provider="EvolutionCMS\\evoAi\\evoAiServiceProvider" --tag=evoai-ai-config
php artisan vendor:publish --provider="EvolutionCMS\\evoAi\\evoAiServiceProvider" --tag=evoai-stubs
```

Міграції:
```bash
php artisan migrate
```

## 5) Довідник конфігів

### 5.1 `core/custom/config/ai.php`
Це конфіг Laravel AI SDK. Мінімальний приклад:

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

Нотатки:
- `default_*` визначають дефолтні провайдери для images/audio/etc.
- Ключі можуть бути в `.env` або в конфігу.

### 5.2 `core/custom/config/cms/settings/evoAi.php`
Налаштування інтеграції Evo:

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

Пояснення:
- `queue_driver`: `stask` (primary) або `sync` (fallback).
- `queue_failover`: використовується, якщо `stask` недоступний.
- `context`: `mgr|web|auto` (контекст виконання).
- `ai_actor_*`: керує сервісним AI‑користувачем (manager user + роль **AI**).

## 6) Ідентичність та безпека
- `conversation_user_id`: реальний користувач у історії. Якщо невідомий (CLI/без сесії) — fallback = `1`.
- `actor_user_id`: AI service account, якщо режим увімкнено.
- `initiated_by_user_id`: хто ініціював дію (для аудиту).

Правила:
- Write‑дії виконуються лише через manager ACL, без прямих записів у БД.
- AI‑юзер — звичайний manager user з роллю **AI** (ApiUser‑style, без нових колонок).
- Інтерактивний логін заблокований (`user_attributes.blocked=1`).
- Роль **AI** read‑only за замовчуванням; підвищення прав — вручну.
- Для доступу до інтерфейсів пакетів видати permissions `stask` та/або `sapi` (група `sPackages`).

## 7) Черги та sTask
- sTask — основний бекенд. `sync` — fallback для середовищ без sTask.
- evoAi не реалізує Laravel Queue; лише сумісне dispatching для SDK.

### 7.1 sTask UI‑воркери
Видимі воркери:
- `evoai_smoke` — фіксований prompt
- `evoai_prompt` — кастомний prompt з віджета

Внутрішній воркер:
- `evoai` — використовується queued SDK‑job’ами (не для ручного запуску)

Запуск воркера:
```bash
php artisan stask:worker
```

### 7.2 Контракт payload (sTask)
Queued‑payload містить:
- `job_class`
- `job_payload` (серіалізація)
- `attempts`, `max_attempts`
- `actor_user_id`, `conversation_user_id`, `initiated_by_user_id`
- `context` (`mgr|web|cli`)

`then/catch` — лише мінімальні метадані, повна Laravel‑сумісність не гарантується.

## 8) Artisan команди

### 8.1 Smoke‑тест
```bash
php artisan ai:test
```
Опції:
- `--provider=openai|ollama|...`
- `--model=...`
- `--prompt="..."`

### 8.2 Генератори
```bash
php artisan make:agent SalesCoach
php artisan make:agent SalesCoach --structured
php artisan make:tool RandomNumberGenerator
```

Згенеровані класи:
- `core/custom/app/Ai/Agents/*`
- `core/custom/app/Ai/Tools/*`

Якщо автолоад не оновився:
```bash
composer dumpautoload
```

## 9) Приклади

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

### Зображення
```php
use Laravel\Ai\Image;

$image = Image::of('A donut on the table')->generate();
$path = $image->storePubliclyAs('ai/donut.png');
```

### Аудіо (TTS)
```php
use Laravel\Ai\Audio;

$audio = Audio::of('Hello from Evo')->generate();
$path = $audio->storePubliclyAs('ai/hello.mp3');
```

### Транскрипція (STT)
```php
use Laravel\Ai\Transcription;

$text = Transcription::fromPath('/path/to/audio.mp3')->generate();
```

### Ембеддінги
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

### Файли та вкладення
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
  Перевір залежності та перезбери автолоад (`composer dumpautoload`). Помилка виникає при відсутньому Prism‑binding.

- **Rate limited by provider**
  Це ліміт провайдера. Спробуй інший модель/провайдер або зачекай.

- **No provider with configured API key**
  Додай ключ у `.env` або `core/custom/config/ai.php`.

## 11) Карта файлів
- Конфіг: `core/custom/config/ai.php`
- Evo‑налаштування: `core/custom/config/cms/settings/evoAi.php`
- Агенти: `core/custom/app/Ai/Agents/*`
- Тулзи: `core/custom/app/Ai/Tools/*`
- Stubs: `core/stubs/*`
