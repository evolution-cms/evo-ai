<?php

namespace EvolutionCMS\evoAi\sTask;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Seiger\sTask\Models\sTaskModel;
use Seiger\sTask\Workers\BaseWorker;

class AiPromptWorker extends BaseWorker
{
    public function identifier(): string
    {
        return 'evoai_prompt';
    }

    public function scope(): string
    {
        return 'evoAi';
    }

    public function icon(): string
    {
        return '<i class="fa fa-comment-dots"></i>';
    }

    public function title(): string
    {
        return 'evoAi Prompt';
    }

    public function description(): string
    {
        return 'Send a custom prompt and show the response.';
    }

    public function renderWidget(): string
    {
        return view('evoAi::widgets.promptWorkerWidget', [
            'identifier' => $this->identifier(),
            'description' => $this->description(),
        ])->render();
    }

    public function taskPrompt(sTaskModel $task, array $opt = []): void
    {
        $prompt = trim((string)($opt['prompt'] ?? ''));
        $provider = $opt['provider'] ?? null;
        $model = $opt['model'] ?? null;

        if ($prompt === '') {
            $prompt = 'Reply with ok.';
        }

        $task->markAsRunning();
        $this->pushProgress($task, [
            'status' => $task->status_text,
            'progress' => 10,
            'message' => 'Prompt: ' . $prompt,
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
