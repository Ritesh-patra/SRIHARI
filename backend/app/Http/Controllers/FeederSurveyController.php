<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\Circle;
use App\Models\Division;
use App\Models\DtrSurvey;
use App\Models\FeederSurvey;
use App\Models\Region;
use App\Models\User;
use App\Models\Zone;
use App\Support\FeederSurveyBrowse;
use App\Support\FeederSurveyDeleter;
use App\Support\SimpleXlsxExporter;
use App\Support\SurveyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FeederSurveyController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        abort_unless($user->canApproveSurveys() || $user->isAdmin(), 403);

        $filters = [
            'status' => $request->input('status', 'all'),
            'region_id' => $request->input('region_id'),
            'circle_id' => $request->input('circle_id'),
            'division_id' => $request->input('division_id'),
            'zone_id' => $request->input('zone_id'),
            'from' => $request->input('from', now()->subDays(7)->toDateString()),
            'to' => $request->input('to', now()->toDateString()),
            'feeder_code' => $request->input('feeder_code'),
            'surveyor_id' => $request->input('surveyor_id'),
        ];

        $request->merge($filters);

        $viewed = $request->boolean('view') || $request->boolean('download');

        if ($request->boolean('download')) {
            return $this->downloadExcel($request);
        }

        $query = FeederSurveyBrowse::applyFilters(
            FeederSurveyBrowse::baseQuery($user),
            $request
        )->latest('feeder_surveys.surveyed_at');

        $surveys = $viewed
            ? $query->paginate(50)->withQueryString()
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50);

        if ($viewed) {
            $surveys->getCollection()->each(fn (FeederSurvey $s) => $s->releaseExpiredLock());
        }

        $regions = Region::orderBy('name')->get(['id', 'name']);
        $circles = Circle::when($filters['region_id'], fn ($q) => $q->where('region_id', $filters['region_id']))
            ->orderBy('name')->get(['id', 'name', 'region_id']);
        $divisions = Division::when($filters['circle_id'], fn ($q) => $q->where('circle_id', $filters['circle_id']))
            ->orderBy('name')->get(['id', 'name', 'circle_id']);
        $zones = Zone::when($filters['division_id'], fn ($q) => $q->where('division_id', $filters['division_id']))
            ->orderBy('name')->get(['id', 'name', 'division_id']);
        $surveyors = User::whereIn('role', [User::ROLE_FIELD_EXECUTIVE, 'surveyor'])
            ->orderBy('name')->get(['id', 'name']);

        return view('feeder-surveys.index', compact(
            'surveys', 'filters', 'regions', 'circles', 'divisions', 'zones', 'surveyors', 'viewed'
        ));
    }

    /**
     * Excel download for shared hosting: no ZipArchive required, CSV fallback.
     */
    private function downloadExcel(Request $request)
    {
        @ini_set('memory_limit', '256M');
        @set_time_limit(120);

        try {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            $query = FeederSurveyBrowse::applyFilters(
                FeederSurveyBrowse::exportQuery($request->user()),
                $request
            )->latest('feeder_surveys.surveyed_at');

            $rows = $query->limit(5000)->get()
                ->map(fn (FeederSurvey $r) => FeederSurveyBrowse::excelRow($r))
                ->all();

            return SimpleXlsxExporter::download(
                'feeder_surveys_'.now()->format('Ymd_His').'.xlsx',
                FeederSurveyBrowse::excelHeaders(),
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

    /** Legacy export route — same filters as index download. */
    public function export(Request $request)
    {
        $request->merge(['download' => '1']);

        return $this->downloadExcel($request);
    }

    /** Bulk hard-delete feeder surveys (admin / approver scope). */
    public function bulkDelete(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canApproveSurveys() || $user->isAdmin(), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:feeder_surveys,id'],
        ]);

        $deleted = 0;
        foreach ($data['ids'] as $id) {
            $row = FeederSurvey::find($id);
            if (! $row) {
                continue;
            }
            if (! SurveyScope::canView($user, $row) && ! $user->isAdmin()) {
                continue;
            }
            FeederSurveyDeleter::deleteManager($row, $user);
            $deleted++;
        }

        return back()->with('success', "Deleted {$deleted} feeder survey(s).");
    }

    public function show(FeederSurvey $feederSurvey)
    {
        abort_unless(SurveyScope::canView(Auth::user(), $feederSurvey), 403);
        $feederSurvey->releaseExpiredLock();
        $feederSurvey->load([
            'surveyor',
            'supervisor',
            'region',
            'circle',
            'division',
            'zone',
            'substation',
            'feeder',
            'sldPhotos',
        ]);

        $dtrSurveys = DtrSurvey::query()
            ->with('surveyor')
            ->where('feeder_id', $feederSurvey->feeder_id)
            ->where('surveyor_id', $feederSurvey->surveyor_id)
            ->latest()
            ->get();

        return view('feeder-surveys.show', [
            'survey' => $feederSurvey,
            'dtrSurveys' => $dtrSurveys,
            'sldPhotos' => $feederSurvey->sldPhotos,
            'canApprove' => SurveyScope::canApprove(Auth::user(), $feederSurvey),
            'reviewCounts' => $feederSurvey->reviewCounts($dtrSurveys),
        ]);
    }

    public function approve(Request $request, FeederSurvey $feederSurvey)
    {
        abort_unless(SurveyScope::canApprove(Auth::user(), $feederSurvey), 403);
        $request->validate(['review_remarks' => ['nullable', 'string', 'max:1000']]);

        $feederSurvey->update([
            'status' => FeederSurvey::STATUS_APPROVED,
            'review_remarks' => $request->review_remarks,
            'reviewed_at' => now(),
            'locked_at' => now(),
            'supervisor_id' => $feederSurvey->supervisor_id ?: Auth::id(),
        ]);

        ActivityLog::record('feeder_survey.approved', $feederSurvey, ['by' => Auth::id()]);
        AppNotification::notifyUser(
            (int) $feederSurvey->surveyor_id,
            'Feeder survey approved',
            ($feederSurvey->feeder_name ?: 'Feeder').' survey was approved.',
            route('feeder-surveys.show', $feederSurvey),
            FeederSurvey::class,
            (int) $feederSurvey->id
        );

        return back()->with('success', 'Feeder survey approved and locked.');
    }

    public function reject(Request $request, FeederSurvey $feederSurvey)
    {
        abort_unless(SurveyScope::canApprove(Auth::user(), $feederSurvey), 403);
        $data = $request->validate(['review_remarks' => ['required', 'string', 'max:1000']]);

        $feederSurvey->update([
            'status' => FeederSurvey::STATUS_REJECTED,
            'review_remarks' => $data['review_remarks'],
            'reviewed_at' => now(),
            'locked_at' => null,
        ]);

        ActivityLog::record('feeder_survey.rejected', $feederSurvey, ['reason' => $data['review_remarks']]);
        AppNotification::notifyUser(
            (int) $feederSurvey->surveyor_id,
            'Feeder survey rejected',
            'Feeder '.($feederSurvey->feeder_name ?: '').' was rejected. Reason: '.$data['review_remarks'],
            route('feeder-surveys.show', $feederSurvey),
            FeederSurvey::class,
            (int) $feederSurvey->id
        );

        return back()->with('success', 'Feeder survey rejected (unlocked for rework).');
    }

    public function unlock(Request $request, FeederSurvey $feederSurvey)
    {
        $user = Auth::user();
        abort_unless($user->canApproveSurveys() && SurveyScope::canView($user, $feederSurvey), 403);
        abort_unless($feederSurvey->locked_at !== null, 422, 'Feeder survey is not locked.');

        $feederSurvey->unlock();
        ActivityLog::record('feeder_survey.unlocked', $feederSurvey, ['by' => Auth::id()]);

        return back()->with('success', 'Feeder survey unlocked for rework / reassignment.');
    }
}
