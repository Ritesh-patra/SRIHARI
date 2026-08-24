<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\ConsumerSurvey;
use App\Models\User;
use App\Support\ConsumerSurveyApproval;
use App\Support\ConsumerSurveyDeleter;
use Illuminate\Http\Request;

class ConsumerSurveyApprovalController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canApproveConsumerSurveys(), 403, 'Consumer survey approval is not enabled for this account.');

        if (! $request->filled('status')) {
            $request->merge(['status' => 'pending_approval']);
        }

        $query = ConsumerSurveyApproval::applyFilters(
            ConsumerSurveyApproval::baseQuery($user),
            $request
        )->latest('consumer_surveys.surveyed_at');

        $perPage = min(200, max(20, $request->integer('per_page', 50)));
        $page = $query->paginate($perPage);

        $data = collect($page->items())->map(fn (ConsumerSurvey $row) => ConsumerSurveyApproval::apiRow($row))->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
            'can_approve' => true,
            'can_delete' => true,
        ]);
    }

    public function show(Request $request, ConsumerSurvey $consumerSurvey)
    {
        $user = $request->user();
        abort_unless($user->canApproveConsumerSurveys(), 403);
        abort_unless($this->canViewRow($user, $consumerSurvey), 403);

        $consumerSurvey->load([
            'surveyor:id,name,email',
            'reviewer:id,name',
            'pole.previousPole:id,pole_no',
            'dtr.feeder.substation.zone.division.circle.region',
        ]);

        return response()->json([
            'survey' => ConsumerSurveyApproval::apiRow($consumerSurvey),
            'can_approve' => $consumerSurvey->status === 'pending_approval',
            'can_delete' => true,
        ]);
    }

    public function bulkAction(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canApproveConsumerSurveys(), 403, 'Consumer survey approval is not enabled for this account.');

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:consumer_surveys,id'],
            'action' => ['required', 'in:approve,reject,delete'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['action'] === 'reject' && trim((string) ($data['remark'] ?? '')) === '') {
            return response()->json([
                'message' => 'Remark is required to reject.',
                'errors' => ['remark' => ['Remark is required to reject.']],
            ], 422);
        }

        $updated = 0;
        $skipped = 0;

        foreach ($data['ids'] as $id) {
            $row = ConsumerSurvey::with('dtrSurvey')->find($id);
            if (! $row || ! $this->canViewRow($user, $row)) {
                $skipped++;

                continue;
            }

            if ($data['action'] === 'delete') {
                $surveyorId = $row->surveyor_id;
                $label = $row->consumer_name ?: $row->ivrs;
                ConsumerSurveyDeleter::delete($row, $user);
                if ($surveyorId) {
                    AppNotification::notifyUser(
                        (int) $surveyorId,
                        'Consumer survey deleted',
                        'Your consumer survey for '.($label ?: '#').' was removed by reviewer.',
                        null,
                        ConsumerSurvey::class,
                        (int) $id
                    );
                }
                $updated++;

                continue;
            }

            if ($row->status !== 'pending_approval') {
                $skipped++;

                continue;
            }

            if ($data['action'] === 'approve') {
                $row->status = 'approved';
                $row->review_remarks = $data['remark'] ?? null;
            } else {
                $row->status = 'rejected';
                $row->review_remarks = trim((string) $data['remark']);
            }
            $row->reviewed_at = now();
            $row->reviewed_by = $user->id;
            $row->save();

            ActivityLog::record(
                $data['action'] === 'approve' ? 'consumer.survey_approved' : 'consumer.survey_rejected',
                $row,
                ['by' => $user->id]
            );

            if ($row->surveyor_id) {
                AppNotification::notifyUser(
                    (int) $row->surveyor_id,
                    $data['action'] === 'approve' ? 'Consumer survey approved' : 'Consumer survey rejected',
                    $data['action'] === 'approve'
                        ? 'Your consumer survey for '.($row->consumer_name ?: $row->ivrs).' was approved.'
                        : 'Rejected: '.$row->review_remarks,
                    null,
                    ConsumerSurvey::class,
                    (int) $row->id
                );
            }

            $updated++;
        }

        $message = match ($data['action']) {
            'approve' => "Approved {$updated} survey(s).",
            'reject' => "Rejected {$updated} survey(s).",
            'delete' => "Deleted {$updated} survey(s).",
        };

        return response()->json([
            'message' => $message,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    private function canViewRow(User $user, ConsumerSurvey $row): bool
    {
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        if (! $row->relationLoaded('dtrSurvey')) {
            $row->load('dtrSurvey');
        }

        if (! $row->dtrSurvey) {
            return (int) $row->surveyor_id === (int) $user->id;
        }

        return \App\Support\SurveyScope::canView($user, $row->dtrSurvey);
    }
}
