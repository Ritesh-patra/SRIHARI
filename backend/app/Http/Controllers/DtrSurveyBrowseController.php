<?php

namespace App\Http\Controllers;

use App\Models\Circle;
use App\Models\Division;
use App\Models\DtrSurvey;
use App\Models\Region;
use App\Models\User;
use App\Models\Zone;
use App\Support\DtrSurveyBrowse;
use App\Support\DtrSurveyDeleter;
use App\Support\SimpleXlsxExporter;
use App\Support\SurveyScope;
use Illuminate\Http\Request;

class DtrSurveyBrowseController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canApproveSurveys() || $user->isAdmin(), 403);

        $filters = [
            'status' => $request->input('status', 'all'),
            'region_id' => $request->input('region_id'),
            'circle_id' => $request->input('circle_id'),
            'division_id' => $request->input('division_id'),
            'zone_id' => $request->input('zone_id'),
            'from' => $request->input('from', now()->subDays(7)->toDateString()),
            'to' => $request->input('to', now()->toDateString()),
            'dtr_code' => $request->input('dtr_code'),
            'feeder_code' => $request->input('feeder_code'),
            'surveyor_id' => $request->input('surveyor_id'),
        ];

        $request->merge($filters);

        $viewed = $request->boolean('view') || $request->boolean('download');

        if ($request->boolean('download')) {
            return $this->downloadExcel($request);
        }

        $query = DtrSurveyBrowse::applyFilters(
            DtrSurveyBrowse::baseQuery($user),
            $request
        )->latest('dtr_surveys.surveyed_at');

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

        return view('dtr-surveys.index', compact(
            'surveys', 'filters', 'regions', 'circles', 'divisions', 'zones', 'surveyors', 'viewed'
        ));
    }

    /** Bulk hard-delete DTR surveys (cascades consumer surveys). */
    public function bulkDelete(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canApproveSurveys() || $user->isAdmin(), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:dtr_surveys,id'],
        ]);

        $deleted = 0;
        foreach ($data['ids'] as $id) {
            $row = DtrSurvey::find($id);
            if (! $row) {
                continue;
            }
            if (! SurveyScope::canView($user, $row) && ! $user->isAdmin()) {
                continue;
            }
            DtrSurveyDeleter::delete($row, $user);
            $deleted++;
        }

        return back()->with('success', "Deleted {$deleted} DTR survey(s).");
    }

    private function downloadExcel(Request $request)
    {
        @ini_set('memory_limit', '256M');
        @set_time_limit(120);

        try {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            $query = DtrSurveyBrowse::applyFilters(
                DtrSurveyBrowse::exportQuery($request->user()),
                $request
            )->latest('dtr_surveys.surveyed_at');

            $rows = $query->limit(5000)->get()
                ->map(fn (DtrSurvey $r) => DtrSurveyBrowse::excelRow($r))
                ->all();

            return SimpleXlsxExporter::download(
                'dtr_surveys_'.now()->format('Ymd_His').'.xlsx',
                DtrSurveyBrowse::excelHeaders(),
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
}
