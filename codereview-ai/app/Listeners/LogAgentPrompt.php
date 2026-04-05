<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Events\AgentPrompted;

class LogAgentPrompt
{
    public function handle(AgentPrompted $event): void
    {
        $usage = $event->response->usage;

        Log::info('Agent prompted', [
            'agent' => get_class($event->prompt->agent),
            'prompt_tokens' => $usage->promptTokens,
            'completion_tokens' => $usage->completionTokens,
        ]);
    }
}
