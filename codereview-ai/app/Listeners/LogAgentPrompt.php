<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Log;
use Laravel\Ai\Events\AgentPrompted;

class LogAgentPrompt
{
    public function handle(AgentPrompted $event): void
    {
        Log::info('Agent prompted', [
            'agent' => get_class($event->agent),
            'tokens' => $event->usage->totalTokens,
            'duration_ms' => $event->durationMs,
        ]);
    }
}
