<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\Circle;
use App\Models\Division;
use App\Models\Region;
use App\Models\Substation;
use App\Models\SubstationSurvey;
use App\Models\User;
use App\Models\Zone;
use App\Support\SimpleXlsxExporter;
use App\Support\SubstationSurveyBrowse;
use App\Support\SurveyScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubstationSurveyController extends Controller
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
            'substation_id' => $request->input('substation_id'),
            'from' => $request->input('from', now()->subDays(7)->toDateString()),
            'to' => $request->input('to', now()->toDateString()),
            'substation_code' => $request->input('substation_code'),
            'surveyor_id' => $request->input('surveyor_id'),
        ];

        $request->merge($filters);

        $viewed = $request->boolean('view') || $request->boolean('download');

        if ($request->boolean('download')) {
            return $this->downloadExcel($request);
        }

        $query = SubstationSurveyBrowse::applyFilters(
            SubstationSurveyBrowse::baseQuery($user),
            $request
        )->latest('substation_surveys.surveyed_at');

        $surveys = $viewed
            ? $query->paginate(50)->withQueryString()
            : new \Illuminate\Pagination\LengthAwarePaginator([], 0, 50);

        if ($viewed) {
            $surveys->getCollection()->each(fn (SubstationSurvey $s) => $s->releaseExpiredLock());
        }

        $regions = Region::orderBy('name')->get(['id', 'name']);
        $circles = Circle::when($filters['region_id'], fn ($q) => $q->where('region_id', $filters['region_id']))
            ->orderBy('name')->get(['id', 'name', 'region_id']);
        $divisions = Division::when($filters['circle_id'], fn ($q) => $q->where('circle_id', $filters['circle_id']))
            ->orderBy('name')->get(['id', 'name', 'circle_id']);
        $zones = Zone::when($filters['division_id'], fn ($q) => $q->where('division_id', $filters['division_id']))
            ->orderBy('name')->get(['id', 'name', 'division_id']);
        $substations = Substation::when($filters['zone_id'], fn ($q) => $q->where('zone_id', $filters['zone_id']))
            ->orderBy('name')->limit(500)->get(['id', 'name', 'zone_id']);
        $surveyors = User::whereIn('role', [User::ROLE_FIELD_EXECUTIVE, 'surveyor'])
            ->orderBy('name')->get(['id', 'name']);

        return view('substation-surveys.index', compact(
            'surveys', 'filters', 'regions', 'circles', 'divisions', 'zones', 'substations', 'surveyors', 'viewed'
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

            $query = SubstationSurveyBrowse::applyFilters(
                SubstationSurveyBrowse::exportQuery($request->user()),
                $request
            )->latest('substation_surveys.surveyed_at');

            $rows = $query->limit(5000)->get()
                ->map(fn (SubstationSurvey $r) => SubstationSurveyBrowse::excelRow($r))
                ->all();

            return SimpleXlsxExporter::download(
                'substation_surveys_'.now()->format('Ymd_His').'.xlsx',
                SubstationSurveyBrowse::excelHeaders(),
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

    public function show(SubstationSurvey $substationSurvey)
    {
        abort_unless(SurveyScope::canView(Auth::user(), $substationSurvey), 403);
        $substationSurvey->releaseExpiredLock();
        $substationSurvey->load([
            'surveyor',
            'supervisor',
            'reviewer',
            'region',
            'circle',
            'division',
            'zone',
            'substation',
        ]);

        return view('substation-surveys.show', [
            'survey' => $substationSurvey,
            'canApprove' => SurveyScope::canApprove(Auth::user(), $substationSurvey),
        ]);
    }

    public function approve(Request $request, SubstationSurvey $substationSurvey)
    {
        abort_unless(SurveyScope::canApprove(Auth::user(), $substationSurvey), 403);
        $request->validate(['review_remarks' => ['nullable', 'string', 'max:1000']]);

        $substationSurvey->update([
            'status' => SubstationSurvey::STATUS_APPROVED,
            'review_remarks' => $request->review_remarks,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
            'locked_at' => now(),
            'supervisor_id' => $substationSurvey->supervisor_id ?: Auth::id(),
        ]);
        $substationSurvey->syncSubstationCoordinates();

        ActivityLog::record('substation_survey.approved', $substationSurvey, ['by' => Auth::id()]);
        AppNotification::notifyUser(
            (int) $substationSurvey->surveyor_id,
            'Substation survey approved',
            ($substationSurvey->substation_name ?: 'Substation').' survey was approved.',
            route('substation-surveys.show', $substationSurvey),
            SubstationSurvey::class,
            (int) $substationSurvey->id
        );

        return back()->with('success', 'Substation survey approved and locked. Map coordinates copied to the substation master.');
    }

    public function reject(Request $request, SubstationSurvey $substationSurvey)
    {
        abort_unless(SurveyScope::canApprove(Auth::user(), $substationSurvey), 403);
        $data = $request->validate(['review_remarks' => ['required', 'string', 'max:1000']]);

        $substationSurvey->update([
            'status' => SubstationSurvey::STATUS_REJECTED,
            'review_remarks' => $data['review_remarks'],
            'reviewed_at' => now(),
            'reviewed_by' => Auth::id(),
            'locked_at' => null,
        ]);

        ActivityLog::record('substation_survey.rejected', $substationSurvey, ['reason' => $data['review_remarks']]);
        AppNotification::notifyUser(
            (int) $substationSurvey->surveyor_id,
            'Substation survey rejected',
            'Substation '.($substationSurvey->substation_name ?: '').' was rejected. Reason: '.$data['review_remarks'],
            route('substation-surveys.show', $substationSurvey),
            SubstationSurvey::class,
            (int) $substationSurvey->id
        );

        return back()->with('success', 'Substation survey rejected (unlocked for rework).');
    }

    public function unlock(Request $request, SubstationSurvey $substationSurvey)
    {
        $user = Auth::user();
        abort_unless($user->canApproveSurveys() && SurveyScope::canView($user, $substationSurvey), 403);
        abort_unless($substationSurvey->locked_at !== null, 422, 'Substation survey is not locked.');

        $substationSurvey->unlock();
        ActivityLog::record('substation_survey.unlocked', $substationSurvey, ['by' => Auth::id()]);

        return back()->with('success', 'Substation survey unlocked for rework.');
    }

    /** Bulk hard-delete substation surveys (admin / approver scope). */
    public function bulkDelete(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canApproveSurveys() || $user->isAdmin(), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:substation_surveys,id'],
        ]);

        $deleted = 0;
        foreach ($data['ids'] as $id) {
            $row = SubstationSurvey::find($id);
            if (! $row) {
                continue;
            }
            if (! SurveyScope::canView($user, $row) && ! $user->isAdmin()) {
                continue;
            }
            ActivityLog::record('substation_survey.deleted', $row, ['by' => $user->id]);
            $row->delete();
            $deleted++;
        }

        return back()->with('success', "Deleted {$deleted} substation survey(s).");
    }
}
