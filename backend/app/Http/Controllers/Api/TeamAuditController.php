<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConsumerSurvey;
use App\Models\DtrSurvey;
use App\Models\FeederSurvey;
use App\Models\User;
use App\Support\SimpleXlsxExporter;
use App\Support\SurveyStatusLabel;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Manager / Super Admin mobile: team audit totals + feeders / DTRs / consumers per FE.
 */
class TeamAuditController extends Controller
{
    public function index(Request $request)
    {
        $auth = $request->user();
        abort_unless($auth->canApproveSurveys() || $auth->isAdmin(), 403);

        [$from, $to] = $this->range($request);

        $executives = $this->executivesFor($auth);

        $rows = $executives->map(fn (User $u) => $this->rowFor($u, $from, $to))
            ->filter(fn (array $r) => $r['total'] > 0 || $request->boolean('include_empty'))
            ->values();

        if ($rows->isEmpty()) {
            $rows = $executives->take(40)->map(fn (User $u) => $this->rowFor($u, $from, $to))->values();
        }

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'totals' => [
                'people' => $rows->count(),
                'total' => (int) $rows->sum('total'),
                'feeder' => (int) $rows->sum('feeder_total'),
                'dtr' => (int) $rows->sum('dtr_total'),
                'consumer' => (int) $rows->sum('consumer_total'),
            ],
            'data' => $rows,
        ]);
    }

    public function show(Request $request, User $user)
    {
        $auth = $request->user();
        abort_unless($auth->canApproveSurveys() || $auth->isAdmin(), 403);
        abort_unless($user->isFieldExecutive() || $user->role === 'surveyor', 404);

        if ($auth->isManager() && ! $auth->isAdmin()) {
            abort_unless((int) $user->supervisor_id === (int) $auth->id, 403);
        }

        [$from, $to] = $this->range($request);
        $row = $this->rowFor($user, $from, $to);

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'surveyor' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'role_label' => $user->roleLabel(),
            ],
            'summary' => $row,
            // Flat aliases for mobile detail screens.
            'feeders' => $row['feeders'],
            'dtrs' => $row['dtrs'],
            'consumers' => $row['consumers'],
            'feeder_total' => $row['feeder_total'],
            'dtr_total' => $row['dtr_total'],
            'consumer_total' => $row['consumer_total'],
            'total' => $row['total'],
        ]);
    }

    /**
     * Excel: surveyor completion summary for date range (Audit Report format).
     * Query: from, to, optional user_id.
     */
    public function export(Request $request)
    {
        $auth = $request->user();
        abort_unless($auth->canApproveSurveys() || $auth->isAdmin(), 403);

        [$from, $to] = $this->range($request);
        $userId = (int) $request->query('user_id', 0);

        $executives = $this->executivesFor($auth);
        if ($userId > 0) {
            $executives = $executives->where('id', $userId)->values();
            abort_if($executives->isEmpty(), 404, 'Surveyor not in your team.');
        }

        $headers = [
            'surveyor', 'email', 'role',
            'pending', 'approved', 'rejected', 'completed', 'total',
            'feeder_total', 'feeder_pending', 'feeder_approved',
            'dtr_total', 'dtr_pending', 'dtr_approved',
            'consumer_total', 'from', 'to',
        ];

        $exportRows = $executives->map(function (User $u) use ($from, $to) {
            $row = $this->rowFor($u, $from, $to);
            $feederPending = collect($row['feeders'])->whereIn('status', ['draft', 'sld_pending', 'pending_approval'])->count();
            $feederApproved = collect($row['feeders'])->whereIn('status', ['approved', 'completed'])->count();
            $dtrPending = collect($row['dtrs'])->whereIn('status', ['draft', 'pending_approval'])->count();
            $dtrApproved = collect($row['dtrs'])->whereIn('status', ['approved', 'completed'])->count();
            $pending = $feederPending + $dtrPending + collect($row['consumers'] ?? [])->where('status', 'pending_approval')->count();
            $approved = $feederApproved + $dtrApproved + collect($row['consumers'] ?? [])->where('status', 'approved')->count();
            $rejected = collect($row['feeders'])->where('status', 'rejected')->count()
                + collect($row['dtrs'])->where('status', 'rejected')->count()
                + collect($row['consumers'] ?? [])->where('status', 'rejected')->count();
            $completed = collect($row['dtrs'])->where('status', 'completed')->count()
                + collect($row['consumers'] ?? [])->whereIn('status', ['saved', 'not_accessible', 'approved'])->count();

            return [
                $u->name,
                $u->email,
                $u->role,
                $pending,
                $approved,
                $rejected,
                $completed,
                $row['total'],
                $row['feeder_total'],
                $feederPending,
                $feederApproved,
                $row['dtr_total'],
                $dtrPending,
                $dtrApproved,
                $row['consumer_total'],
                $from->toDateString(),
                $to->toDateString(),
            ];
        })->values();

        $suffix = $userId > 0 ? '_user'.$userId : '';

        return SimpleXlsxExporter::download(
            'team_audit_'.$from->format('Ymd').'_'.$to->format('Ymd').$suffix.'.xlsx',
            $headers,
            $exportRows
        );
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    private function executivesFor(User $auth)
    {
        return User::query()
            ->where('is_active', true)
            ->whereIn('role', [User::ROLE_FIELD_EXECUTIVE, 'surveyor'])
            ->when($auth->isManager() && ! $auth->isAdmin(), function ($q) use ($auth) {
                $q->where('supervisor_id', $auth->id);
            })
            ->orderBy('name')
            ->get();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(Request $request): array
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

    private function inRange($query, Carbon $from, Carbon $to)
    {
        return $query->where(function ($q) use ($from, $to) {
            $q->whereBetween('surveyed_at', [$from, $to])
                ->orWhere(function ($q2) use ($from, $to) {
                    $q2->whereNull('surveyed_at')->whereBetween('created_at', [$from, $to]);
                });
        });
    }

    /** @return array<string, mixed> */
    private function rowFor(User $user, Carbon $from, Carbon $to): array
    {
        $feeders = $this->inRange(FeederSurvey::query()->where('surveyor_id', $user->id), $from, $to)
            ->orderByDesc('surveyed_at')
            ->get(['id', 'feeder_name', 'feeder_code', 'status', 'surveyed_at', 'feeder_id']);

        $dtrs = $this->inRange(DtrSurvey::query()->where('surveyor_id', $user->id), $from, $to)
            ->orderByDesc('surveyed_at')
            ->get(['id', 'dtr_name', 'dtr_code', 'feeder_name', 'feeder_code', 'status', 'surveyed_at', 'entry_source']);

        $consumerBase = $this->inRange(ConsumerSurvey::query()->where('surveyor_id', $user->id), $from, $to);
        $consumerTotal = (clone $consumerBase)->count();

        $consumerRows = (clone $consumerBase)
            ->with([
                'dtr:id,code,name,feeder_id',
                'dtr.feeder:id,code,name',
                'dtrSurvey:id,dtr_name,dtr_code,feeder_name,feeder_code',
            ])
            ->orderByDesc('surveyed_at')
            ->limit(5000)
            ->get([
                'id',
                'consumer_name',
                'ivrs',
                'msn',
                'phone',
                'status',
                'surveyed_at',
                'dtr_id',
                'dtr_survey_id',
                'verification_status',
            ]);

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role_label' => $user->roleLabel(),
            ],
            'feeder_total' => $feeders->count(),
            'dtr_total' => $dtrs->count(),
            'consumer_total' => $consumerTotal,
            'total' => $feeders->count() + $dtrs->count() + $consumerTotal,
            'feeder_names' => $feeders->map(function ($r) {
                $n = trim((string) ($r->feeder_name ?: 'Feeder'));
                $c = trim((string) ($r->feeder_code ?: ''));

                return $c !== '' ? "{$n} ({$c})" : $n;
            })->unique()->values()->all(),
            'dtr_names' => $dtrs->map(function ($r) {
                $n = trim((string) ($r->dtr_name ?: 'DTR'));
                $c = trim((string) ($r->dtr_code ?: ''));

                return $c !== '' ? "{$n} ({$c})" : $n;
            })->unique()->values()->all(),
            'dtrs' => $dtrs->map(fn ($r) => [
                'id' => $r->id,
                'dtr_name' => $r->dtr_name,
                'dtr_code' => $r->dtr_code,
                'feeder_name' => $r->feeder_name,
                'feeder_code' => $r->feeder_code,
                'entry_source' => $r->entry_source,
                'status' => $r->status,
                'status_label' => SurveyStatusLabel::label($r->status, 'dtr'),
                'surveyed_at' => optional($r->surveyed_at)?->toDateTimeString(),
            ])->values()->all(),
            'feeders' => $feeders->map(fn ($r) => [
                'id' => $r->id,
                'feeder_name' => $r->feeder_name,
                'feeder_code' => $r->feeder_code,
                'status' => $r->status,
                'status_label' => SurveyStatusLabel::label($r->status, 'feeder'),
                'surveyed_at' => optional($r->surveyed_at)?->toDateTimeString(),
            ])->values()->all(),
            'consumers' => $consumerRows->map(function ($r) {
                $dtrName = $r->dtrSurvey?->dtr_name
                    ?: $r->dtr?->name
                    ?: null;
                $dtrCode = $r->dtrSurvey?->dtr_code
                    ?: $r->dtr?->code
                    ?: null;
                $feederName = $r->dtrSurvey?->feeder_name
                    ?: $r->dtr?->feeder?->name
                    ?: null;
                $feederCode = $r->dtrSurvey?->feeder_code
                    ?: $r->dtr?->feeder?->code
                    ?: null;

                return [
                    'id' => $r->id,
                    'consumer_name' => $r->consumer_name,
                    'ivrs' => $r->ivrs,
                    'msn' => $r->msn,
                    'phone' => $r->phone,
                    'status' => $r->status,
                    'status_label' => SurveyStatusLabel::label($r->status, 'consumer'),
                    'verification_status' => $r->verification_status,
                    'dtr_id' => $r->dtr_id,
                    'dtr_name' => $dtrName,
                    'dtr_code' => $dtrCode,
                    'feeder_name' => $feederName,
                    'feeder_code' => $feederCode,
                    'surveyed_at' => optional($r->surveyed_at)?->toDateTimeString(),
                ];
            })->values()->all(),
        ];
    }
}
