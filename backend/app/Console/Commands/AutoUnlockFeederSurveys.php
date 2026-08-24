<?php

namespace App\Console\Commands;

use App\Models\FeederSurvey;
use Illuminate\Console\Command;

class AutoUnlockFeederSurveys extends Command
{
    protected $signature = 'feeder-surveys:auto-unlock';

    protected $description = 'Clear feeder survey locks older than 2 days so surveyors can be reassigned';

    public function handle(): int
    {
        $cutoff = now()->subDays(FeederSurvey::LOCK_AUTO_UNLOCK_DAYS);
        $count = FeederSurvey::query()
            ->whereNotNull('locked_at')
            ->where('locked_at', '<=', $cutoff)
            ->update(['locked_at' => null]);

        $this->info("Auto-unlocked {$count} feeder survey(s) (locked_at <= {$cutoff->toDateTimeString()}).");

        return self::SUCCESS;
    }
}
