<p align="center">
<a href="https://packagist.org/packages/evolution-cms/evo-ai"><img src="https://img.shields.io/packagist/dt/evolution-cms/evo-ai" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/evolution-cms/evo-ai"><img src="https://img.shields.io/packagist/v/evolution-cms/evo-ai" alt="Latest Stable Version"></a>
<img src="https://img.shields.io/badge/license-MIT-green" alt="License">
</p>

# evo-ai для Evolution CMS

evo-ai — це бібліотека Evolution CMS, що інтегрує можливості Laravel AI SDK в Evolution-based проєкти. Пакет додає Evo‑native publish конфігів, мінімальні shims для відсутніх `Illuminate\Foundation` класів і sTask‑first черги.

Якщо потрібен швидкий старт і приклади — використовуйте цей README. Повні деталі дивись у `DOCS.uk.md` (UA) або `DOCS.md` (EN).

## Вимоги
- Evolution CMS 3.5.2+
- PHP 8.3+
- Composer 2.2+

Опційно:
- sTask для асинхронних задач (constraint пакета: `^1.0`)

## Швидкий старт
З директорії `core`:

```bash
cd core
php artisan package:installrequire evolution-cms/evo-ai "*"
php artisan migrate
```

Publish конфігів і stubs (опційно, авто‑publish увімкнений):

```bash
php artisan vendor:publish --provider="EvolutionCMS\\evoAi\\evoAiServiceProvider" --tag=evoai-config
php artisan vendor:publish --provider="EvolutionCMS\\evoAi\\evoAiServiceProvider" --tag=evoai-ai-config
php artisan vendor:publish --provider="EvolutionCMS\\evoAi\\evoAiServiceProvider" --tag=evoai-stubs
```

Додай ключ провайдера у `.env` або `core/custom/config/ai.php`:

```
OPENAI_API_KEY=...
```

Запусти smoke‑тест:

```bash
php artisan ai:test
```

Для локального Ollama:

```bash
php artisan ai:test --provider=ollama
```

## Мінімальний приклад
```php
use App\Ai\Agents\SupportAgent;

$agent = new SupportAgent();
$response = $agent->prompt('Hello from Evo');

echo $response->text;
```

## Черги (sTask‑first)
- sTask — основний бекенд; `sync` — fallback.
- evoAi не реалізує Laravel Queue, лише сумісне dispatching для SDK.

Запуск воркера:

```bash
php artisan stask:worker
```

sTask UI‑воркери (для тесту):
- `evoai_smoke` — фіксований prompt
- `evoai_prompt` — кастомний prompt з віджета

## AI Service Account (рольова модель)
AI працює як звичайний manager user з роллю **AI** (створюється автоматично). Роль read‑only за замовчуванням; щоб AI могла зберігати/публікувати — підніміть права вручну (наприклад, до Publisher).

Приклад налаштувань у `core/custom/config/cms/settings/evoAi.php`:
```
ai_actor_mode: service
ai_actor_email: ai@your-host
ai_actor_autocreate: true
ai_actor_block_login: true
ai_actor_role: AI
ai_actor_role_autocreate: true
```

Якщо потрібен доступ до інтерфейсів пакетів, видай ролі **AI** permissions `stask` та/або `sapi` (у sApi це група `sPackages`).

## Генератори Artisan
```bash
php artisan make:agent SalesCoach
php artisan make:agent SalesCoach --structured
php artisan make:tool RandomNumberGenerator
```

Класи створюються в `core/custom/app/Ai/...`. Якщо автолоад не підхопився — `composer dumpautoload`.

## Детальніше
Дивись `DOCS.uk.md` для повного опису конфігів, правил ідентичності, черг і розширених можливостей SDK.
