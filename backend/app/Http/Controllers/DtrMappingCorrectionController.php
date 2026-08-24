<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\Dtr;
use App\Models\DtrSurvey;
use App\Support\SurveyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DtrMappingCorrectionController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->canApproveSurveys() || $user->isAdmin(), 403);

        $status = $request->input('status', 'pending');
        if (! in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $query = DtrSurvey::query()
            ->with([
                'surveyor:id,name',
                'dtr:id,code,name,capacity_kva,feeder_id',
                                'masterFeeder:id,code,name,substation_id',
                'masterFeeder.substation:id,name',
                'reportedFeeder:id,code,name,substation_id',
                'reportedFeeder.substation:id,name',
                'mappingCorrectionReviewer:id,name',
            ])
            ->whereNotNull('mapping_correction_status')
            ->latest('id');

        if ($status !== 'all') {
            $query->where('mapping_correction_status', $status);
        }

        if (! $user->isAdmin()) {
            $query = SurveyScope::apply($query, $user);
        }

        $corrections = $query->paginate(40)->withQueryString();

        $counts = [
            'pending' => DtrSurvey::query()->where('mapping_correction_status', DtrSurvey::MAPPING_PENDING)->count(),
            'approved' => DtrSurvey::query()->where('mapping_correction_status', DtrSurvey::MAPPING_APPROVED)->count(),
            'rejected' => DtrSurvey::query()->where('mapping_correction_status', DtrSurvey::MAPPING_REJECTED)->count(),
        ];

        return view('dtr-mapping-corrections.index', compact('corrections', 'status', 'counts'));
    }

    public function approve(Request $request, DtrSurvey $survey)
    {
        $user = Auth::user();
        abort_unless($user->canApproveSurveys() || $user->isAdmin(), 403);
        abort_unless(
            $survey->mapping_correction_status === DtrSurvey::MAPPING_PENDING,
            422,
            'Only pending mapping corrections can be approved.'
        );
        if (! $user->isAdmin()) {
            abort_unless(SurveyScope::canView($user, $survey), 403);
        }

        $data = $request->validate([
            'review_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $reportedFeederId = (int) ($survey->reported_feeder_id ?: $survey->feeder_id);
        abort_unless($reportedFeederId > 0, 422, 'Reported feeder is missing.');
        abort_unless($survey->dtr_id, 422, 'DTR is missing on this survey.');

        DB::transaction(function () use ($survey, $user, $data, $reportedFeederId) {
            $dtr = Dtr::query()->lockForUpdate()->findOrFail($survey->dtr_id);
            $fromFeederId = (int) $dtr->feeder_id;

            if ($fromFeederId !== $reportedFeederId) {
                $dtr->feeder_id = $reportedFeederId;
                $dtr->save();
            }

            $survey->forceFill([
                'mapping_correction_status' => DtrSurvey::MAPPING_APPROVED,
                'mapping_correction_remarks' => $data['review_remarks'] ?? $survey->mapping_correction_remarks,
                'mapping_correction_reviewed_at' => now(),
                'mapping_correction_reviewed_by' => $user->id,
                'master_feeder_id' => $survey->master_feeder_id ?: $fromFeederId,
                'reported_feeder_id' => $reportedFeederId,
            ])->save();

            ActivityLog::record('dtr.mapping_correction.approved', $survey, [
                'by' => $user->id,
                'dtr_id' => $dtr->id,
                'from_feeder_id' => $fromFeederId,
                'to_feeder_id' => $reportedFeederId,
            ]);
        });

        if ($survey->surveyor_id) {
            AppNotification::notifyUser(
                (int) $survey->surveyor_id,
                'DTR mapping correction approved',
                'DTR '.($survey->dtr_code ?: '').' remapped to reported feeder in master.',
                route('dtr-mapping-corrections.index', ['status' => 'approved']),
                DtrSurvey::class,
                (int) $survey->id
            );
        }

        return back()->with('success', 'Mapping correction approved. Master DTR feeder updated.');
    }

    public function reject(Request $request, DtrSurvey $survey)
    {
        $user = Auth::user();
        abort_unless($user->canApproveSurveys() || $user->isAdmin(), 403);
        abort_unless(
            $survey->mapping_correction_status === DtrSurvey::MAPPING_PENDING,
            422,
            'Only pending mapping corrections can be rejected.'
        );
        if (! $user->isAdmin()) {
            abort_unless(SurveyScope::canView($user, $survey), 403);
        }

        $data = $request->validate([
            'review_remarks' => ['required', 'string', 'max:1000'],
        ]);

        $survey->forceFill([
            'mapping_correction_status' => DtrSurvey::MAPPING_REJECTED,
            'mapping_correction_remarks' => $data['review_remarks'],
            'mapping_correction_reviewed_at' => now(),
            'mapping_correction_reviewed_by' => $user->id,
        ])->save();

        ActivityLog::record('dtr.mapping_correction.rejected', $survey, [
            'by' => $user->id,
            'reason' => $data['review_remarks'],
        ]);

        if ($survey->surveyor_id) {
            AppNotification::notifyUser(
                (int) $survey->surveyor_id,
                'DTR mapping correction rejected',
                'DTR '.($survey->dtr_code ?: '').' master feeder unchanged. Field survey kept. Reason: '.$data['review_remarks'],
                route('dtr-mapping-corrections.index', ['status' => 'rejected']),
                DtrSurvey::class,
                (int) $survey->id
            );
        }

        return back()->with('success', 'Mapping correction rejected. Master feeder unchanged; field survey retained.');
    }
}
