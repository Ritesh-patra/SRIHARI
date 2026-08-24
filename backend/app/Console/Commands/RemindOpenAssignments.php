<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\WorkAssignment;
use Illuminate\Console\Command;

/**
 * Daily reminder: active feeder assignments stay until SLD done or manager closes.
 */
class RemindOpenAssignments extends Command
{
    protected $signature = 'assignments:remind-open';

    protected $description = 'Notify field executives about open/started feeder assignments still pending';

    public function handle(): int
    {
        $active = WorkAssignment::query()
            ->with(['feeder:id,code,name', 'assignee:id,name'])
            ->whereIn('status', WorkAssignment::ACTIVE_STATUSES)
            ->whereNotNull('assigned_to')
            ->get()
            ->groupBy('assigned_to');

        $sent = 0;

        foreach ($active as $userId => $rows) {
            $count = $rows->count();
            $names = $rows->take(5)->map(function (WorkAssignment $a) {
                $f = $a->feeder;

                return $f ? trim(($f->name ?: 'Feeder').' ('.($f->code ?: $f->id).')') : 'Feeder #'.$a->feeder_id;
            })->implode(', ');
            $extra = $count > 5 ? ' +'.($count - 5).' more' : '';

            AppNotification::notifyUser(
                (int) $userId,
                'Pending feeder assignment(s)',
                "You still have {$count} assigned feeder(s) to complete (SLD). {$names}{$extra}",
                null,
                WorkAssignment::class,
                (int) $rows->first()->id
            );
            $sent++;
        }

        $this->info("Reminders sent to {$sent} field executive(s).");

        return self::SUCCESS;
    }
}
