<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\DtrReactivationRequest;
use App\Models\DtrSurvey;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Clears Finish-DTR consumer lock (consumer_survey_completed_at)
 * so field teams can survey more consumers on the same DTR survey.
 */
class DtrConsumerReopen
{
    public static function tableReady(): bool
    {
        return Schema::hasTable('dtr_reactivation_requests');
    }

    public static function canReopen(DtrSurvey $survey): bool
    {
        return $survey->consumer_survey_completed_at !== null
            && in_array($survey->status, ['approved', 'pending_approval', 'completed'], true);
    }

    /**
     * Clear finish lock. Optionally marks a pending reactivation request approved.
     */
    public static function reopen(
        DtrSurvey $survey,
        User $by,
        ?string $remarks = null,
        ?DtrReactivationRequest $request = null,
        string $source = 'admin'
    ): DtrSurvey {
        return DB::transaction(function () use ($survey, $by, $remarks, $request, $source) {
            $locked = DtrSurvey::query()->lockForUpdate()->findOrFail($survey->id);

            if (! self::canReopen($locked)) {
                abort(422, 'This DTR is not finished (or not eligible) for consumer re-activation.');
            }

            $locked->consumer_survey_completed_at = null;
            $locked->save();

            if ($request && $request->isPending()) {
                $request->forceFill([
                    'status' => DtrReactivationRequest::STATUS_APPROVED,
                    'reviewed_by' => $by->id,
                    'reviewed_at' => now(),
                    'review_remarks' => $remarks,
                ])->save();
            } elseif (self::tableReady()) {
                // Auto-close any other pending requests for this survey when admin reopens directly.
                DtrReactivationRequest::query()
                    ->where('dtr_survey_id', $locked->id)
                    ->where('status', DtrReactivationRequest::STATUS_PENDING)
                    ->update([
                        'status' => DtrReactivationRequest::STATUS_APPROVED,
                        'reviewed_by' => $by->id,
                        'reviewed_at' => now(),
                        'review_remarks' => $remarks ?: 'Reopened directly by reviewer.',
                        'updated_at' => now(),
                    ]);
            }

            ActivityLog::record('consumer.dtr_reactivated', $locked, [
                'by' => $by->id,
                'source' => $source,
                'request_id' => $request?->id,
                'remarks' => $remarks,
            ]);

            $notifyUserId = $request?->requested_by ?: $locked->surveyor_id;
            if ($notifyUserId) {
                AppNotification::notifyUser(
                    (int) $notifyUserId,
                    'DTR re-activated for consumer survey',
                    'DTR '.($locked->dtr_code ?: '#'.$locked->id).' is open again. You can survey more consumers, then Finish DTR when done.',
                    null,
                    DtrSurvey::class,
                    (int) $locked->id
                );
            }

            return $locked->fresh(['feeder', 'dtr', 'surveyor']);
        });
    }

    public static function rejectRequest(
        DtrReactivationRequest $request,
        User $by,
        string $remarks
    ): DtrReactivationRequest {
        abort_unless($request->isPending(), 422, 'Only pending requests can be rejected.');

        $request->forceFill([
            'status' => DtrReactivationRequest::STATUS_REJECTED,
            'reviewed_by' => $by->id,
            'reviewed_at' => now(),
            'review_remarks' => $remarks,
        ])->save();

        ActivityLog::record('consumer.dtr_reactivation_rejected', $request->dtrSurvey ?? $request, [
            'by' => $by->id,
            'request_id' => $request->id,
            'remarks' => $remarks,
        ]);

        if ($request->requested_by) {
            $survey = $request->dtrSurvey;
            AppNotification::notifyUser(
                (int) $request->requested_by,
                'DTR re-activation rejected',
                'Request for DTR '.($survey?->dtr_code ?: '#'.$request->dtr_survey_id).' was rejected: '.$remarks,
                null,
                DtrReactivationRequest::class,
                (int) $request->id
            );
        }

        return $request->fresh(['dtrSurvey', 'requester', 'reviewer']);
    }
}
