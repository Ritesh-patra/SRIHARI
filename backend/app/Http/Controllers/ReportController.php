<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\ConsumerSurvey;
use App\Models\DtrSurvey;
use App\Models\FeederSurvey;
use App\Models\User;
use App\Support\ConsumerSurveyDeleter;
use App\Support\SurveyScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /** Statuses treated as pending (awaiting action / approval). */
    private const PENDING = ['draft', 'sld_pending', 'pending_approval'];

    /** Statuses treated as approved. */
    private const APPROVED = ['approved'];

    /** Statuses treated as completed/done. */
    private const COMPLETED = ['completed'];

    public function index()
    {
        $user = Auth::user();
        $base = SurveyScope::apply(DtrSurvey::query(), $user);

        $byStatus = (clone $base)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $meterInstall = [
            'Installed' => (clone $base)->where('smart_meter_status', 'Installed')->count(),
            'Not Installed' => (clone $base)->where('smart_meter_status', 'Not Installed')->count(),
            'Meter Missing' => (clone $base)->where('smart_meter_status', 'Meter Missing')->count(),
        ];

        $aging = (clone $base)
            ->where('status', 'pending_approval')
            ->where('surveyed_at', '<', now()->subDays(3))
            ->count();

        $photoComplete = (clone $base)
            ->whereNotNull('dtr_overall_photo')
            ->whereNotNull('smart_meter_photo')
            ->count();

        $feProductivity = DtrSurvey::query()
            ->select('surveyor_id', DB::raw('count(*) as total'))
            ->groupBy('surveyor_id')
            ->with('surveyor')
            ->orderByDesc('total')
            ->take(10)
            ->get();

        $rejectionReasons = (clone $base)
            ->where('status', 'rejected')
            ->whereNotNull('review_remarks')
            ->latest()
            ->take(10)
            ->get(['dtr_name', 'review_remarks', 'reviewed_at']);

        $daily = (clone $base)
            ->where('surveyed_at', '>=', now()->subDays(14))
            ->select(DB::raw('date(surveyed_at) as day'), DB::raw('count(*) as total'))
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $consumerCoverage = ConsumerSurvey::count();

        return view('reports.index', compact(
            'byStatus',
            'meterInstall',
            'aging',
            'photoComplete',
            'feProductivity',
            'rejectionReasons',
            'daily',
            'consumerCoverage'
        ));
    }

    /** Per-surveyor individual reports (date range + optional user/role filter). */
    public function surveyors(Request $request)
    {
        [$from, $to] = $this->resolveDateRange($request);

        $role = $request->query('role', User::ROLE_FIELD_EXECUTIVE);
        $userId = $request->query('user_id');

        $usersQuery = User::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($role && in_array($role, User::ROLES, true)) {
            $usersQuery->where('role', $role);
        }

        if ($userId) {
            $usersQuery->where('id', (int) $userId);
        }

        $users = $usersQuery->get();
        $filterUsers = User::query()
            ->where('is_active', true)
            ->when($role && in_array($role, User::ROLES, true), fn ($q) => $q->where('role', $role))
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role']);

        $rows = $users->map(function (User $u) use ($from, $to) {
            return $this->buildUserReportRow($u, $from, $to);
        })->filter(fn (array $row) => $row['total'] > 0 || $request->boolean('include_empty'))
            ->values();

        // Always show selected users even with zero activity when filtering one user
        if ($userId && $rows->isEmpty() && $users->isNotEmpty()) {
            $rows = $users->map(fn (User $u) => $this->buildUserReportRow($u, $from, $to))->values();
        }

        if (! $userId && ! $request->boolean('include_empty')) {
            // Prefer users with activity; if none, still list FE roster lightly
            if ($rows->isEmpty()) {
                $rows = $users->take(50)->map(fn (User $u) => $this->buildUserReportRow($u, $from, $to))->values();
            }
        }

        $totals = [
            'people' => $rows->count(),
            'pending' => (int) $rows->sum('pending'),
            'approved' => (int) $rows->sum('approved'),
            'rejected' => (int) $rows->sum('rejected'),
            'completed' => (int) $rows->sum('completed'),
            'total' => (int) $rows->sum('total'),
            'feeder' => (int) $rows->sum(fn ($r) => $r['feeder']['total']),
            'dtr' => (int) $rows->sum(fn ($r) => $r['dtr']['total']),
            'consumer' => (int) $rows->sum(fn ($r) => $r['consumer']['total']),
        ];

        return view('reports.surveyors', [
            'rows' => $rows,
            'totals' => $totals,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'role' => $role,
            'userId' => $userId ? (int) $userId : null,
            'filterUsers' => $filterUsers,
            'roles' => User::ROLES,
            'includeEmpty' => $request->boolean('include_empty'),
        ]);
    }

    /** Detailed individual report for one surveyor in date range. */
    public function surveyorShow(Request $request, User $user)
    {
        [$from, $to] = $this->resolveDateRange($request);

        $summary = $this->buildUserReportRow($user, $from, $to);

        $feeders = $this->surveysInRange(FeederSurvey::query()->where('surveyor_id', $user->id), $from, $to)
            ->orderByDesc('surveyed_at')
            ->orderByDesc('id')
            ->get();

        $dtrs = $this->surveysInRange(DtrSurvey::query()->where('surveyor_id', $user->id), $from, $to)
            ->orderByDesc('surveyed_at')
            ->orderByDesc('id')
            ->get();

        $consumers = $this->surveysInRange(ConsumerSurvey::query()->where('surveyor_id', $user->id), $from, $to)
            ->with(['pole:id,pole_no', 'dtr:id,name,code'])
            ->orderByDesc('surveyed_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return view('reports.surveyor-show', [
            'surveyor' => $user,
            'summary' => $summary,
            'feeders' => $feeders,
            'dtrs' => $dtrs,
            'consumers' => $consumers,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);
    }

    public function exportSurveys(Request $request)
    {
        $user = Auth::user();
        $query = SurveyScope::apply(DtrSurvey::query()->with('surveyor'), $user);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $headers = [
            'id', 'dtr_code', 'dtr_name', 'feeder', 'status', 'smart_meter',
            'surveyor', 'surveyed_at', 'circle_id', 'division_id', 'observation',
        ];
        $rows = $query->orderBy('id')->get()->map(fn (DtrSurvey $s) => [
            $s->id, $s->dtr_code, $s->dtr_name, $s->feeder_name, $s->status,
            $s->smart_meter_status, $s->surveyor?->name, optional($s->surveyed_at)?->toDateTimeString(),
            $s->circle_id, $s->division_id, $s->observation,
        ]);

        return \App\Support\SimpleXlsxExporter::download(
            'dtr_surveys_'.now()->format('Ymd_His').'.xlsx',
            $headers,
            $rows
        );
    }

    /** Excel export of surveyor summary report (same filters as /reports/surveyors). */
    public function exportSurveyors(Request $request)
    {
        [$from, $to] = $this->resolveDateRange($request);

        $role = $request->query('role', User::ROLE_FIELD_EXECUTIVE);
        $userId = $request->query('user_id');

        $usersQuery = User::query()->where('is_active', true)->orderBy('name');
        if ($role && in_array($role, User::ROLES, true)) {
            $usersQuery->where('role', $role);
        }
        if ($userId) {
            $usersQuery->where('id', (int) $userId);
        }

        $users = $usersQuery->get();
        $rows = $users->map(function (User $u) use ($from, $to) {
            return $this->buildUserReportRow($u, $from, $to);
        })->filter(fn (array $row) => $row['total'] > 0 || $request->boolean('include_empty') || $userId)
            ->values();

        $headers = [
            'surveyor', 'email', 'role',
            'pending', 'approved', 'rejected', 'completed', 'total',
            'feeder_total', 'feeder_pending', 'feeder_approved',
            'dtr_total', 'dtr_pending', 'dtr_approved',
            'consumer_total', 'from', 'to',
        ];

        $exportRows = $rows->map(fn (array $row) => [
            $row['user']->name,
            $row['user']->email,
            $row['user']->role,
            $row['pending'],
            $row['approved'],
            $row['rejected'],
            $row['completed'],
            $row['total'],
            $row['feeder']['total'],
            $row['feeder']['pending'],
            $row['feeder']['approved'],
            $row['dtr']['total'],
            $row['dtr']['pending'],
            $row['dtr']['approved'],
            $row['consumer']['total'],
            $from->toDateString(),
            $to->toDateString(),
        ]);

        return \App\Support\SimpleXlsxExporter::download(
            'surveyor_report_'.now()->format('Ymd_His').'.xlsx',
            $headers,
            $exportRows
        );
    }

    /** Excel export of feeder surveys list. */
    public function exportFeederSurveys(Request $request)
    {
        $user = Auth::user();
        $query = SurveyScope::apply(
            FeederSurvey::query()->with(['surveyor', 'feeder', 'substation']),
            $user
        );

        if ($status = $request->string('status')->toString()) {
            if ($status !== '' && $status !== 'all') {
                $query->where('status', $status);
            }
        }

        $headers = [
            'id', 'surveyor', 'feeder_code', 'feeder_name', 'substation',
            'status', 'display_status', 'dtrs_completed', 'dtrs_expected',
            'surveyed_at', 'reviewed_at', 'remarks',
        ];

        $rows = $query->latest()->get()->map(fn (FeederSurvey $s) => [
            $s->id,
            $s->surveyor?->name,
            $s->feeder_code,
            $s->feeder_name,
            $s->substation_name,
            $s->status,
            $s->display_status,
            $s->dtrs_completed,
            $s->dtrs_expected,
            optional($s->surveyed_at)?->toDateTimeString(),
            optional($s->reviewed_at)?->toDateTimeString(),
            $s->remarks,
        ]);

        return \App\Support\SimpleXlsxExporter::download(
            'feeder_surveys_'.now()->format('Ymd_His').'.xlsx',
            $headers,
            $rows
        );
    }

    public function exportConsumers()
    {
        $filename = 'consumer_surveys_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'dtr_survey_id', 'pole_id', 'name', 'phone', 'ivrs', 'msn', 'flag', 'surveyed_at']);
            ConsumerSurvey::orderBy('id')->chunk(200, function ($rows) use ($out) {
                foreach ($rows as $c) {
                    fputcsv($out, [
                        $c->id, $c->dtr_survey_id, $c->pole_id, $c->consumer_name,
                        $c->phone, $c->ivrs, $c->msn, $c->survey_flag, $c->surveyed_at,
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function printable(DtrSurvey $survey)
    {
        abort_unless(SurveyScope::canView(Auth::user(), $survey), 403);
        $survey->load(['surveyor', 'region', 'circle', 'division', 'zone', 'substation']);

        return view('reports.printable', compact('survey'));
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function resolveDateRange(Request $request): array
    {
        $from = $request->query('from')
            ? Carbon::parse($request->query('from'))->startOfDay()
            : now()->subDays(30)->startOfDay();
        $to = $request->query('to')
            ? Carbon::parse($request->query('to'))->endOfDay()
            : now()->endOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    /**
     * @return array{
     *   user: User,
     *   pending: int,
     *   approved: int,
     *   rejected: int,
     *   completed: int,
     *   total: int,
     *   feeder: array{pending:int,approved:int,rejected:int,completed:int,total:int},
     *   dtr: array{pending:int,approved:int,rejected:int,completed:int,total:int},
     *   consumer: array{pending:int,approved:int,rejected:int,completed:int,total:int}
     * }
     */
    private function buildUserReportRow(User $user, Carbon $from, Carbon $to): array
    {
        $feederRows = $this->surveysInRange(FeederSurvey::query()->where('surveyor_id', $user->id), $from, $to)
            ->orderByDesc('surveyed_at')
            ->get(['id', 'status', 'feeder_name', 'feeder_code', 'substation_name', 'surveyed_at']);
        $feeder = $this->countByStatusBuckets($feederRows);

        $dtrRows = $this->surveysInRange(DtrSurvey::query()->where('surveyor_id', $user->id), $from, $to)
            ->orderByDesc('surveyed_at')
            ->get(['id', 'status', 'consumer_survey_completed_at', 'dtr_name', 'dtr_code', 'feeder_name', 'surveyed_at']);

        $dtr = $this->countDtrBuckets($dtrRows);

        $consumerRows = $this->surveysInRange(ConsumerSurvey::query()->where('surveyor_id', $user->id), $from, $to)
            ->get(['status']);
        $consumer = [
            'pending' => $consumerRows->where('status', 'pending_approval')->count(),
            'approved' => $consumerRows->where('status', 'approved')->count(),
            'rejected' => $consumerRows->where('status', 'rejected')->count(),
            'completed' => $consumerRows->whereIn('status', ['saved', 'not_accessible'])->count(),
            'total' => $consumerRows->count(),
        ];

        $pending = $feeder['pending'] + $dtr['pending'] + $consumer['pending'];
        $approved = $feeder['approved'] + $dtr['approved'] + $consumer['approved'];
        $rejected = $feeder['rejected'] + $dtr['rejected'] + $consumer['rejected'];
        $completed = $feeder['completed'] + $dtr['completed'] + $consumer['completed'] + $consumer['approved'];
        $total = $feeder['total'] + $dtr['total'] + $consumer['total'];

        $feederNames = $feederRows->map(function ($r) {
            $name = trim((string) ($r->feeder_name ?: 'Feeder'));
            $code = trim((string) ($r->feeder_code ?: ''));

            return $code !== '' ? "{$name} ({$code})" : $name;
        })->unique()->values()->all();

        $dtrNames = $dtrRows->map(function ($r) {
            $name = trim((string) ($r->dtr_name ?: 'DTR'));
            $code = trim((string) ($r->dtr_code ?: ''));

            return $code !== '' ? "{$name} ({$code})" : $name;
        })->unique()->values()->all();

        return [
            'user' => $user,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'completed' => $completed,
            'total' => $total,
            'feeder' => $feeder,
            'dtr' => $dtr,
            'consumer' => $consumer,
            'feeder_names' => $feederNames,
            'dtr_names' => $dtrNames,
            'dtr_items' => $dtrRows->map(fn ($r) => [
                'id' => $r->id,
                'dtr_name' => $r->dtr_name,
                'dtr_code' => $r->dtr_code,
                'feeder_name' => $r->feeder_name,
                'status' => $r->status,
                'surveyed_at' => optional($r->surveyed_at)?->toDateTimeString(),
            ])->values()->all(),
            'feeder_items' => $feederRows->map(fn ($r) => [
                'id' => $r->id,
                'feeder_name' => $r->feeder_name,
                'feeder_code' => $r->feeder_code,
                'substation_name' => $r->substation_name,
                'status' => $r->status,
                'surveyed_at' => optional($r->surveyed_at)?->toDateTimeString(),
            ])->values()->all(),
        ];
    }

    private function surveysInRange($query, Carbon $from, Carbon $to)
    {
        return $query->where(function ($q) use ($from, $to) {
            $q->whereBetween('surveyed_at', [$from, $to])
                ->orWhere(function ($q2) use ($from, $to) {
                    $q2->whereNull('surveyed_at')
                        ->whereBetween('created_at', [$from, $to]);
                });
        });
    }

    /** @param  Collection<int, object>  $rows */
    private function countByStatusBuckets(Collection $rows): array
    {
        $pending = $rows->whereIn('status', self::PENDING)->count();
        $approved = $rows->whereIn('status', self::APPROVED)->count();
        $rejected = $rows->where('status', 'rejected')->count();
        $completed = $rows->whereIn('status', self::COMPLETED)->count();

        return [
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'completed' => $completed,
            'total' => $rows->count(),
        ];
    }

    /** @param  Collection<int, object>  $rows */
    private function countDtrBuckets(Collection $rows): array
    {
        $pending = 0;
        $approved = 0;
        $rejected = 0;
        $completed = 0;

        foreach ($rows as $row) {
            if (! empty($row->consumer_survey_completed_at)) {
                $completed++;
                continue;
            }
            $status = (string) $row->status;
            if (in_array($status, self::PENDING, true)) {
                $pending++;
            } elseif (in_array($status, self::APPROVED, true)) {
                $approved++;
            } elseif ($status === 'rejected') {
                $rejected++;
            } elseif (in_array($status, self::COMPLETED, true)) {
                $completed++;
            }
        }

        return [
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'completed' => $completed,
            'total' => $rows->count(),
        ];
    }

    /** Hard-delete a survey from individual report (Super Admin / Admin). */
    public function destroySurvey(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'type' => ['required', 'in:feeder,dtr,consumer'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'surveyor_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $deleted = 0;
        $user = $request->user();

        foreach ($data['ids'] as $id) {
            if ($data['type'] === 'feeder') {
                $row = FeederSurvey::find($id);
                if (! $row) {
                    continue;
                }
                ActivityLog::record('feeder_survey.report_deleted', $row, ['by' => $user->id]);
                $row->delete();
                $deleted++;
            } elseif ($data['type'] === 'dtr') {
                $row = DtrSurvey::find($id);
                if (! $row) {
                    continue;
                }
                ActivityLog::record('survey.report_deleted', $row, ['by' => $user->id]);
                $row->delete();
                $deleted++;
            } else {
                $row = ConsumerSurvey::find($id);
                if (! $row) {
                    continue;
                }
                ConsumerSurveyDeleter::delete($row, $user);
                $deleted++;
            }
        }

        $redirect = redirect()->back();
        if ($request->filled('surveyor_id')) {
            $redirect = redirect()->route('reports.surveyors.show', array_filter([
                'user' => (int) $data['surveyor_id'],
                'from' => $data['from'] ?? null,
                'to' => $data['to'] ?? null,
            ]));
        }

        return $redirect->with('success', "Deleted {$deleted} {$data['type']} survey(s).");
    }
}
