<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\ConsumerSurvey;
use App\Models\DtrSurvey;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class DtrSurveyDeleter
{
    /**
     * Hard-delete a DTR survey: consumer surveys (photos) first, then DTR photos + row.
     */
    public static function delete(DtrSurvey $row, User $by): void
    {
        $consumers = ConsumerSurvey::query()
            ->where('dtr_survey_id', $row->id)
            ->get();

        foreach ($consumers as $consumer) {
            ConsumerSurveyDeleter::delete($consumer, $by);
        }

        foreach (['dtr_overall_photo', 'smart_meter_photo', 'ct_ratio_photo'] as $field) {
            self::deleteDiskPath($row->{$field});
        }

        ActivityLog::record('survey.manager_deleted', $row, [
            'by' => $by->id,
            'dtr_id' => $row->dtr_id,
            'dtr_code' => $row->dtr_code,
            'status' => $row->status,
            'consumers_removed' => $consumers->count(),
        ]);

        $row->delete();
    }

    private static function deleteDiskPath(?string $path): void
    {
        if (! $path) {
            return;
        }
        $relative = ltrim((string) $path, '/');
        if (str_starts_with($relative, 'storage/')) {
            $relative = substr($relative, strlen('storage/'));
        }
        try {
            if (Storage::disk('public')->exists($relative)) {
                Storage::disk('public')->delete($relative);
            }
        } catch (\Throwable) {
            // best-effort photo cleanup
        }
    }
}
