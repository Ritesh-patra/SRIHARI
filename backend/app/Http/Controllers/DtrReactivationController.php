<?php

namespace App\Http\Controllers;

use App\Models\DtrReactivationRequest;
use App\Models\DtrSurvey;
use App\Support\DtrConsumerReopen;
use App\Support\SurveyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class DtrReactivationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canApproveSurveys() || $user->isAdmin(), 403);

        if (! Schema::hasTable('dtr_reactivation_requests')) {
            return view('dtr-reactivation.index', [
                'requests' => new \Illuminate\Pagination\LengthAwarePaginator([], 0, 40),
                'status' => 'pending',
                'counts' => ['pending' => 0, 'approved' => 0, 'rejected' => 0],
                'tableMissing' => true,
            ]);
        }

        $status = $request->input('status', 'pending');
        if (! in_array($status, ['pending', 'approved', 'rejected', 'all'], true)) {
            $status = 'pending';
        }

        $query = DtrReactivationRequest::query()
            ->with([
                'requester:id,name',
                'reviewer:id,name',
                'dtrSurvey.surveyor:id,name',
                'dtrSurvey.feeder:id,code,name',
                'dtrSurvey.dtr:id,code,name',
            ])
            ->latest('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if (! $user->isAdmin()) {
            $surveyIds = SurveyScope::apply(DtrSurvey::query(), $user)->pluck('id');
            $query->whereIn('dtr_survey_id', $surveyIds);
        }

        $requests = $query->paginate(40)->withQueryString();

        $countBase = DtrReactivationRequest::query();
        if (! $user->isAdmin()) {
            $surveyIds = SurveyScope::apply(DtrSurvey::query(), $user)->pluck('id');
            $countBase->whereIn('dtr_survey_id', $surveyIds);
        }

        $counts = [
            'pending' => (clone $countBase)->where('status', DtrReactivationRequest::STATUS_PENDING)->count(),
            'approved' => (clone $countBase)->where('status', DtrReactivationRequest::STATUS_APPROVED)->count(),
            'rejected' => (clone $countBase)->where('status', DtrReactivationRequest::STATUS_REJECTED)->count(),
        ];

        return view('dtr-reactivation.index', compact('requests', 'status', 'counts') + [
            'tableMissing' => false,
        ]);
    }

    public function approve(Request $request, DtrReactivationRequest $reactivation)
    {
        $user = $request->user();
        abort_unless($user->canApproveSurveys() || $user->isAdmin(), 403);
        abort_unless($reactivation->isPending(), 422, 'Only pending requests can be approved.');

        $survey = $reactivation->dtrSurvey;
        abort_unless($survey, 404);
        if (! $user->isAdmin()) {
            abort_unless(SurveyScope::canView($user, $survey), 403);
        }

        $data = $request->validate([
            'review_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        DtrConsumerReopen::reopen(
            $survey,
            $user,
            $data['review_remarks'] ?? null,
            $reactivation,
            'approval'
        );

        return back()->with('success', 'DTR re-activated. Field team can survey more consumers.');
    }

    public function reject(Request $request, DtrReactivationRequest $reactivation)
    {
        $user = $request->user();
        abort_unless($user->canApproveSurveys() || $user->isAdmin(), 403);
        abort_unless($reactivation->isPending(), 422, 'Only pending requests can be rejected.');

        $survey = $reactivation->dtrSurvey;
        if ($survey && ! $user->isAdmin()) {
            abort_unless(SurveyScope::canView($user, $survey), 403);
        }

        $data = $request->validate([
            'review_remarks' => ['required', 'string', 'max:1000'],
        ]);

        DtrConsumerReopen::rejectRequest($reactivation, $user, trim($data['review_remarks']));

        return back()->with('success', 'Re-activation request rejected. DTR stays finished.');
    }

    /** Direct reopen from DTR Surveys browse (no prior FE request required). */
    public function reopenSurvey(Request $request, DtrSurvey $survey)
    {
        $user = $request->user();
        abort_unless($user->canApproveSurveys() || $user->isAdmin(), 403);
        if (! $user->isAdmin()) {
            abort_unless(SurveyScope::canView($user, $survey), 403);
        }

        $data = $request->validate([
            'review_remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        DtrConsumerReopen::reopen(
            $survey,
            $user,
            $data['review_remarks'] ?? 'Reopened from DTR Surveys browse.',
            null,
            'direct'
        );

        return back()->with('success', 'DTR consumer survey reopened. FE can survey more consumers.');
    }
}
