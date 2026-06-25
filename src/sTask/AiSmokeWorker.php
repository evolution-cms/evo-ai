<?php

namespace EvolutionCMS\evoAi\sTask;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Workers\BaseWorker;

class AiSmokeWorker extends BaseWorker
{
    public function identifier(): string
    {
        return 'evoai_smoke';
    }

    public function scope(): string
    {
        return 'evoAi';
    }

    public function icon(): string
    {
        return '<i class="fa fa-robot"></i>';
    }

    public function title(): string
    {
        return 'evoAi Smoke Test';
    }

    public function description(): string
    {
        return 'Runs a minimal AI prompt and prints the response.';
    }

    public function renderWidget(): string
    {
        return view('sTask::partials.defaultWorkerWidget', [
            'identifier' => $this->identifier(),
            'description' => $this->description(),
        ])->render();
    }

    public function taskMake(sTaskModel $task, array $opt = []): void
    {
        $prompt = (string)($opt['prompt'] ?? 'Reply with ok.');
        $provider = $opt['provider'] ?? null;
        $model = $opt['model'] ?? null;

        $task->markAsRunning();
        $this->pushProgress($task, [
            'status' => $task->status_text,
            'progress' => 10,
            'message' => 'Starting smoke test...',
        ]);

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
            $response = $agent->prompt($prompt, provider: $provider ?: null, model: $model ?: null);
            $text = is_string($response->text ?? null) ? $response->text : (string)$response;

            $this->pushProgress($task, [
                'status' => $task->status_text,
                'progress' => 80,
                'message' => 'AI: ' . $text,
            ]);

            $this->markFinished($task, null, 'Done');
        } catch (\Throwable $e) {
            $this->markFailed($task, 'AI error: ' . $e->getMessage());
        }
    }
}
