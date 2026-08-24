<?php

namespace App\Support;

use App\Models\ActivityLog;
use App\Models\ConsumerSurvey;
use App\Models\DtrSurvey;
use App\Models\FeederSurvey;
use App\Models\User;
use App\Models\WorkAssignment;
use Illuminate\Support\Facades\Storage;

class FeederSurveyDeleter
{
    /** Statuses an FE may self-delete (not approved). */
    public const OWN_DELETABLE_STATUSES = [
        FeederSurvey::STATUS_DRAFT,
        FeederSurvey::STATUS_SLD_PENDING,
        FeederSurvey::STATUS_PENDING_APPROVAL,
        FeederSurvey::STATUS_REJECTED,
    ];

    public static function canOwnDelete(FeederSurvey $row): bool
    {
        return in_array($row->status, self::OWN_DELETABLE_STATUSES, true);
    }

    /**
     * Hard-delete a non-approved feeder survey so the feeder can be surveyed again.
     * Cascades linked feeder-path DTR surveys (and their non-approved consumer surveys).
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    public static function deleteOwn(FeederSurvey $row, User $by): void
    {
        abort_unless(self::canOwnDelete($row), 422, 'Only pending / rejected feeder surveys can be deleted. Ask manager if already approved.');

        $id = (int) $row->id;
        $linked = DtrSurvey::query()->where('feeder_survey_id', $id)->get();

        $approvedLinked = $linked->whereIn('status', ['approved', 'completed']);
        abort_if(
            $approvedLinked->isNotEmpty(),
            422,
            'This feeder survey has approved DTR surveys. Ask a manager to delete it.'
        );

        foreach ($linked as $dtrSurvey) {
            $consumers = ConsumerSurvey::query()
                ->where('dtr_survey_id', $dtrSurvey->id)
                ->get();
            // Never silently remove manager-approved consumer verifications.
            abort_if(
                $consumers->contains(fn ($c) => in_array($c->status, ['approved', 'completed'], true)),
                422,
                'Linked DTR has approved consumer surveys. Ask a manager to delete.'
            );
            DtrSurveyDeleter::delete($dtrSurvey, $by);
        }

        self::purgeFeederAssets($row);

        // SLD submit marks assignment done — reopen so FE can survey this feeder again.
        if ($row->feeder_id && $row->surveyor_id) {
            WorkAssignment::query()
                ->where('assigned_to', (int) $row->surveyor_id)
                ->where('feeder_id', (int) $row->feeder_id)
                ->where('status', WorkAssignment::STATUS_DONE)
                ->update(['status' => WorkAssignment::STATUS_OPEN]);
        }

        ActivityLog::record('feeder_survey.own_deleted', $row, [
            'by' => $by->id,
            'feeder_id' => $row->feeder_id,
            'feeder_code' => $row->feeder_code,
            'status' => $row->status,
            'linked_dtrs_removed' => $linked->count(),
        ]);

        $row->delete();
    }

    /**
     * Manager / admin hard-delete (any status). Cascades linked feeder-path DTR surveys.
     */
    public static function deleteManager(FeederSurvey $row, User $by): void
    {
        $id = (int) $row->id;
        $linked = DtrSurvey::query()->where('feeder_survey_id', $id)->get();

        foreach ($linked as $dtrSurvey) {
            DtrSurveyDeleter::delete($dtrSurvey, $by);
        }

        self::purgeFeederAssets($row);

        ActivityLog::record('feeder_survey.manager_deleted', $row, [
            'by' => $by->id,
            'feeder_id' => $row->feeder_id,
            'feeder_code' => $row->feeder_code,
            'status' => $row->status,
            'linked_dtrs_removed' => $linked->count(),
        ]);

        $row->delete();
    }

    private static function purgeFeederAssets(FeederSurvey $row): void
    {
        self::deleteDiskPath($row->new_meter_photo);
        self::deleteDiskPath($row->sld_photo);

        foreach ($row->sldPhotos()->get() as $photo) {
            self::deleteDiskPath($photo->path);
            $photo->delete();
        }
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
