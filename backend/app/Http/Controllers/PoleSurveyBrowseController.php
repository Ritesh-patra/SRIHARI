<?php

namespace App\Http\Controllers;

use App\Models\Circle;
use App\Models\ConsumerSurvey;
use App\Models\Division;
use App\Models\Pole;
use App\Models\Region;
use App\Models\User;
use App\Models\Zone;
use App\Support\ConsumerSurveyDeleter;
use App\Support\PoleSurveyBrowse;
use App\Support\SimpleXlsxExporter;
use App\Support\SurveyScope;
use Illuminate\Http\Request;

class PoleSurveyBrowseController extends Controller
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
            'pole_code' => $request->input('pole_code'),
            'dtr_code' => $request->input('dtr_code'),
            'surveyor_id' => $request->input('surveyor_id'),
        ];

        $request->merge($filters);

        $viewed = $request->boolean('view') || $request->boolean('download');

        if ($request->boolean('download')) {
            return $this->downloadExcel($request);
        }

        $query = PoleSurveyBrowse::applyFilters(
            PoleSurveyBrowse::baseQuery($user),
            $request
        )->latest('poles.id');

        $poles = $viewed
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

        return view('pole-surveys.index', compact(
            'poles', 'filters', 'regions', 'circles', 'divisions', 'zones', 'surveyors', 'viewed'
        ));
    }

    /**
     * Bulk-delete selected poles (and their consumer surveys).
     */
    public function bulkDelete(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canApproveSurveys() || $user->isAdmin(), 403);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:poles,id'],
        ]);

        $allowedPoles = PoleSurveyBrowse::applyScope(
            Pole::query()->whereIn('id', $data['ids']),
            $user
        )->get();

        $deletedSurveys = 0;
        $deletedPoles = 0;

        foreach ($allowedPoles as $pole) {
            $surveys = ConsumerSurvey::query()
                ->with('dtrSurvey')
                ->where('pole_id', $pole->id)
                ->get();

            foreach ($surveys as $row) {
                if ($row->dtrSurvey && ! SurveyScope::canView($user, $row->dtrSurvey) && ! $user->isAdmin()) {
                    continue 2;
                }
                ConsumerSurveyDeleter::delete($row, $user);
                $deletedSurveys++;
            }

            // Clear previous_pole references so delete is not blocked.
            Pole::query()->where('previous_pole_id', $pole->id)->update(['previous_pole_id' => null, 'source_type' => 'dtr']);
            $pole->delete();
            $deletedPoles++;
        }

        return back()->with(
            'success',
            "Deleted {$deletedPoles} pole(s) and {$deletedSurveys} consumer survey(s)."
        );
    }

    private function downloadExcel(Request $request)
    {
        @ini_set('memory_limit', '256M');
        @set_time_limit(120);

        try {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            $query = PoleSurveyBrowse::applyFilters(
                PoleSurveyBrowse::exportQuery($request->user()),
                $request
            )->latest('poles.id');

            $rows = $query->limit(5000)->get()
                ->map(fn (Pole $r) => PoleSurveyBrowse::excelRow($r))
                ->all();

            return SimpleXlsxExporter::download(
                'pole_surveys_'.now()->format('Ymd_His').'.xlsx',
                PoleSurveyBrowse::excelHeaders(),
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
