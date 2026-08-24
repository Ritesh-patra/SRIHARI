<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\Circle;
use App\Models\ConsumerSurvey;
use App\Models\Division;
use App\Models\Region;
use App\Models\User;
use App\Models\Zone;
use App\Support\ConsumerSurveyApproval;
use App\Support\ConsumerSurveyDeleter;
use App\Support\SimpleXlsxExporter;
use Illuminate\Http\Request;

class ConsumerSurveyApprovalController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->canApproveConsumerSurveys(), 403);

        $filters = [
            'status' => $request->input('status', 'pending_approval'),
            'region_id' => $request->input('region_id'),
            'circle_id' => $request->input('circle_id'),
            'division_id' => $request->input('division_id'),
            'zone_id' => $request->input('zone_id'),
            'phase' => $request->input('phase', 'all'),
            'from' => $request->input('from', now()->subDays(7)->toDateString()),
            'to' => $request->input('to', now()->toDateString()),
            'ivrs' => $request->input('ivrs'),
            'dtr_code' => $request->input('dtr_code'),
            'surveyor_id' => $request->input('surveyor_id'),
        ];

        $request->merge($filters);

        $viewed = $request->boolean('view') || $request->boolean('download');

        if ($request->boolean('download')) {
            return $this->downloadExcel($request);
        }

        $query = ConsumerSurveyApproval::applyFilters(
            ConsumerSurveyApproval::baseQuery($request->user()),
            $request
        )->latest('consumer_surveys.surveyed_at');

        $surveys = $viewed
            ? $query->paginate(50)->withQueryString()
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50);

        $regions = Region::orderBy('name')->get(['id', 'name']);
        $circles = Circle::when($filters['region_id'], fn ($q) => $q->where('region_id', $filters['region_id']))
            ->orderBy('name')->get(['id', 'name', 'region_id']);
        $divisions = Division::when($filters['circle_id'], fn ($q) => $q->where('circle_id', $filters['circle_id']))
            ->orderBy('name')->get(['id', 'name', 'circle_id']);
        $zones = Zone::when($filters['division_id'], fn ($q) => $q->where('division_id', $filters['division_id']))
            ->orderBy('name')->get(['id', 'name', 'division_id']);
        $surveyors = User::whereIn('role', [User::ROLE_FIELD_EXECUTIVE, 'surveyor'])
            ->orderBy('name')->get(['id', 'name']);

        return view('consumer-approval.index', compact(
            'surveys', 'filters', 'regions', 'circles', 'divisions', 'zones', 'surveyors', 'viewed'
        ));
    }

    /**
     * Excel download for shared hosting: no ZipArchive required, CSV fallback, no photo blobs.
     */
    private function downloadExcel(Request $request)
    {
        @ini_set('memory_limit', '256M');
        @set_time_limit(120);

        try {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            $query = ConsumerSurveyApproval::applyExportOrder(
                ConsumerSurveyApproval::applyFilters(
                    ConsumerSurveyApproval::exportQuery($request->user()),
                    $request
                )
            );

            $rows = ConsumerSurveyApproval::excelRows(
                $query->limit(5000)->get()
            );

            // SimpleXlsxExporter already falls back to CSV if XLSX build fails.
            return SimpleXlsxExporter::download(
                'consumer_survey_approval_'.now()->format('Ymd_His').'.xlsx',
                ConsumerSurveyApproval::excelHeaders(),
                $rows
            );
        } catch (\Throwable $e) {
            report($e);

            $message = 'Download failed. Please try a smaller date range or contact support.';
            if (config('app.debug')) {
                $message .= "\n\n".$e->getMessage();
            }

            return response($message, 500, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]);
        }
    }

    public function bulkAction(Request $request)
    {
        abort_unless($request->user()->canApproveConsumerSurveys(), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:consumer_surveys,id'],
            'action' => ['required', 'in:approve,reject,delete'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['action'] === 'reject' && trim((string) ($data['remark'] ?? '')) === '') {
            return back()->withErrors(['remark' => 'Remark is required to reject.'])->withInput();
        }

        $user = $request->user();
        $updated = 0;

        foreach ($data['ids'] as $id) {
            $row = ConsumerSurvey::with('dtrSurvey')->find($id);
            if (! $row) {
                continue;
            }
            if ($row->dtrSurvey && ! \App\Support\SurveyScope::canView($user, $row->dtrSurvey) && ! $user->isAdmin()) {
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
                $row
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

        $label = match ($data['action']) {
            'approve' => 'Approved',
            'reject' => 'Rejected',
            'delete' => 'Deleted',
        };

        return back()->with('success', "{$label} {$updated} survey(s).");
    }
}
