<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\ConsumerSurvey;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class ConsumerSurveyDeleter
{
    public static function delete(ConsumerSurvey $row, User $by): void
    {
        foreach (['meter_photo', 'premise_photo'] as $field) {
            $path = $row->{$field};
            if (! $path) {
                continue;
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

        ActivityLog::record('consumer.survey_deleted', $row, [
            'by' => $by->id,
            'ivrs' => $row->ivrs,
            'consumer_name' => $row->consumer_name,
            'dtr_id' => $row->dtr_id,
        ]);

        $row->delete();
    }
}
