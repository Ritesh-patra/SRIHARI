<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\Circle;
use App\Models\Consumer;
use App\Models\ConsumerSurvey;
use App\Models\Division;
use App\Models\Dtr;
use App\Models\DtrReactivationRequest;
use App\Models\DtrSurvey;
use App\Models\Feeder;
use App\Models\FeederSurvey;
use App\Models\Pole;
use App\Models\Region;
use App\Models\Substation;
use App\Models\User;
use App\Models\UserScope;
use App\Models\WorkAssignment;
use App\Models\Zone;
use App\Rules\ClientImageFile;
use App\Support\FeederSurveyDeleter;
use App\Support\HierarchyScope;
use App\Support\DtrConsumerReopen;
use App\Support\SurveyPhotoStorage;
use App\Support\SurveyScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class FieldApiController extends Controller
{
    public function dashboard(Request $request)
    {
        $user = $request->user();
        $base = SurveyScope::apply(DtrSurvey::query(), $user);
        $feederBase = FeederSurvey::query();
        if ($user->isAdmin() || $user->canApproveSurveys()) {
            $feederBase = SurveyScope::apply($feederBase, $user);
        } else {
            $feederBase->where('surveyor_id', $user->id);
        }

        return response()->json([
            'stats' => [
                'pending' => (clone $base)->where('status', 'pending_approval')->count(),
                'rejected' => (clone $base)->where('status', 'rejected')->count(),
                'approved' => (clone $base)->where('status', 'approved')->whereNull('consumer_survey_completed_at')->count(),
                'completed' => (clone $base)->whereNotNull('consumer_survey_completed_at')->count(),
                'feeder_pending' => (clone $feederBase)->where('status', 'pending_approval')->count(),
                'feeder_sld_pending' => (clone $feederBase)->where('status', 'sld_pending')->count(),
                'feeder_dtr_pending' => (clone $feederBase)->where('status', 'draft')->count(),
                'feeder_rejected' => (clone $feederBase)->where('status', 'rejected')->count(),
                'feeder_approved' => (clone $feederBase)->whereIn('status', ['approved', 'completed'])->count(),
                'feeder_submitted' => (clone $feederBase)->whereIn('status', ['draft', 'sld_pending', 'pending_approval', 'approved', 'completed'])->count(),
                'feeder_total' => (clone $feederBase)->whereIn('status', ['draft', 'sld_pending', 'pending_approval', 'rejected', 'approved', 'completed'])->count(),
                'assignments' => WorkAssignment::when($user->isFieldExecutive(), fn ($q) => $q->where('assigned_to', $user->id))->count(),
                'unread' => AppNotification::where('user_id', $user->id)->whereNull('read_at')->count(),
                'consumer_pending' => $user->canApproveConsumerSurveys()
                    ? \App\Support\ConsumerSurveyApproval::applyFilters(
                        \App\Support\ConsumerSurveyApproval::baseQuery($user),
                        new Request(['status' => 'pending_approval'])
                    )->count()
                    : 0,
            ],
            'can_consumer_survey_approve' => $user->canApproveConsumerSurveys(),
            'recent' => (clone $base)->latest()->take(8)->get(),
        ]);
    }

    public function surveys(Request $request)
    {
        $user = $request->user();
        $query = SurveyScope::apply(DtrSurvey::query()->with('surveyor'), $user);

        $status = trim((string) $request->query('status', ''));
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $feederId = $request->integer('feeder_id');
        if ($feederId > 0) {
            $query->where('feeder_id', $feederId);
        }

        $entrySource = trim((string) $request->query('entry_source', ''));
        if ($entrySource !== '') {
            $query->where('entry_source', $entrySource);
        }

        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        if ($from !== '') {
            $query->whereDate('surveyed_at', '>=', $from);
        }
        if ($to !== '') {
            $query->whereDate('surveyed_at', '<=', $to);
        }

        $perPage = min(200, max(20, $request->integer('per_page', 20)));
        $surveys = $query->latest()->paginate($perPage);
        $surveys->getCollection()->transform(function (DtrSurvey $s) use ($user) {
            $ownEditable = ($user->isFieldExecutive() || $user->isAdmin())
                && (int) $s->surveyor_id === (int) $user->id
                && in_array($s->status, ['draft', 'pending_approval', 'rejected'], true)
                && $s->locked_at === null;
            $managerDelete = $user->canApproveSurveys() && SurveyScope::canView($user, $s);
            $s->setAttribute('can_edit', $ownEditable || ($user->canApproveSurveys() && SurveyScope::canView($user, $s)));
            $s->setAttribute('can_delete', $ownEditable || $managerDelete);

            return $s;
        });

        return response()->json($surveys);
    }

    /** FE progress: filtered feeder / DTR / consumer surveys owned by the signed-in user. */
    public function myProgress(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canCaptureSurveys() || $user->isAdmin(), 403);

        $type = trim((string) $request->query('type', 'all'));
        if (! in_array($type, ['all', 'feeder', 'dtr', 'consumer'], true)) {
            $type = 'all';
        }
        $status = trim((string) $request->query('status', 'all'));
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));

        $items = collect();

        if ($type === 'all' || $type === 'feeder') {
            $q = FeederSurvey::query()->where('surveyor_id', $user->id);
            if ($status !== '' && $status !== 'all') {
                $q->where('status', $status);
            }
            if ($from !== '') {
                $q->whereDate('surveyed_at', '>=', $from);
            }
            if ($to !== '') {
                $q->whereDate('surveyed_at', '<=', $to);
            }
            foreach ($q->latest()->limit(200)->get() as $s) {
                $canMutate = \App\Support\FeederSurveyDeleter::canOwnDelete($s);
                $items->push([
                    'type' => 'feeder',
                    'id' => $s->id,
                    'title' => $s->feeder_name ?: 'Feeder',
                    'subtitle' => $s->feeder_code ?: '',
                    'status' => $s->status,
                    'status_label' => \App\Support\SurveyStatusLabel::label($s->status, 'feeder'),
                    'surveyed_at' => optional($s->surveyed_at)?->toDateTimeString(),
                    'can_edit' => $canMutate,
                    'can_delete' => $canMutate,
                ]);
            }
        }

        if ($type === 'all' || $type === 'dtr') {
            $q = DtrSurvey::query()->where('surveyor_id', $user->id);
            if ($status !== '' && $status !== 'all') {
                $q->where('status', $status);
            }
            if ($from !== '') {
                $q->whereDate('surveyed_at', '>=', $from);
            }
            if ($to !== '') {
                $q->whereDate('surveyed_at', '<=', $to);
            }
            foreach ($q->latest()->limit(200)->get() as $s) {
                $canMutate = in_array($s->status, ['draft', 'pending_approval', 'rejected'], true) && $s->locked_at === null;
                $items->push([
                    'type' => 'dtr',
                    'id' => $s->id,
                    'title' => $s->dtr_name ?: 'DTR',
                    'subtitle' => trim(($s->dtr_code ?: '').' · '.($s->feeder_name ?: '')),
                    'status' => $s->status,
                    'status_label' => $s->status === 'approved'
                        ? 'DTR Already Surveyed'
                        : \App\Support\SurveyStatusLabel::label($s->status, 'dtr'),
                    'surveyed_at' => optional($s->surveyed_at)?->toDateTimeString(),
                    'can_edit' => $canMutate,
                    'can_delete' => $canMutate,
                ]);
            }
        }

        if ($type === 'all' || $type === 'consumer') {
            $q = ConsumerSurvey::query()
                ->with(['pole:id,pole_no', 'dtrSurvey:id,dtr_name,dtr_code,feeder_name'])
                ->where('surveyor_id', $user->id);
            if ($status !== '' && $status !== 'all') {
                $q->where('status', $status);
            }
            if ($from !== '') {
                $q->whereDate('surveyed_at', '>=', $from);
            }
            if ($to !== '') {
                $q->whereDate('surveyed_at', '<=', $to);
            }
            foreach ($q->latest('surveyed_at')->limit(200)->get() as $s) {
                $canMutate = in_array($s->status, ['pending_approval', 'rejected', 'saved', 'not_accessible'], true);
                $items->push([
                    'type' => 'consumer',
                    'id' => $s->id,
                    'title' => $s->consumer_name ?: ($s->ivrs ?: 'Consumer'),
                    'subtitle' => trim(($s->dtrSurvey?->dtr_name ?: '').' · Pole '.($s->pole?->pole_no ?: '—').($s->ivrs ? ' · '.$s->ivrs : '')),
                    'status' => $s->status,
                    'status_label' => \App\Support\SurveyStatusLabel::label($s->status, 'consumer'),
                    'surveyed_at' => optional($s->surveyed_at)?->toDateTimeString(),
                    'dtr_survey_id' => $s->dtr_survey_id,
                    'can_edit' => $canMutate,
                    'can_delete' => $canMutate,
                ]);
            }
        }

        $sorted = $items->sortByDesc(fn ($r) => $r['surveyed_at'] ?? '')->values();

        return response()->json([
            'data' => $sorted,
            'filters' => [
                'type' => $type,
                'status' => $status !== '' ? $status : 'all',
                'from' => $from,
                'to' => $to,
            ],
            'count' => $sorted->count(),
        ]);
    }

    public function surveyShow(DtrSurvey $survey)
    {
        abort_unless(SurveyScope::canView(Auth::user(), $survey), 403);
        $survey->load(['surveyor', 'region', 'circle', 'division', 'zone', 'substation', 'dtr', 'feeder']);

        $user = Auth::user();

        return response()->json([
            'survey' => $survey,
            'can_approve' => SurveyScope::canApprove($user, $survey),
            'can_unlock' => $user->canApproveSurveys() && SurveyScope::canView($user, $survey) && $survey->locked_at !== null,
            'can_edit' => $user->canApproveSurveys() && SurveyScope::canView($user, $survey),
            'can_delete' => $user->canApproveSurveys() && SurveyScope::canView($user, $survey),
        ]);
    }

    /** Manager / admin correction of DTR survey field mismatches (JSON, no photo upload). */
    public function managerUpdateSurvey(Request $request, DtrSurvey $survey)
    {
        $user = $request->user();
        abort_unless($user->canApproveSurveys() && SurveyScope::canView($user, $survey), 403);

        if ($request->filled('lt_line_type')) {
            $normalized = \App\Support\LtLineType::normalize($request->input('lt_line_type'));
            if ($normalized !== null) {
                $request->merge(['lt_line_type' => $normalized]);
            }
        }

        $data = $request->validate([
            'dtr_capacity_kva' => ['nullable', 'integer', 'min:1'],
            'dtr_condition' => ['nullable', Rule::in(['Normal', 'Good', 'Damaged', 'Leaning', 'Oil Leakage', 'Burnt', 'Other'])],
            'lt_line_type' => ['nullable', Rule::in(\App\Support\LtLineType::options())],
            'smart_meter_status' => ['nullable', Rule::in(['Installed', 'Not Installed', 'Meter Missing'])],
            'old_meter_condition' => ['nullable', Rule::in(['Healthy', 'Faulty', 'Defective', 'Burnt', 'Display Off', 'Missing', 'Removed'])],
            'old_msn' => ['nullable', 'string', 'max:100'],
            'old_meter_make' => ['nullable', 'string', 'max:100'],
            'new_msn' => ['nullable', 'string', 'max:100'],
            'new_meter_make' => ['nullable', Rule::in(['L&T Schneider', 'LNT', 'HPL', 'Visiontek'])],
            'new_meter_ct_ratio' => ['nullable', 'string', 'max:50'],
            'new_meter_mf' => ['nullable', 'string', 'max:50'],
            'observation' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'gps_accuracy' => ['nullable', 'numeric'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        // Map optional remarks → observation when client sends remarks for DTR
        if (array_key_exists('remarks', $data) && ! array_key_exists('observation', $data)) {
            $data['observation'] = $data['remarks'];
        }
        unset($data['remarks']);

        $survey->fill(array_filter($data, fn ($v) => $v !== null));
        $this->stripUnavailableDtrSurveyAttributes($survey);
        try {
            $survey->save();
        } catch (\Throwable $e) {
            report($e);
            $message = $this->dtrSurveySaveErrorMessage($e);

            return response()->json([
                'message' => $message,
                'error' => $message,
            ], 500);
        }
        ActivityLog::record('survey.manager_updated', $survey, ['by' => $user->id]);

        return response()->json([
            'message' => 'DTR survey updated.',
            'survey' => $survey->fresh(['surveyor', 'region', 'circle', 'division', 'zone', 'substation', 'dtr', 'feeder']),
        ]);
    }

    /** Manager / admin hard-delete a DTR survey (consumers + photos). */
    public function managerDeleteSurvey(Request $request, DtrSurvey $survey)
    {
        $user = $request->user();
        abort_unless($user->canApproveSurveys() && SurveyScope::canView($user, $survey), 403);

        $id = (int) $survey->id;
        \App\Support\DtrSurveyDeleter::delete($survey, $user);

        return response()->json(['message' => 'DTR survey deleted.', 'id' => $id]);
    }

    /** FE: delete own DTR survey before manager approval. */
    public function destroyOwnDtrSurvey(Request $request, DtrSurvey $survey)
    {
        $user = $request->user();
        abort_unless($user->isFieldExecutive() || $user->isAdmin(), 403);
        abort_unless(
            $user->isAdmin() || (int) $survey->surveyor_id === (int) $user->id,
            403,
            'You can only delete your own DTR surveys.'
        );
        abort_unless(
            in_array($survey->status, ['draft', 'pending_approval', 'rejected'], true) && $survey->locked_at === null,
            422,
            'Only draft / pending / rejected DTR surveys can be deleted before manager approval.'
        );

        $id = (int) $survey->id;
        \App\Support\DtrSurveyDeleter::delete($survey, $user);

        return response()->json(['message' => 'DTR survey deleted. You can survey again.', 'id' => $id]);
    }

    /** Find existing audit for this surveyor + DTR (blocks duplicate new audits). */
    public function surveyByDtr(Request $request)
    {
        abort_unless($request->user()->canCaptureSurveys(), 403);
        $dtrId = $request->integer('dtr_id');
        abort_unless($dtrId > 0, 422, 'dtr_id is required.');

        $survey = DtrSurvey::query()
            ->where('surveyor_id', $request->user()->id)
            ->where('dtr_id', $dtrId)
            ->latest()
            ->first();

        $entityDone = DtrSurvey::query()
            ->where('dtr_id', $dtrId)
            ->whereIn('status', ['pending_approval', 'approved', 'completed'])
            ->latest()
            ->first();

        return response()->json([
            'exists' => (bool) $survey || (bool) $entityDone,
            'survey' => $survey,
            'blocked' => $entityDone && (int) $entityDone->surveyor_id !== (int) $request->user()->id,
            'message' => $entityDone && (int) $entityDone->surveyor_id !== (int) $request->user()->id
                ? 'This DTR was already surveyed. Duplicate DTR survey is not allowed.'
                : null,
        ]);
    }

    public function hierarchy(Request $request)
    {
        $allowed = HierarchyScope::allowedZoneIds($request->user());
        $q = Region::where('is_active', true)->orderBy('name');
        if ($allowed !== null) {
            $regionIds = Zone::query()
                ->whereIn('id', $allowed)
                ->with('division.circle')
                ->get()
                ->map(fn (Zone $z) => $z->division?->circle?->region_id)
                ->filter()
                ->unique()
                ->values();
            $q->whereIn('id', $regionIds);
        }

        return response()->json([
            'regions' => $q->get(['id', 'name']),
        ]);
    }

    public function circles(Request $request)
    {
        $allowed = HierarchyScope::allowedZoneIds($request->user());
        $q = Circle::where('region_id', $request->integer('region_id'))->where('is_active', true)->orderBy('name');
        if ($allowed !== null) {
            $circleIds = Zone::query()
                ->whereIn('id', $allowed)
                ->with('division')
                ->get()
                ->map(fn (Zone $z) => $z->division?->circle_id)
                ->filter()
                ->unique()
                ->values();
            $q->whereIn('id', $circleIds);
        }

        return $q->get(['id', 'name']);
    }

    public function divisions(Request $request)
    {
        $allowed = HierarchyScope::allowedZoneIds($request->user());
        $q = Division::where('circle_id', $request->integer('circle_id'))->where('is_active', true)->orderBy('name');
        if ($allowed !== null) {
            $divisionIds = Zone::query()
                ->whereIn('id', $allowed)
                ->pluck('division_id')
                ->unique()
                ->values();
            $q->whereIn('id', $divisionIds);
        }

        return $q->get(['id', 'name']);
    }

    public function zones(Request $request)
    {
        $allowed = HierarchyScope::allowedZoneIds($request->user());
        $q = Zone::where('division_id', $request->integer('division_id'))->where('is_active', true)->orderBy('name');
        if ($allowed !== null) {
            $q->whereIn('id', $allowed);
        }

        return $q->get(['id', 'name', 'division_id']);
    }

    /** Zone with nested region/circle/division for FE autofill. */
    public function zoneAncestry(Request $request, Zone $zone)
    {
        $allowed = HierarchyScope::allowedZoneIds($request->user());
        if ($allowed !== null) {
            abort_unless($allowed->contains((int) $zone->id), 403, 'Zone not in your assigned scope.');
        }

        $zone->load(['division.circle.region']);

        return response()->json([
            'zone' => HierarchyScope::zonePayload($zone),
        ]);
    }

    public function substations(Request $request)
    {
        $zoneId = $request->integer('zone_id');
        HierarchyScope::assertZoneAllowed($request->user(), $zoneId);

        return Substation::where('zone_id', $zoneId)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }

    public function feeders(Request $request)
    {
        $user = $request->user();
        $query = Feeder::where('substation_id', $request->integer('substation_id'))
            ->where('is_active', true)
            ->orderBy('name');

        if ($user && $user->requiresFeederAssignment()) {
            $assigned = WorkAssignment::assignedFeederIdsFor($user);
            if ($assigned->isEmpty()) {
                return response()->json([]);
            }
            $query->whereIn('id', $assigned);
        }

        return $query->get(['id', 'name', 'code']);
    }

    public function dtrs(Request $request)
    {
        $feederId = $request->integer('feeder_id');
        $query = Dtr::where('feeder_id', $feederId)->where('is_active', true)->orderBy('name');

        // Active survey pickers: hide DTRs already surveyed (draft / pending / approved).
        // exclude_surveyed covers any entry_source; exclude_standalone kept for older clients.
        if ($feederId > 0 && ($request->boolean('exclude_surveyed') || $request->boolean('exclude_standalone'))) {
            $user = $request->user();
            $excludeIds = collect();

            if ($request->boolean('exclude_surveyed') && $user) {
                // Own in-progress or submitted surveys leave the active picker.
                $own = DtrSurvey::query()
                    ->where('feeder_id', $feederId)
                    ->where('surveyor_id', $user->id)
                    ->whereIn('status', ['draft', 'pending_approval', 'approved', 'completed'])
                    ->whereNotNull('dtr_id')
                    ->pluck('dtr_id');
                // Anyone else's submitted/approved survey blocks a duplicate for this DTR.
                $othersDone = DtrSurvey::query()
                    ->where('feeder_id', $feederId)
                    ->where('surveyor_id', '!=', $user->id)
                    ->whereIn('status', ['pending_approval', 'approved', 'completed'])
                    ->whereNotNull('dtr_id')
                    ->pluck('dtr_id');
                $excludeIds = $own->merge($othersDone);
            } elseif ($request->boolean('exclude_standalone')) {
                $excludeIds = DtrSurvey::query()
                    ->where('feeder_id', $feederId)
                    ->when($user && $user->requiresFeederAssignment(), fn ($q) => $q->where('surveyor_id', $user->id))
                    ->where('entry_source', DtrSurvey::ENTRY_STANDALONE)
                    ->whereIn('status', ['pending_approval', 'approved'])
                    ->whereNotNull('dtr_id')
                    ->pluck('dtr_id');
            }

            $excludeIds = $excludeIds->unique()->filter()->values();
            if ($excludeIds->isNotEmpty()) {
                $query->whereNotIn('id', $excludeIds);
            }
        }

        return $query->get(['id', 'name', 'code', 'capacity_kva']);
    }

    /**
     * Pre-check DTR code before create — used by Add New DTR Check / Check & Save.
     * Field is source of truth: same code under another feeder will be overwritten & remapped on save.
     */
    public function checkDtrCode(Request $request)
    {
        abort_unless($request->user()->canCaptureSurveys(), 403);

        $data = $request->validate([
            'feeder_id' => ['required', 'exists:feeders,id'],
            'code' => ['required', 'string', 'max:50'],
        ]);

        WorkAssignment::assertFeederAssigned($request->user(), (int) $data['feeder_id']);

        $code = trim($data['code']);
        $feederId = (int) $data['feeder_id'];
        $dtr = Dtr::query()
            ->with(['feeder.substation:id,name'])
            ->where('code', $code)
            ->first();

        if (! $dtr) {
            return response()->json([
                'exists' => false,
                'same_feeder' => false,
                'will_overwrite' => false,
                'mapping_correction_required' => false,
                'code' => $code,
                'dtr' => null,
                'mapped_feeder' => null,
                'substation' => null,
                'capacity' => null,
            ]);
        }

        $sameFeeder = (int) $dtr->feeder_id === $feederId;
        $mappedFeeder = $dtr->feeder;
        $substation = $mappedFeeder?->substation;

        return response()->json([
            'exists' => true,
            'same_feeder' => $sameFeeder,
            'will_overwrite' => ! $sameFeeder,
            // Softened: no longer blocks create — storeDtr remaps master to current feeder.
            'mapping_correction_required' => false,
            'code' => $dtr->code,
            'dtr' => $dtr->only(['id', 'name', 'code', 'capacity_kva', 'feeder_id']),
            'mapped_feeder' => $mappedFeeder ? [
                'id' => $mappedFeeder->id,
                'code' => $mappedFeeder->code,
                'name' => $mappedFeeder->name,
            ] : null,
            'substation' => $substation ? [
                'id' => $substation->id,
                'name' => $substation->name,
            ] : null,
            'capacity' => $dtr->capacity_kva,
            'previous_feeder_id' => (int) $dtr->feeder_id,
            'reported_feeder_id' => $feederId,
        ]);
    }

    /**
     * Field executive can add a missing DTR under the selected feeder.
     * Unique code conflict: overwrite master details and move feeder_id to current feeder
     * (field survey is source of truth). No 422 / mapping-correction pending on this path.
     */
    public function storeDtr(Request $request)
    {
        abort_unless($request->user()->canCaptureSurveys(), 403);

        $data = $request->validate([
            'feeder_id' => ['required', 'exists:feeders,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:190'],
            'capacity_kva' => ['nullable', 'integer', 'min:1'],
            // Accepted for older clients; ignored — overwrite/remap is the product rule.
            'mapping_correction' => ['nullable', 'boolean'],
        ]);

        WorkAssignment::assertFeederAssigned($request->user(), (int) $data['feeder_id']);

        $code = trim($data['code']);
        $feederId = (int) $data['feeder_id'];
        $name = trim($data['name']);
        $capacity = array_key_exists('capacity_kva', $data) ? $data['capacity_kva'] : null;

        $existing = Dtr::query()
            ->with(['feeder:id,code,name'])
            ->where('code', $code)
            ->first();

        if ($existing) {
            $previousFeederId = (int) $existing->feeder_id;
            $sameFeeder = $previousFeederId === $feederId;

            $existing->name = $name;
            if ($capacity !== null) {
                $existing->capacity_kva = $capacity;
            }
            $existing->feeder_id = $feederId;
            $existing->is_active = true;
            $existing->save();

            ActivityLog::record($sameFeeder ? 'dtr.updated_field' : 'dtr.remapped_field', $existing, [
                'by' => $request->user()->id,
                'previous_feeder_id' => $previousFeederId,
                'feeder_id' => $feederId,
                'same_feeder' => $sameFeeder,
            ]);

            return response()->json([
                'message' => $sameFeeder
                    ? 'DTR updated under this feeder.'
                    : 'DTR code existed under another feeder — details updated and mapped to current feeder.',
                'dtr' => $existing->only(['id', 'name', 'code', 'capacity_kva', 'feeder_id']),
                'mapping_correction' => false,
                'exists' => true,
                'same_feeder' => $sameFeeder,
                'remapped' => ! $sameFeeder,
                'previous_feeder_id' => $previousFeederId,
            ]);
        }

        $dtr = Dtr::create([
            'feeder_id' => $feederId,
            'code' => $code,
            'name' => $name,
            'capacity_kva' => $capacity,
            'is_active' => true,
        ]);

        ActivityLog::record('dtr.created_field', $dtr, [
            'by' => $request->user()->id,
            'feeder_id' => $dtr->feeder_id,
        ]);

        return response()->json([
            'message' => 'DTR added successfully.',
            'dtr' => $dtr->only(['id', 'name', 'code', 'capacity_kva', 'feeder_id']),
            'mapping_correction' => false,
            'exists' => false,
            'same_feeder' => false,
            'remapped' => false,
        ], 201);
    }

    /**
     * Compact hierarchy for Flutter / manager (Region → Feeder).
     * DTRs are NOT included — load via GET /hierarchy/dtrs?feeder_id=…
     */
    public function hierarchyBundle(Request $request)
    {
        @ini_set('memory_limit', '512M');

        // Feeders only — never eager-load 160k+ DTRs into one JSON payload.
        $regions = Region::query()
            ->where('is_active', true)
            ->with([
                'circles' => fn ($q) => $q->where('is_active', true)->orderBy('name')->with([
                    'divisions' => fn ($q) => $q->where('is_active', true)->orderBy('name')->with([
                        'zones' => fn ($q) => $q->where('is_active', true)->orderBy('name')->with([
                            'substations' => fn ($q) => $q->where('is_active', true)->orderBy('name')->with([
                                'feeders' => fn ($q) => $q->where('is_active', true)->orderBy('name')
                                    ->select(['id', 'substation_id', 'code', 'name', 'is_active']),
                            ]),
                        ]),
                    ]),
                ]),
            ])
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);

        $regions = HierarchyScope::pruneRegionTree($regions, $request->user());
        $allowed = HierarchyScope::allowedZoneIds($request->user());
        $assignedFeederIds = [];

        $user = $request->user();
        if ($user && $user->requiresFeederAssignment()) {
            $assignedFeederIds = WorkAssignment::assignedFeederIdsFor($user)->all();
            $regions = HierarchyScope::pruneToAssignedFeeders($regions, collect($assignedFeederIds));
        }

        // Ensure feeders expose an empty dtrs array so older clients don't NPE.
        $regions->each(function ($region) {
            foreach ($region->circles ?? [] as $circle) {
                foreach ($circle->divisions ?? [] as $division) {
                    foreach ($division->zones ?? [] as $zone) {
                        foreach ($zone->substations ?? [] as $ss) {
                            foreach ($ss->feeders ?? [] as $feeder) {
                                $feeder->setRelation('dtrs', collect());
                            }
                        }
                    }
                }
            }
        });

        return response()->json([
            'regions' => $regions,
            'assigned_zone_ids' => $allowed?->values()->all(),
            'assigned_feeder_ids' => $assignedFeederIds,
            'includes_dtrs' => false,
            'cached_at' => now()->toIso8601String(),
        ]);
    }

    public function surveyOptions()
    {
        return response()->json([
            'dtr_conditions' => ['Normal', 'Good', 'Damaged', 'Leaning', 'Oil Leakage', 'Burnt', 'Other'],
            'smart_meter_statuses' => ['Installed', 'Not Installed', 'Meter Missing'],
            'old_meter_conditions' => ['Healthy', 'Faulty', 'Defective', 'Burnt', 'Display Off', 'Missing', 'Removed'],
            'new_meter_makes' => ['L&T Schneider', 'HPL', 'Visiontek'],
            'consumer_meter_makes' => \App\Support\MeterMakeFromMsn::allowedMakes(),
            'lt_line_types' => \App\Support\LtLineType::options(),
            'old_meter_makes' => ['SECURE', 'Secure', 'HPL', 'Visiontek', 'Other'],
            'feeder_voltages' => ['11 KV', '33 KV'],
            'metering_types' => ['Output Feeder', 'Input Feeder'],
            'yes_no' => ['Yes', 'No'],
            'ct_ratios' => ['100/5', '150/5', '200/5', '300/5'],
            'smart_meter_installed' => ['Yes', 'No', 'Meter Not Available'],
            'feeder_old_makes' => ['L&T Schneider', 'Secure', 'HPL', 'Visiontek', 'Other'],
        ]);
    }

    public function storeSurvey(Request $request)
    {
        abort_unless($request->user()->canCaptureSurveys(), 403);

        try {
            $data = $this->filterDtrSurveyColumns($this->validateSurvey($request));
            $user = $request->user();
            HierarchyScope::assertZoneAllowed($user, (int) $data['zone_id']);
            WorkAssignment::assertFeederAssigned($user, (int) $data['feeder_id']);

            $feeder = Feeder::findOrFail($data['feeder_id']);
            $dtr = Dtr::findOrFail($data['dtr_id']);

            // Normalize entry source: feeder flow vs standalone (DTR→Consumer hub).
            $entrySource = $data['entry_source'] ?? null;
            $feederSurveyId = $data['feeder_survey_id'] ?? null;
            if ($feederSurveyId && ! $entrySource) {
                $entrySource = DtrSurvey::ENTRY_FEEDER;
            }
            if (! $entrySource) {
                $entrySource = DtrSurvey::ENTRY_STANDALONE;
                $feederSurveyId = null;
            }
            if ($entrySource === DtrSurvey::ENTRY_STANDALONE) {
                $feederSurveyId = null;
            }
            $data['entry_source'] = $entrySource;
            $data['feeder_survey_id'] = $feederSurveyId;
            $data = $this->filterDtrSurveyColumns($data);

            // Entity-level: DTR already submitted/approved by anyone — no second survey.
            $entityDone = DtrSurvey::query()
                ->where('dtr_id', $dtr->id)
                ->whereIn('status', ['pending_approval', 'approved', 'completed'])
                ->latest()
                ->first();
            if ($entityDone) {
                return response()->json([
                    'message' => 'This DTR was already surveyed. Duplicate DTR survey is not allowed.',
                    'existing_survey_id' => (int) $entityDone->surveyor_id === (int) $user->id ? $entityDone->id : null,
                    'survey' => (int) $entityDone->surveyor_id === (int) $user->id
                        ? $entityDone->load(['surveyor', 'region', 'circle', 'division', 'zone', 'substation'])
                        : null,
                ], 409);
            }

            // Feeder→DTR must not redo a DTR already submitted via standalone path.
            if ($entrySource === DtrSurvey::ENTRY_FEEDER && Schema::hasColumn('dtr_surveys', 'entry_source')) {
                $standaloneDone = DtrSurvey::query()
                    ->where('surveyor_id', $user->id)
                    ->where('dtr_id', $dtr->id)
                    ->where('entry_source', DtrSurvey::ENTRY_STANDALONE)
                    ->whereIn('status', ['pending_approval', 'approved'])
                    ->latest()
                    ->first();
                if ($standaloneDone) {
                    return response()->json([
                        'message' => 'This DTR was already surveyed from DTR → Consumer (standalone). It cannot be surveyed again under Feeder → DTR.',
                        'existing_survey_id' => $standaloneDone->id,
                        'survey' => $standaloneDone->load(['surveyor', 'region', 'circle', 'division', 'zone', 'substation']),
                    ], 409);
                }
            }

            // One DTR audit per surveyor — edit existing only, no fresh start
            $existing = DtrSurvey::query()
                ->where('surveyor_id', $user->id)
                ->where('dtr_id', $dtr->id)
                ->latest()
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'This DTR was already audited. Open the existing survey to edit and resubmit for review.',
                    'existing_survey_id' => $existing->id,
                    'survey' => $existing->load(['surveyor', 'region', 'circle', 'division', 'zone', 'substation']),
                ], 409);
            }

            $survey = new DtrSurvey($data);
            $survey->surveyor_id = $user->id;
            $survey->supervisor_id = $user->supervisor_id;
            $survey->surveyed_at = now();
            $survey->feeder_code = $feeder->code;
            $survey->feeder_name = $feeder->name;
            $survey->dtr_code = $dtr->code;
            $survey->dtr_name = $dtr->name;
            if (Schema::hasColumn('dtr_surveys', 'entry_source')) {
                $survey->entry_source = $entrySource;
            }
            if (Schema::hasColumn('dtr_surveys', 'feeder_survey_id')) {
                $survey->feeder_survey_id = $feederSurveyId;
            }
            $this->applyMappingCorrectionFlags($request, $survey, $dtr, $feeder);
            $isSubmit = $request->input('action') === 'submit';
            $this->applyDtrSubmitStatus($survey, $isSubmit);
            $this->applyPhotos($request, $survey);
            $this->clearOldMeterIfNeeded($survey, $data['smart_meter_status']);

            if ($isSubmit) {
                $this->assertPhotos($survey);
            }

            $this->stripUnavailableDtrSurveyAttributes($survey);
            $survey->save();
            ActivityLog::record($isSubmit ? 'survey.submitted' : 'survey.draft', $survey);
            if ($survey->isMappingCorrectionPending()) {
                ActivityLog::record('dtr.mapping_correction.pending', $survey, [
                    'master_feeder_id' => $survey->master_feeder_id,
                    'reported_feeder_id' => $survey->reported_feeder_id,
                ]);
            }
            if ($isSubmit) {
                $this->notifyApprovers($survey);
            }

            return response()->json([
                'message' => $isSubmit
                    ? 'DTR survey submitted for manager approval. You can start Consumer Survey.'
                    : 'Draft saved successfully.',
                'survey' => $survey->fresh(['surveyor', 'region', 'circle', 'division', 'zone', 'substation']),
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            $message = $this->dtrSurveySaveErrorMessage($e);

            return response()->json([
                'message' => $message,
                'error' => $message,
            ], 500);
        }
    }

    public function updateSurvey(Request $request, DtrSurvey $survey)
    {
        try {
            $this->authorizeSurveyEdit($request->user(), $survey);
            $data = $this->filterDtrSurveyColumns($this->validateSurvey($request, $survey));
            HierarchyScope::assertZoneAllowed($request->user(), (int) $data['zone_id']);
            WorkAssignment::assertFeederAssigned($request->user(), (int) $data['feeder_id']);

            $feeder = Feeder::findOrFail($data['feeder_id']);
            $dtr = Dtr::findOrFail($data['dtr_id']);

            $survey->fill($data);
            $survey->feeder_code = $feeder->code;
            $survey->feeder_name = $feeder->name;
            $survey->dtr_code = $dtr->code;
            $survey->dtr_name = $dtr->name;
            $survey->review_remarks = null;
            $this->applyMappingCorrectionFlags($request, $survey, $dtr, $feeder);
            $isSubmit = $request->input('action') === 'submit';
            $this->applyDtrSubmitStatus($survey, $isSubmit);
            $this->applyPhotos($request, $survey);
            $this->clearOldMeterIfNeeded($survey, $data['smart_meter_status']);

            if ($isSubmit) {
                $this->assertPhotos($survey);
            }

            $this->stripUnavailableDtrSurveyAttributes($survey);
            $survey->save();
            ActivityLog::record($isSubmit ? 'survey.resubmitted' : 'survey.updated', $survey);

            if ($isSubmit) {
                // Clear FE reject / re-survey notifications once they resubmit.
                AppNotification::clearForSubject(
                    (int) $survey->surveyor_id,
                    DtrSurvey::class,
                    (int) $survey->id
                );
                $this->notifyApprovers($survey);
            }

            return response()->json([
                'message' => $isSubmit
                    ? 'DTR survey submitted for manager approval. You can start Consumer Survey.'
                    : 'Draft updated successfully.',
                'survey' => $survey->fresh(['surveyor', 'region', 'circle', 'division', 'zone', 'substation']),
            ]);
        } catch (ValidationException $e) {
            throw $e;
        } catch (HttpExceptionInterface $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            $message = $this->dtrSurveySaveErrorMessage($e);

            return response()->json([
                'message' => $message,
                'error' => $message,
            ], 500);
        }
    }

    public function submitSurvey(Request $request, DtrSurvey $survey)
    {
        $request->merge(['action' => 'submit']);

        return $this->updateSurvey($request, $survey);
    }

    private function validateSurvey(Request $request, ?DtrSurvey $survey = null): array
    {
        $isSubmit = $request->input('action') === 'submit';
        $meterStatus = trim((string) $request->input('smart_meter_status', ''));
        $meterMissing = $meterStatus === 'Meter Missing';

        // Normalize LT line type (legacy OH/OG → Under Ground / Over Ground).
        if ($request->filled('lt_line_type')) {
            $normalized = \App\Support\LtLineType::normalize($request->input('lt_line_type'));
            if ($normalized !== null) {
                $request->merge(['lt_line_type' => $normalized]);
            }
        }

        $needOverallPhoto = $isSubmit && ! $survey?->dtr_overall_photo;
        $needMeterPhoto = $isSubmit && ! $meterMissing && ! $survey?->smart_meter_photo;

        $rules = [
            'region_id' => ['required', 'exists:regions,id'],
            'circle_id' => ['required', 'exists:circles,id'],
            'division_id' => ['required', 'exists:divisions,id'],
            'zone_id' => ['required', 'exists:zones,id'],
            'substation_id' => ['required', 'exists:substations,id'],
            'feeder_id' => ['required', 'exists:feeders,id'],
            'dtr_id' => ['required', 'exists:dtrs,id'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'gps_accuracy' => ['nullable', 'numeric'],
            'dtr_capacity_kva' => ['nullable', 'integer', 'min:1'],
            'dtr_condition' => ['required', Rule::in(['Normal', 'Good', 'Damaged', 'Leaning', 'Oil Leakage', 'Burnt', 'Other'])],
            // Require LT line type on submit only when the prod column exists.
            'lt_line_type' => [
                ($isSubmit && Schema::hasColumn('dtr_surveys', 'lt_line_type')) ? 'required' : 'nullable',
                Rule::in(\App\Support\LtLineType::options()),
            ],
            'smart_meter_status' => ['required', Rule::in(['Installed', 'Not Installed', 'Meter Missing'])],
            'old_meter_condition' => ['nullable', Rule::in(['Healthy', 'Faulty', 'Defective', 'Burnt', 'Display Off', 'Missing', 'Removed'])],
            'old_msn' => ['nullable', 'string', 'max:100'],
            'old_meter_make' => ['nullable', 'string', 'max:100'],
            'new_msn' => ['nullable', 'string', 'max:100'],
            'new_meter_make' => ['nullable', Rule::in(['L&T Schneider', 'LNT', 'HPL', 'Visiontek'])],
            'new_meter_ct_ratio' => ['nullable', 'string', 'max:50'],
            'new_meter_mf' => ['nullable', 'string', 'max:50'],
            'observation' => ['nullable', 'string', 'max:500'],
            'entry_source' => ['nullable', Rule::in([DtrSurvey::ENTRY_STANDALONE, DtrSurvey::ENTRY_FEEDER])],
            'feeder_survey_id' => ['nullable', 'integer', 'exists:feeder_surveys,id'],
            'mapping_correction' => ['nullable', 'boolean'],
            'master_feeder_id' => ['nullable', 'integer', 'exists:feeders,id'],
            'reported_feeder_id' => ['nullable', 'integer', 'exists:feeders,id'],
            'field_dtr_name' => ['nullable', 'string', 'max:190'],
            'dtr_overall_photo' => [$needOverallPhoto ? 'required' : 'nullable', 'file', 'max:5120', new ClientImageFile],
            'smart_meter_photo' => [$needMeterPhoto ? 'required' : 'nullable', 'file', 'max:5120', new ClientImageFile],
            'ct_ratio_photo' => ['nullable', 'file', 'max:5120', new ClientImageFile],
        ];

        if ($meterStatus === 'Not Installed') {
            $rules['old_meter_condition'] = ['required', Rule::in(['Healthy', 'Faulty', 'Defective', 'Burnt', 'Display Off', 'Missing', 'Removed'])];
            $rules['old_msn'] = ['required', 'string', 'max:100'];
            $rules['old_meter_make'] = ['required', 'string', 'max:100'];
        }

        if (in_array($meterStatus, ['Installed', 'Not Installed'], true) && $isSubmit) {
            $rules['new_msn'] = ['required', 'string', 'max:100'];
            $rules['new_meter_make'] = ['required', Rule::in(['L&T Schneider', 'LNT', 'HPL', 'Visiontek'])];
            $rules['new_meter_ct_ratio'] = ['required', 'string', 'max:50'];
            $rules['new_meter_mf'] = ['required', 'string', 'max:50'];
        }

        return $request->validate($rules);
    }

    private function applyPhotos(Request $request, DtrSurvey $survey): void
    {
        if ($request->hasFile('dtr_overall_photo')) {
            $survey->dtr_overall_photo = $this->storeSurveyPhoto($request->file('dtr_overall_photo'), 'surveys/dtr');
        }
        if ($request->hasFile('smart_meter_photo')) {
            $survey->smart_meter_photo = $this->storeSurveyPhoto($request->file('smart_meter_photo'), 'surveys/meters');
        }
        if ($request->hasFile('ct_ratio_photo') && Schema::hasColumn('dtr_surveys', 'ct_ratio_photo')) {
            $survey->ct_ratio_photo = $this->storeSurveyPhoto($request->file('ct_ratio_photo'), 'surveys/ct');
        }
    }

    private function clearOldMeterIfNeeded(DtrSurvey $survey, string $status): void
    {
        if ($status !== 'Not Installed') {
            $survey->old_meter_condition = null;
            $survey->old_msn = null;
            $survey->old_meter_make = null;
        }
    }

    private function assertPhotos(DtrSurvey $survey): void
    {
        if (! $survey->dtr_overall_photo) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'photos' => ['DTR Overall Photo is mandatory for submission.'],
            ]);
        }

        // Meter Missing: smart meter photo is optional (no meter to photograph).
        if ($survey->smart_meter_status !== 'Meter Missing' && ! $survey->smart_meter_photo) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'photos' => ['Smart Meter Photo is mandatory for submission (unless Meter Missing).'],
            ]);
        }
    }

    private function authorizeSurveyEdit(User $user, DtrSurvey $survey): void
    {
        if ($user->isAdmin()) {
            return;
        }
        if (! $user->isFieldExecutive() || (int) $survey->surveyor_id !== (int) $user->id || ! $survey->isEditable()) {
            abort(403, 'You cannot edit this survey.');
        }
    }

    /**
     * DTR submit goes to manager approval (auto-approve disabled).
     * Consumer survey remains allowed while pending_approval / approved.
     */
    private function applyDtrSubmitStatus(DtrSurvey $survey, bool $isSubmit): void
    {
        if ($isSubmit) {
            $survey->status = 'pending_approval';
            $survey->reviewed_at = null;
            if (Schema::hasColumn('dtr_surveys', 'locked_at')) {
                $survey->locked_at = null;
            }

            return;
        }

        $survey->status = 'draft';
        $survey->reviewed_at = null;
        if (Schema::hasColumn('dtr_surveys', 'locked_at')) {
            $survey->locked_at = null;
        }
    }

    /**
     * Flag survey as mapping correction when client confirms mismatch.
     * Does not move dtrs.feeder_id — admin approves later.
     * No-op when mapping_correction_* columns are missing on production.
     */
    private function applyMappingCorrectionFlags(Request $request, DtrSurvey $survey, Dtr $dtr, Feeder $reportedFeeder): void
    {
        if (! Schema::hasColumn('dtr_surveys', 'mapping_correction_status')) {
            return;
        }

        $explicit = $request->boolean('mapping_correction');

        // Keep approved/rejected unless surveyor explicitly re-opens correction.
        if (! $explicit
            && in_array($survey->mapping_correction_status, [DtrSurvey::MAPPING_APPROVED, DtrSurvey::MAPPING_REJECTED], true)) {
            return;
        }

        if (! $explicit && ! $survey->isMappingCorrectionPending()) {
            return;
        }

        $masterFeederId = $request->filled('master_feeder_id')
            ? (int) $request->input('master_feeder_id')
            : (int) $dtr->feeder_id;
        $reportedFeederId = $request->filled('reported_feeder_id')
            ? (int) $request->input('reported_feeder_id')
            : (int) $reportedFeeder->id;

        if ($masterFeederId <= 0 || $reportedFeederId <= 0 || $masterFeederId === $reportedFeederId) {
            return;
        }

        $survey->mapping_correction_status = DtrSurvey::MAPPING_PENDING;
        if (Schema::hasColumn('dtr_surveys', 'master_feeder_id')) {
            $survey->master_feeder_id = $masterFeederId;
        }
        if (Schema::hasColumn('dtr_surveys', 'reported_feeder_id')) {
            $survey->reported_feeder_id = $reportedFeederId;
        }
        if ($request->filled('field_dtr_name') && Schema::hasColumn('dtr_surveys', 'field_dtr_name')) {
            $survey->field_dtr_name = (string) $request->input('field_dtr_name');
        }
    }

    /**
     * Drop newer optional keys from validated payload when columns are absent on prod.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterDtrSurveyColumns(array $data): array
    {
        foreach ($this->dtrSurveyOptionalColumns() as $column) {
            if (array_key_exists($column, $data) && ! Schema::hasColumn('dtr_surveys', $column)) {
                unset($data[$column]);
            }
        }

        // mapping_correction is request-only (not a DB column).
        unset($data['mapping_correction']);

        return $data;
    }

    /** Remove dirty attributes that do not exist as DB columns before save. */
    private function stripUnavailableDtrSurveyAttributes(DtrSurvey $survey): void
    {
        foreach ($this->dtrSurveyOptionalColumns() as $column) {
            if (! Schema::hasColumn('dtr_surveys', $column) && array_key_exists($column, $survey->getAttributes())) {
                unset($survey->$column);
            }
        }
    }

    /** @return list<string> */
    private function dtrSurveyOptionalColumns(): array
    {
        return [
            'lt_line_type',
            'ct_ratio_photo',
            'entry_source',
            'feeder_survey_id',
            'locked_at',
            'consumer_survey_completed_at',
            'mapping_correction_status',
            'master_feeder_id',
            'reported_feeder_id',
            'field_dtr_name',
            'mapping_correction_remarks',
            'mapping_correction_reviewed_at',
            'mapping_correction_reviewed_by',
        ];
    }

    private function dtrSurveySaveErrorMessage(\Throwable $e): string
    {
        $raw = $e->getMessage();

        if ($e instanceof QueryException || str_contains($raw, 'Unknown column')) {
            if (preg_match("/Unknown column ['`]([^'`]+)['`]/i", $raw, $m)) {
                return 'Database missing column `'.$m[1].'` on dtr_surveys. Run FIX-dtr-submit-missing-columns.sql in phpMyAdmin, then retry submit.';
            }

            return 'Database schema mismatch on dtr_surveys (likely missing lt_line_type or mapping_correction_* columns). Run FIX-dtr-submit-missing-columns.sql, then retry.';
        }

        if (str_contains($raw, 'finfo') || str_contains($raw, 'Fileinfo') || str_contains($raw, 'mime')) {
            return 'Photo processing failed (PHP fileinfo/GD). Check hosting PHP extensions and storage permissions.';
        }

        $short = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);
        if (strlen($short) > 220) {
            $short = substr($short, 0, 217).'...';
        }

        return $short !== '' ? 'Server error while saving DTR survey: '.$short : 'Server error while saving DTR survey.';
    }

    private function notifyApprovers(DtrSurvey $survey): void
    {
        $managerIds = User::query()
            ->whereIn('role', ['manager', 'project_manager', 'admin', 'super_admin'])
            ->where('is_active', true)
            ->pluck('id');

        foreach ($managerIds as $id) {
            if ((int) $id === (int) $survey->surveyor_id) {
                continue;
            }
            AppNotification::notifyUser(
                $id,
                'New survey pending approval',
                $survey->dtr_name.' · '.$survey->feeder_name,
                null,
                DtrSurvey::class,
                (int) $survey->id,
            );
        }
    }

    /** DTR ready for consumer work after field submit (pending or approved). */
    private function dtrReadyForConsumer(?DtrSurvey $survey): bool
    {
        if (! $survey || $survey->consumer_survey_completed_at) {
            return false;
        }

        // approved = auto-approve on submit; pending_approval = legacy; completed = still open for consumer.
        return in_array($survey->status, ['approved', 'pending_approval', 'completed'], true);
    }

    public function pendingApprovals(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canApproveSurveys(), 403);

        // Approvals inbox: Feeder SLD, DTR surveys, and Consumer.
        $type = trim((string) $request->query('type', 'all'));
        if (! in_array($type, ['all', 'feeder', 'dtr', 'consumer'], true)) {
            $type = 'all';
        }

        $feederBase = SurveyScope::apply(FeederSurvey::query()->with('surveyor:id,name,email'), $user);
        $dtrBase = SurveyScope::apply(DtrSurvey::query()->with('surveyor:id,name,email'), $user);
        $consumerBase = \App\Support\ConsumerSurveyApproval::baseQuery($user);

        $feederPending = (clone $feederBase)->whereIn('status', ['pending_approval', 'sld_pending'])->count();
        $dtrPending = (clone $dtrBase)->where('status', 'pending_approval')->count();
        $consumerPending = (clone $consumerBase)->where('consumer_surveys.status', 'pending_approval')->count();

        $summary = [
            'total' => (clone $feederBase)->count() + (clone $dtrBase)->count() + (clone $consumerBase)->count(),
            'feeder' => (clone $feederBase)->count(),
            'dtr' => (clone $dtrBase)->count(),
            'consumer' => (clone $consumerBase)->count(),
            'feeder_pending' => $feederPending,
            'dtr_pending' => $dtrPending,
            'consumer_pending' => $consumerPending,
        ];

        $items = collect();

        if ($type === 'all' || $type === 'feeder') {
            $feeders = (clone $feederBase)
                ->whereIn('status', ['pending_approval', 'sld_pending'])
                ->latest()
                ->limit(100)
                ->get();
            foreach ($feeders as $s) {
                $items->push([
                    'type' => 'feeder',
                    'type_label' => 'Feeder SLD',
                    'id' => $s->id,
                    'title' => $s->feeder_name ?: 'Feeder',
                    'subtitle' => trim(($s->surveyor?->name ?: 'Surveyor').' · '.($s->feeder_code ?: '')),
                    'feeder_name' => $s->feeder_name,
                    'feeder_code' => $s->feeder_code,
                    'status' => $s->status,
                    'status_label' => $s->display_status ?: \App\Support\SurveyStatusLabel::label($s->status, 'feeder'),
                    'surveyed_at' => optional($s->surveyed_at)?->toDateTimeString(),
                    'surveyor' => $s->surveyor ? [
                        'id' => $s->surveyor->id,
                        'name' => $s->surveyor->name,
                        'email' => $s->surveyor->email,
                    ] : null,
                ]);
            }
        }

        if ($type === 'all' || $type === 'dtr') {
            $dtrs = (clone $dtrBase)
                ->where('status', 'pending_approval')
                ->latest()
                ->limit(100)
                ->get();
            foreach ($dtrs as $s) {
                $items->push([
                    'type' => 'dtr',
                    'type_label' => 'DTR',
                    'id' => $s->id,
                    'title' => $s->dtr_name ?: 'DTR',
                    'subtitle' => trim(($s->surveyor?->name ?: 'Surveyor').' · '.($s->dtr_code ?: '').' · '.($s->feeder_name ?: '')),
                    'dtr_name' => $s->dtr_name,
                    'dtr_code' => $s->dtr_code,
                    'feeder_name' => $s->feeder_name,
                    'status' => $s->status,
                    'status_label' => \App\Support\SurveyStatusLabel::label($s->status, 'dtr'),
                    'surveyed_at' => optional($s->surveyed_at)?->toDateTimeString(),
                    'surveyor' => $s->surveyor ? [
                        'id' => $s->surveyor->id,
                        'name' => $s->surveyor->name,
                        'email' => $s->surveyor->email,
                    ] : null,
                ]);
            }
        }

        if ($type === 'all' || $type === 'consumer') {
            $consumers = (clone $consumerBase)
                ->where('consumer_surveys.status', 'pending_approval')
                ->latest('consumer_surveys.surveyed_at')
                ->limit(100)
                ->get();
            foreach ($consumers as $s) {
                $row = \App\Support\ConsumerSurveyApproval::apiRow($s);
                $items->push([
                    'type' => 'consumer',
                    'type_label' => 'Consumer',
                    'id' => $s->id,
                    'title' => $s->consumer_name ?: ($s->ivrs ?: 'Consumer'),
                    'subtitle' => trim(($s->surveyor?->name ?: 'Surveyor').' · '.($row['dtr_name'] ?? '').($s->ivrs ? ' · '.$s->ivrs : '')),
                    'consumer_name' => $s->consumer_name,
                    'ivrs' => $s->ivrs,
                    'dtr_name' => $row['dtr_name'] ?? null,
                    'feeder_name' => $row['feeder_name'] ?? null,
                    'status' => $s->status,
                    'status_label' => \App\Support\SurveyStatusLabel::label($s->status, 'consumer'),
                    'surveyed_at' => optional($s->surveyed_at)?->toDateTimeString(),
                    'surveyor' => $s->surveyor ? [
                        'id' => $s->surveyor->id,
                        'name' => $s->surveyor->name,
                        'email' => $s->surveyor->email,
                    ] : null,
                ]);
            }
        }

        $sorted = $items->sortByDesc(fn ($r) => $r['surveyed_at'] ?? '')->values();

        return response()->json([
            'summary' => $summary,
            'filter' => $type,
            'data' => $sorted,
            // Backward-compatible paginator-ish keys for older clients.
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => $sorted->count(),
            'total' => $sorted->count(),
        ]);
    }

    public function approve(Request $request, DtrSurvey $survey)
    {
        abort_unless(SurveyScope::canApprove($request->user(), $survey), 403);
        $request->validate(['review_remarks' => ['nullable', 'string', 'max:1000']]);
        $survey->update([
            'status' => 'approved',
            'review_remarks' => $request->review_remarks,
            'reviewed_at' => now(),
            'locked_at' => now(),
        ]);
        AppNotification::notifyUser(
            (int) $survey->surveyor_id,
            'Survey approved',
            $survey->dtr_name.' · '.$survey->feeder_name,
            null,
            DtrSurvey::class,
            (int) $survey->id
        );

        return response()->json(['message' => 'Approved', 'survey' => $survey->fresh()]);
    }

    public function reject(Request $request, DtrSurvey $survey)
    {
        abort_unless(SurveyScope::canApprove($request->user(), $survey), 403);
        $data = $request->validate(['review_remarks' => ['required', 'string', 'min:1', 'max:1000']]);
        $survey->update([
            'status' => 'rejected',
            'review_remarks' => $data['review_remarks'],
            'reviewed_at' => now(),
            'locked_at' => null,
        ]);

        $body = 'DTR '.$survey->dtr_name.' ('.$survey->dtr_code.') survey was rejected. '
            .'Please re-survey this DTR and submit again. Reason: '.$data['review_remarks'];

        AppNotification::notifyUser(
            (int) $survey->surveyor_id,
            'Survey rejected — re-survey required',
            $body,
            null,
            DtrSurvey::class,
            (int) $survey->id
        );

        return response()->json(['message' => 'Rejected', 'survey' => $survey->fresh()]);
    }

    /** Manager unlock so surveyor can rework after pending/approved lock. */
    public function unlockSurvey(Request $request, DtrSurvey $survey)
    {
        $user = $request->user();
        abort_unless($user->canApproveSurveys() && SurveyScope::canView($user, $survey), 403);
        abort_unless($survey->locked_at !== null, 422, 'Survey is not locked.');

        $survey->unlock();
        ActivityLog::record('survey.unlocked', $survey, ['by' => $user->id]);

        return response()->json([
            'message' => 'DTR survey unlocked. Surveyor can rework until it is locked again.',
            'survey' => $survey->fresh(['surveyor', 'region', 'circle', 'division', 'zone', 'substation', 'dtr', 'feeder']),
        ]);
    }

    public function approvedForConsumer(Request $request)
    {
        $user = $request->user();
        $base = DtrSurvey::with(['dtr.poles', 'region', 'circle', 'division', 'zone', 'substation', 'feeder']);
        $readyStatuses = ['approved', 'pending_approval', 'completed'];
        $approvedAll = (clone $base)->whereIn('status', $readyStatuses)->count();
        $approvedOpen = (clone $base)->whereIn('status', $readyStatuses)->whereNull('consumer_survey_completed_at')->count();
        $q = SurveyScope::apply(
            (clone $base)
                ->whereIn('status', $readyStatuses)
                ->whereNull('consumer_survey_completed_at'),
            $user
        );

        if ($request->filled('zone_id')) {
            $q->where('zone_id', $request->integer('zone_id'));
        }
        if ($request->filled('substation_id')) {
            $q->where('substation_id', $request->integer('substation_id'));
        }
        if ($request->filled('feeder_id')) {
            $q->where('feeder_id', $request->integer('feeder_id'));
        }
        if ($search = trim((string) $request->query('q', ''))) {
            $q->where(function ($query) use ($search) {
                $query->where('dtr_name', 'like', "%{$search}%")
                    ->orWhere('dtr_code', 'like', "%{$search}%")
                    ->orWhere('feeder_name', 'like', "%{$search}%")
                    ->orWhere('feeder_code', 'like', "%{$search}%");
            });
        }

        $surveys = $q->latest()->paginate(min(100, max(20, $request->integer('per_page', 80))));

        // #region agent log
        try {
            $payload = json_encode([
                'sessionId' => 'a2382b',
                'runId' => 'pre-fix',
                'hypothesisId' => 'A',
                'location' => 'FieldApiController.php:approvedForConsumer',
                'message' => 'approvedForConsumer filter counts',
                'data' => [
                    'user_id' => $user?->id,
                    'approved_all' => $approvedAll,
                    'approved_open' => $approvedOpen,
                    'scoped_total' => $surveys->total(),
                    'page_count' => $surveys->count(),
                    'dtrs_on_selected_feeder' => $request->filled('feeder_id')
                        ? \App\Models\Dtr::where('feeder_id', $request->integer('feeder_id'))->count()
                        : \App\Models\Dtr::query()->count(),
                    'rows' => collect($surveys->items())->map(fn ($s) => [
                        'id' => $s->id,
                        'dtr_name' => $s->dtr_name,
                        'feeder_id' => $s->feeder_id,
                        'completed_at' => $s->consumer_survey_completed_at,
                    ])->values()->all(),
                ],
                'timestamp' => (int) (microtime(true) * 1000),
            ], JSON_UNESCAPED_SLASHES);
            file_put_contents(dirname(base_path()).DIRECTORY_SEPARATOR.'debug-a2382b.log', $payload.PHP_EOL, FILE_APPEND);
        } catch (\Throwable $e) {
            // ignore debug log failures
        }
        // #endregion

        return response()->json($surveys);
    }

    /**
     * All DTRs under a feeder for Consumer Survey picker.
     * Includes pending/completed/no-survey rows so the FE sees every DTR — not only open approved.
     */
    public function feederDtrsForConsumer(Request $request)
    {
        abort_unless($request->user()->canCaptureSurveys() || $request->user()->isAdmin(), 403);

        $data = $request->validate([
            'feeder_id' => ['required', 'integer', 'exists:feeders,id'],
        ]);
        $feederId = (int) $data['feeder_id'];

        $dtrs = Dtr::query()
            ->where('feeder_id', $feederId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'feeder_id', 'capacity_kva']);

        $surveys = SurveyScope::apply(
            DtrSurvey::with(['dtr.poles', 'region', 'circle', 'division', 'zone', 'substation', 'feeder'])
                ->where('feeder_id', $feederId),
            $request->user()
        )->latest()->get()->groupBy('dtr_id');

        $pendingReactivations = collect();
        if (DtrConsumerReopen::tableReady()) {
            $surveyIds = $surveys->flatten()->pluck('id')->filter()->unique()->values();
            if ($surveyIds->isNotEmpty()) {
                $pendingReactivations = DtrReactivationRequest::query()
                    ->whereIn('dtr_survey_id', $surveyIds)
                    ->where('status', DtrReactivationRequest::STATUS_PENDING)
                    ->pluck('id', 'dtr_survey_id');
            }
        }

        $rows = $dtrs->map(function (Dtr $dtr) use ($surveys, $pendingReactivations) {
            $list = $surveys->get($dtr->id) ?? collect();
            $ready = $list->first(fn (DtrSurvey $s) => $this->dtrReadyForConsumer($s));
            $latest = $list->first();

            $statusKey = 'no_survey';
            if ($ready) {
                $statusKey = 'ready';
            } elseif ($latest && in_array($latest->status, ['approved', 'pending_approval', 'completed'], true) && $latest->consumer_survey_completed_at) {
                $statusKey = 'completed';
            } elseif ($latest && $latest->status === 'rejected') {
                $statusKey = 'rejected';
            } elseif ($latest && in_array($latest->status, ['pending_approval', 'pending'], true)) {
                // Legacy label for chips; ready path above should usually catch submitted DTRs.
                $statusKey = 'pending';
            } elseif ($latest) {
                $statusKey = (string) $latest->status;
            }

            $surveyPayload = null;
            if ($ready) {
                $surveyPayload = $ready->toArray();
            } elseif ($latest) {
                $surveyPayload = $latest->toArray();
            }

            $targetSurveyId = $ready?->id ?? $latest?->id;
            $reactivationPending = $targetSurveyId
                ? $pendingReactivations->has($targetSurveyId)
                : false;

            return [
                'dtr_id' => $dtr->id,
                'dtr_code' => $dtr->code,
                'dtr_name' => $dtr->name,
                'feeder_id' => $dtr->feeder_id,
                'capacity_kva' => $dtr->capacity_kva,
                'status_key' => $statusKey,
                'can_open' => (bool) $ready,
                'can_request_reactivation' => $statusKey === 'completed' && ! $reactivationPending,
                'reactivation_pending' => (bool) $reactivationPending,
                'survey_id' => $targetSurveyId,
                'survey' => $surveyPayload,
            ];
        })->values();

        // #region agent log
        try {
            $payload = json_encode([
                'sessionId' => 'a2382b',
                'runId' => 'post-fix',
                'hypothesisId' => 'A',
                'location' => 'FieldApiController.php:feederDtrsForConsumer',
                'message' => 'feeder DTR list for consumer tab',
                'data' => [
                    'feeder_id' => $feederId,
                    'dtr_count' => $rows->count(),
                    'ready_count' => $rows->where('can_open', true)->count(),
                    'names' => $rows->pluck('dtr_name')->values()->all(),
                    'status_keys' => $rows->pluck('status_key')->values()->all(),
                ],
                'timestamp' => (int) (microtime(true) * 1000),
            ], JSON_UNESCAPED_SLASHES);
            file_put_contents(dirname(base_path()).DIRECTORY_SEPARATOR.'debug-a2382b.log', $payload.PHP_EOL, FILE_APPEND);
        } catch (\Throwable $e) {
        }
        // #endregion

        return response()->json(['data' => $rows]);
    }

    /**
     * Search master/WFM consumer by IVRS and/or meter serial (MSN).
     * Scoped to the logged-in user's assigned zone(s); when surveying a DTR/feeder,
     * further narrowed to that hierarchy's zone so other-zone master rows are not returned.
     */
    public function searchConsumer(Request $request)
    {
        abort_unless($request->user()->canCaptureSurveys() || $request->user()->isAdmin(), 403);

        $data = $request->validate([
            'ivrs' => ['nullable', 'string', 'max:50'],
            'msn' => ['nullable', 'string', 'max:50'],
            // Survey context: feeder mismatch warning + zone narrowing.
            'feeder_id' => ['nullable', 'integer', 'exists:feeders,id'],
            'dtr_id' => ['nullable', 'integer', 'exists:dtrs,id'],
            'zone_id' => ['nullable', 'integer', 'exists:zones,id'],
        ]);

        $ivrs = trim((string) ($data['ivrs'] ?? ''));
        $msn = trim((string) ($data['msn'] ?? ''));
        $user = $request->user();

        $empty = static function (string $message, array $extra = []) {
            return response()->json(array_merge([
                'found' => false,
                'mismatch' => false,
                'same_feeder' => true,
                'will_remap' => false,
                'message' => $message,
                'consumer' => null,
                'mapped_feeder' => null,
                'mapped_dtr' => null,
                'zone_scope_empty' => false,
            ], $extra));
        };

        if ($ivrs === '' && $msn === '') {
            return $empty('Enter IVRS Number or Meter Serial Number to search.');
        }

        $allowedZones = HierarchyScope::allowedZoneIds($user);

        // Field executives must have zone assignment (direct or via region/circle/division).
        if ($user->isFieldExecutive() && ($allowedZones === null || $allowedZones->isEmpty())) {
            return $empty(
                'No zone assigned to your account. Ask your manager to assign a zone before searching consumers.',
                ['zone_scope_empty' => true]
            );
        }

        $searchZoneIds = $allowedZones; // null = admin / unrestricted
        $contextZoneId = $this->resolveContextZoneId(
            (int) ($data['zone_id'] ?? 0),
            (int) ($data['feeder_id'] ?? 0),
            (int) ($data['dtr_id'] ?? 0),
        );

        if ($contextZoneId > 0) {
            if ($searchZoneIds !== null && ! $searchZoneIds->contains($contextZoneId)) {
                return $empty('Current DTR / feeder zone is outside your assigned zones.');
            }
            // Prefer current survey zone so multi-zone FEs do not pull other-zone masters.
            $searchZoneIds = collect([$contextZoneId]);
        }

        $consumer = $this->findConsumerInZones($ivrs, $msn, $searchZoneIds);

        $sourceLabel = match ($consumer?->source) {
            Consumer::SOURCE_MI => 'MI Done',
            Consumer::SOURCE_MASTER => 'Consumer Master',
            default => 'Master / WFM',
        };

        $currentFeederId = (int) ($data['feeder_id'] ?? 0);
        $currentDtrId = (int) ($data['dtr_id'] ?? 0);
        $mappedDtr = $consumer?->dtr;
        $mappedFeeder = $consumer?->feeder ?? $mappedDtr?->feeder;
        $consumerFeederId = $this->resolveConsumerFeederId($consumer);
        $sameFeeder = $currentFeederId <= 0 || $consumerFeederId <= 0 || $consumerFeederId === $currentFeederId;
        $mismatch = (bool) $consumer && $currentFeederId > 0 && $consumerFeederId > 0 && ! $sameFeeder;

        $message = $consumer
            ? ($mismatch
                ? "Consumer found in {$sourceLabel} under another feeder — continue will remap to current feeder on submit."
                : "Consumer found in {$sourceLabel} data.")
            : 'Consumer not found in your assigned zone.';

        return response()->json([
            'found' => (bool) $consumer,
            'mismatch' => $mismatch,
            'same_feeder' => $sameFeeder,
            'will_remap' => $mismatch,
            'message' => $message,
            'source' => $consumer?->source,
            'consumer' => $consumer,
            'mapped_feeder' => $mappedFeeder ? [
                'id' => $mappedFeeder->id,
                'code' => $mappedFeeder->code,
                'name' => $mappedFeeder->name,
            ] : null,
            'mapped_dtr' => $mappedDtr ? [
                'id' => $mappedDtr->id,
                'code' => $mappedDtr->code,
                'name' => $mappedDtr->name,
                'feeder_id' => $mappedDtr->feeder_id,
            ] : null,
            'current_feeder_id' => $currentFeederId ?: null,
            'current_dtr_id' => $currentDtrId ?: null,
            'previous_feeder_id' => $consumerFeederId ?: null,
            'zone_scope_empty' => false,
            'search_zone_ids' => $searchZoneIds?->values()->all(),
        ]);
    }

    /** Resolve zone for the active DTR/feeder survey context. */
    private function resolveContextZoneId(int $zoneId, int $feederId, int $dtrId): int
    {
        if ($zoneId > 0) {
            return $zoneId;
        }

        if ($feederId > 0) {
            $zid = Feeder::query()
                ->whereKey($feederId)
                ->join('substations', 'substations.id', '=', 'feeders.substation_id')
                ->value('substations.zone_id');

            if ($zid) {
                return (int) $zid;
            }
        }

        if ($dtrId > 0) {
            $zid = Dtr::query()
                ->where('dtrs.id', $dtrId)
                ->join('feeders', 'feeders.id', '=', 'dtrs.feeder_id')
                ->join('substations', 'substations.id', '=', 'feeders.substation_id')
                ->value('substations.zone_id');

            if ($zid) {
                return (int) $zid;
            }
        }

        return 0;
    }

    /**
     * Fast zone-scoped consumer lookup (single query, indexed equality, slim columns).
     *
     * @param  \Illuminate\Support\Collection<int, int>|null  $zoneIds  null = no zone filter
     */
    private function findConsumerInZones(string $ivrs, string $msn, $zoneIds): ?Consumer
    {
        $cols = [
            'id', 'dtr_id', 'feeder_id', 'pole_id', 'name', 'phone',
            'ivrs', 'account_no', 'msn', 'address', 'phase', 'is_active', 'source',
        ];

        $query = Consumer::query()
            ->select($cols)
            ->with([
                'dtr:id,feeder_id,code,name',
                'dtr.feeder:id,code,name,substation_id',
                'feeder:id,code,name,substation_id',
            ]);

        if ($ivrs !== '') {
            $query->where('ivrs', $ivrs);
        }

        if ($msn !== '') {
            // Equality variants keep msn index usable (avoid UPPER(msn)).
            $msnUpper = strtoupper($msn);
            $msnLower = strtolower($msn);
            $query->where(function ($q) use ($msn, $msnUpper, $msnLower) {
                $q->where('msn', $msnUpper);
                if ($msn !== $msnUpper) {
                    $q->orWhere('msn', $msn);
                }
                if ($msnLower !== $msn && $msnLower !== $msnUpper) {
                    $q->orWhere('msn', $msnLower);
                }
            });
        }

        if ($zoneIds !== null) {
            $zoneIds = collect($zoneIds)->map(fn ($id) => (int) $id)->unique()->values();
            if ($zoneIds->isEmpty()) {
                return null;
            }

            $feederIds = Feeder::query()
                ->whereHas('substation', fn ($s) => $s->whereIn('zone_id', $zoneIds))
                ->pluck('id');

            if ($feederIds->isEmpty()) {
                return null;
            }

            $query->where(function ($q) use ($feederIds) {
                $q->whereIn('feeder_id', $feederIds)
                    ->orWhereIn('dtr_id', function ($sub) use ($feederIds) {
                        $sub->select('id')->from('dtrs')->whereIn('feeder_id', $feederIds);
                    });
            });
        }

        // Prefer MI Done, then Consumer Master, then any other source — one round-trip.
        return $query
            ->orderByRaw("CASE source WHEN 'mi' THEN 0 WHEN 'master' THEN 1 ELSE 2 END")
            ->orderBy('id')
            ->limit(1)
            ->first();
    }

    /** Prefer consumers.feeder_id; fall back to master DTR's feeder. */
    private function resolveConsumerFeederId(?Consumer $consumer): int
    {
        if (! $consumer) {
            return 0;
        }
        if (! empty($consumer->feeder_id)) {
            return (int) $consumer->feeder_id;
        }

        return (int) ($consumer->dtr?->feeder_id ?? 0);
    }

    /** Field verification submit — manager approval pending. */
    public function verifyConsumer(Request $request, DtrSurvey $survey)
    {
        abort_unless($request->user()->canCaptureSurveys() || $request->user()->isAdmin(), 403);
        abort_unless($this->dtrReadyForConsumer($survey), 403);
        abort_unless(SurveyScope::canView($request->user(), $survey), 403);

        $data = $request->validate([
            'pole_id' => ['required', 'exists:poles,id'],
            'consumer_id' => ['nullable', 'exists:consumers,id'],
            'consumer_name' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20'],
            'ivrs' => ['nullable', 'string', 'max:50'],
            'msn' => ['nullable', 'string', 'max:50'],
            'phase' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'gps_accuracy' => ['nullable', 'numeric'],
            'observation' => ['nullable', 'string', 'max:1000'],
            'meter_make' => [
                'nullable',
                'string',
                'max:80',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (! \App\Support\MeterMakeFromMsn::isAcceptableMake((string) $value)) {
                        $fail('Enter a valid meter make (known make or custom name). Do not send Other alone.');
                    }
                },
            ],
            'meter_condition' => ['nullable', Rule::in(['Normal', 'Defective', 'Burnt'])],
            'verification_status' => ['nullable', Rule::in(['Verified', 'Updated', 'New Consumer'])],
            // Client confirmation after mismatch warning; server also remaps whenever feeder/DTR differs.
            'remap_to_current_feeder' => ['nullable', 'boolean'],
            'details_changed' => ['nullable', 'boolean'],
            'meter_photo' => ['required', 'file', 'max:5120', new ClientImageFile],
            'premise_photo' => ['nullable', 'file', 'max:5120', new ClientImageFile],
        ]);

        $pole = Pole::findOrFail($data['pole_id']);
        abort_unless((int) $pole->dtr_id === (int) $survey->dtr_id, 422, 'Pole does not belong to this DTR.');

        $verification = $data['verification_status'] ?? ($data['consumer_id'] ? 'Verified' : 'New Consumer');
        $remapped = false;
        $previousFeederId = null;
        $previousDtrId = null;

        // Field survey is source of truth: remap master consumer to current DTR/feeder + overwrite details.
        // Never 422 for "wrong feeder" — same product rule as DTR Add New overwrite.
        if (! empty($data['consumer_id'])) {
            $consumer = Consumer::with('dtr')->find($data['consumer_id']);
            if ($consumer) {
                $targetDtrId = (int) $survey->dtr_id;
                $targetFeederId = (int) $survey->feeder_id;
                $previousDtrId = (int) ($consumer->dtr_id ?? 0);
                $previousFeederId = $this->resolveConsumerFeederId($consumer);
                $needsRemap = ($previousDtrId !== $targetDtrId)
                    || ($previousFeederId > 0 && $targetFeederId > 0 && $previousFeederId !== $targetFeederId)
                    || $request->boolean('remap_to_current_feeder');

                $updates = [
                    'pole_id' => $pole->id,
                    'dtr_id' => $targetDtrId,
                    'feeder_id' => $targetFeederId > 0 ? $targetFeederId : $consumer->feeder_id,
                ];
                if (array_key_exists('consumer_name', $data) && $data['consumer_name'] !== null && $data['consumer_name'] !== '') {
                    $updates['name'] = $data['consumer_name'];
                }
                if (array_key_exists('phone', $data)) {
                    $updates['phone'] = $data['phone'];
                }
                if (array_key_exists('ivrs', $data) && $data['ivrs'] !== null && $data['ivrs'] !== '') {
                    $updates['ivrs'] = $data['ivrs'];
                }
                if (array_key_exists('msn', $data) && $data['msn'] !== null && $data['msn'] !== '') {
                    $updates['msn'] = $data['msn'];
                }
                if (array_key_exists('phase', $data)) {
                    $updates['phase'] = $data['phase'];
                }
                if (array_key_exists('address', $data)) {
                    $updates['address'] = $data['address'];
                }

                $consumer->fill($updates);
                $consumer->save();

                if ($needsRemap && ($previousDtrId !== $targetDtrId || $previousFeederId !== $targetFeederId)) {
                    $remapped = true;
                    ActivityLog::record('consumer.remapped_field', $consumer, [
                        'previous_dtr_id' => $previousDtrId,
                        'previous_feeder_id' => $previousFeederId,
                        'dtr_id' => $targetDtrId,
                        'feeder_id' => $targetFeederId,
                        'dtr_survey_id' => $survey->id,
                        'by' => $request->user()->id,
                    ]);
                }
            }

            if ($verification === 'Verified' && ($request->boolean('details_changed') || $remapped)) {
                $verification = 'Updated';
            }
        } else {
            $verification = 'New Consumer';
        }

        // Upsert pending/rejected; block duplicate once already approved.
        $row = null;
        if (! empty($data['consumer_id'])) {
            $row = ConsumerSurvey::where('dtr_id', $survey->dtr_id)
                ->where('consumer_id', $data['consumer_id'])
                ->latest('id')
                ->first();
        }
        if (! $row && ! empty($data['ivrs'])) {
            $row = ConsumerSurvey::where('dtr_id', $survey->dtr_id)
                ->where('ivrs', $data['ivrs'])
                ->latest('id')
                ->first();
        }
        if ($row && $row->status === 'approved') {
            return response()->json([
                'message' => 'This consumer was already verified and approved on this DTR. Duplicate survey is not allowed.',
                'existing_survey_id' => $row->id,
                'consumer_survey' => $row->load(['consumer', 'pole']),
            ], 409);
        }
        if (! $row) {
            $row = new ConsumerSurvey;
        }

        $msn = $data['msn'] ?? null;
        $meterMake = $data['meter_make']
            ?? \App\Support\MeterMakeFromMsn::fromMsn($msn)
            ?? $row->meter_make;
        if (is_string($meterMake)) {
            $meterMake = trim($meterMake);
            if ($meterMake === '' || strcasecmp($meterMake, \App\Support\MeterMakeFromMsn::MAKE_OTHER) === 0) {
                $meterMake = \App\Support\MeterMakeFromMsn::fromMsn($msn) ?? $row->meter_make;
            }
        }

        $row->fill([
            'consumer_id' => $data['consumer_id'] ?? $row->consumer_id,
            'consumer_name' => $data['consumer_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'ivrs' => $data['ivrs'] ?? null,
            'msn' => $msn,
            'meter_make' => $meterMake,
            'phase' => $data['phase'] ?? null,
            'address' => $data['address'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'gps_accuracy' => $data['gps_accuracy'] ?? null,
            'observation' => $data['observation'] ?? null,
            'meter_condition' => $data['meter_condition'] ?? null,
            'verification_status' => $verification,
            'status' => 'pending_approval',
            'survey_flag' => $verification === 'New Consumer' ? 'new' : null,
            'review_remarks' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
        ]);
        $row->dtr_survey_id = $survey->id;
        $row->surveyor_id = $request->user()->id;
        $row->dtr_id = $survey->dtr_id;
        $row->pole_id = $pole->id;
        $row->surveyed_at = now();

        if ($request->hasFile('meter_photo')) {
            $row->meter_photo = $this->storeSurveyPhoto($request->file('meter_photo'), 'surveys/consumers');
        }
        if ($request->hasFile('premise_photo')) {
            $row->premise_photo = $this->storeSurveyPhoto($request->file('premise_photo'), 'surveys/consumers');
        }

        $row->save();
        ActivityLog::record($row->wasRecentlyCreated ? 'consumer.survey_saved' : 'consumer.survey_updated', $row, [
            'remapped' => $remapped,
            'previous_dtr_id' => $previousDtrId,
            'previous_feeder_id' => $previousFeederId,
        ]);

        $message = $remapped
            ? 'Consumer remapped to this feeder/DTR and survey submitted (Manager approval pending).'
            : 'Consumer survey submitted (Manager approval pending).';

        return response()->json([
            'message' => $message,
            'remapped' => $remapped,
            'previous_dtr_id' => $previousDtrId,
            'previous_feeder_id' => $previousFeederId,
            'consumer_survey' => $row->fresh(['consumer', 'pole']),
        ], $row->wasRecentlyCreated ? 201 : 200);
    }

    /** FE: list consumer surveys on a DTR (optional pole filter) — for correcting mistakes. */
    public function listOwnConsumerSurveys(Request $request, DtrSurvey $survey)
    {
        abort_unless(SurveyScope::canView($request->user(), $survey), 403);

        $query = ConsumerSurvey::query()
            ->with(['pole:id,pole_no', 'dtr:id,code,name'])
            ->where('dtr_survey_id', $survey->id)
            ->where('surveyor_id', $request->user()->id)
            ->latest('id');

        if ($poleId = $request->integer('pole_id')) {
            $query->where('pole_id', $poleId);
        }

        $rows = $query->limit(200)->get()->map(fn (ConsumerSurvey $c) => [
            'id' => $c->id,
            'consumer_name' => $c->consumer_name,
            'ivrs' => $c->ivrs,
            'msn' => $c->msn,
            'status' => $c->status,
            'pole_no' => $c->pole?->pole_no,
            'surveyed_at' => optional($c->surveyed_at)?->toDateTimeString(),
            'can_delete' => in_array($c->status, ['pending_approval', 'rejected', 'saved', 'not_accessible'], true),
        ]);

        return response()->json(['data' => $rows]);
    }

    /** FE: permanently delete own wrong consumer verification (before manager approval). */
    public function destroyOwnConsumerSurvey(Request $request, ConsumerSurvey $consumerSurvey)
    {
        $user = $request->user();
        abort_unless(
            $user->isFieldExecutive() || $user->isAdmin(),
            403
        );
        abort_unless(
            $user->isAdmin() || (int) $consumerSurvey->surveyor_id === (int) $user->id,
            403,
            'You can only delete your own consumer surveys.'
        );
        abort_unless(
            in_array($consumerSurvey->status, ['pending_approval', 'rejected', 'saved', 'not_accessible'], true),
            422,
            'Only pending / rejected / saved consumer surveys can be deleted. Ask manager if already approved.'
        );

        \App\Support\ConsumerSurveyDeleter::delete($consumerSurvey, $user);

        return response()->json(['message' => 'Consumer survey deleted. You can verify again.']);
    }

    public function poles(DtrSurvey $survey)
    {
        abort_unless(SurveyScope::canView(Auth::user(), $survey), 403);
        abort_unless($this->dtrReadyForConsumer($survey), 403);

        $poles = Pole::with(['previousPole:id,pole_no'])
            ->withCount('consumers')
            ->withCount(['consumerSurveys as surveyed_count' => function ($q) use ($survey) {
                $q->where('dtr_survey_id', $survey->id);
            }])
            ->where('dtr_id', $survey->dtr_id)
            ->orderBy('pole_no')
            ->get();

        $expected = (int) $poles->sum('houses_connected');
        $masterTotal = Consumer::where('dtr_id', $survey->dtr_id)->count();
        $totalConsumers = max($masterTotal, $expected);
        $surveyed = ConsumerSurvey::where('dtr_survey_id', $survey->id)->count();

        return response()->json([
            'survey' => $survey->load(['feeder', 'dtr']),
            'poles' => $poles,
            'stats' => [
                'total_poles' => $poles->count(),
                'total_houses' => $expected,
                'total_consumers' => $totalConsumers,
                'surveyed_consumers' => $surveyed,
                'pending_consumers' => max(0, $totalConsumers - $surveyed),
            ],
        ]);
    }

    public function storePole(Request $request, DtrSurvey $survey)
    {
        abort_unless($request->user()->isFieldExecutive() || $request->user()->isAdmin(), 403);
        abort_unless($this->dtrReadyForConsumer($survey), 403);

        $data = $request->validate([
            'pole_no' => ['required', 'string', 'max:50'],
            'source_type' => ['required', Rule::in(['dtr', 'previous_pole'])],
            'previous_pole_id' => ['nullable', 'exists:poles,id'],
            'houses_connected' => ['required', 'integer', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'photo' => ['nullable', 'file', 'max:12288', new ClientImageFile],
        ]);
        unset($data['photo']);

        if ($data['source_type'] === 'previous_pole' && empty($data['previous_pole_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'previous_pole_id' => ['Select the previous pole (source).'],
            ]);
        }

        if ($data['source_type'] === 'dtr') {
            $data['previous_pole_id'] = null;
        } elseif (! empty($data['previous_pole_id'])) {
            $prev = Pole::findOrFail($data['previous_pole_id']);
            abort_unless((int) $prev->dtr_id === (int) $survey->dtr_id, 422, 'Previous pole must belong to same DTR.');
        }

        $data['dtr_id'] = $survey->dtr_id;
        $data['houses_connected'] = (int) ($data['houses_connected'] ?? 0);
        $photo = $this->polePhotoPath($request);
        if ($photo !== null) {
            $data['photo'] = $photo;
        }
        $pole = Pole::create($data);

        return response()->json(['pole' => $pole->load('previousPole')], 201);
    }

    /** Field executive can correct a pole they added under this DTR survey. */
    public function updatePole(Request $request, DtrSurvey $survey, Pole $pole)
    {
        abort_unless($request->user()->isFieldExecutive() || $request->user()->isAdmin(), 403);
        abort_unless($this->dtrReadyForConsumer($survey), 403);
        abort_unless(SurveyScope::canView($request->user(), $survey), 403);
        abort_unless((int) $pole->dtr_id === (int) $survey->dtr_id, 422, 'Pole does not belong to this DTR.');

        $data = $request->validate([
            'pole_no' => ['required', 'string', 'max:50'],
            'source_type' => ['required', Rule::in(['dtr', 'previous_pole'])],
            'previous_pole_id' => ['nullable', 'exists:poles,id'],
            'houses_connected' => ['required', 'integer', 'min:0'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'photo' => ['nullable', 'file', 'max:12288', new ClientImageFile],
        ]);
        unset($data['photo']);

        if ($data['source_type'] === 'previous_pole' && empty($data['previous_pole_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'previous_pole_id' => ['Select the previous pole (source).'],
            ]);
        }

        if ($data['source_type'] === 'dtr') {
            $data['previous_pole_id'] = null;
        } elseif (! empty($data['previous_pole_id'])) {
            abort_unless((int) $data['previous_pole_id'] !== (int) $pole->id, 422, 'Pole cannot reference itself.');
            $prev = Pole::findOrFail($data['previous_pole_id']);
            abort_unless((int) $prev->dtr_id === (int) $survey->dtr_id, 422, 'Previous pole must belong to same DTR.');
        }

        $data['houses_connected'] = (int) $data['houses_connected'];
        $photo = $this->polePhotoPath($request);
        if ($photo !== null) {
            $data['photo'] = $photo;
        }
        $pole->update($data);

        return response()->json([
            'message' => 'Pole updated.',
            'pole' => $pole->fresh()->load('previousPole'),
        ]);
    }

    /**
     * Optional pole image (multipart `photo`) → surveys/poles.
     * Returns null when no file is sent or `poles.photo` is not deployed yet.
     */
    private function polePhotoPath(Request $request): ?string
    {
        if (! $request->hasFile('photo') || ! Schema::hasColumn('poles', 'photo')) {
            return null;
        }

        return $this->storeSurveyPhoto($request->file('photo'), 'surveys/poles');
    }

    /** FE: delete a mistakenly created pole under this DTR (and its consumer surveys). */
    public function destroyPole(Request $request, DtrSurvey $survey, Pole $pole)
    {
        abort_unless($request->user()->isFieldExecutive() || $request->user()->isAdmin(), 403);
        abort_unless($this->dtrReadyForConsumer($survey), 403);
        abort_unless(SurveyScope::canView($request->user(), $survey), 403);
        abort_unless((int) $pole->dtr_id === (int) $survey->dtr_id, 422, 'Pole does not belong to this DTR.');

        // Block delete if another pole on this DTR references it as previous source.
        $dependents = Pole::query()
            ->where('dtr_id', $survey->dtr_id)
            ->where('previous_pole_id', $pole->id)
            ->count();
        abort_unless($dependents === 0, 422, 'Cannot delete this pole — other poles use it as previous source. Delete those first.');

        $surveys = ConsumerSurvey::query()
            ->where('pole_id', $pole->id)
            ->get();
        foreach ($surveys as $row) {
            \App\Support\ConsumerSurveyDeleter::delete($row, $request->user());
        }

        $label = $pole->pole_no;
        $id = (int) $pole->id;
        $pole->delete();
        ActivityLog::record('pole.deleted', $survey, [
            'pole_id' => $id,
            'pole_no' => $label,
            'by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => "Pole {$label} deleted.",
            'id' => $id,
        ]);
    }

    /** Mark consumer survey for this DTR as finished and return summary stats. */
    public function finishConsumer(Request $request, DtrSurvey $survey)
    {
        abort_unless($request->user()->canCaptureSurveys() || $request->user()->isAdmin(), 403);
        abort_unless(in_array($survey->status, ['approved', 'pending_approval', 'completed'], true), 403);
        abort_unless(SurveyScope::canView($request->user(), $survey), 403);

        if ($survey->consumer_survey_completed_at) {
            return response()->json([
                'message' => 'DTR survey already finished.',
                'summary' => $this->dtrFinishSummary($survey),
                'survey' => $survey->fresh(['feeder', 'dtr']),
            ]);
        }

        $survey->consumer_survey_completed_at = now();
        $survey->save();
        ActivityLog::record('consumer.dtr_finished', $survey);

        return response()->json([
            'message' => 'DTR survey finished successfully.',
            'summary' => $this->dtrFinishSummary($survey->fresh()),
            'survey' => $survey->fresh(['feeder', 'dtr']),
        ]);
    }

    /**
     * FE requests re-activation of a finished DTR so more consumers can be surveyed.
     * Manager/Admin must approve (web) before consumer_survey_completed_at is cleared.
     */
    public function requestDtrReactivation(Request $request, DtrSurvey $survey)
    {
        abort_unless($request->user()->canCaptureSurveys() || $request->user()->isAdmin(), 403);
        abort_unless(SurveyScope::canView($request->user(), $survey), 403);

        if (! DtrConsumerReopen::tableReady()) {
            return response()->json([
                'message' => 'Re-activation is not available yet. Please ask admin to run the SQL update.',
            ], 503);
        }

        if (! $survey->consumer_survey_completed_at) {
            return response()->json([
                'message' => 'This DTR is already open for consumer survey.',
                'survey' => $survey->fresh(['feeder', 'dtr']),
            ], 422);
        }

        abort_unless(
            in_array($survey->status, ['approved', 'pending_approval', 'completed'], true),
            403,
            'DTR survey is not eligible for consumer re-activation.'
        );

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $existing = DtrReactivationRequest::query()
            ->where('dtr_survey_id', $survey->id)
            ->where('status', DtrReactivationRequest::STATUS_PENDING)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => 'Re-activation request already pending for this DTR.',
                'request' => $existing,
            ], 422);
        }

        $row = DtrReactivationRequest::create([
            'dtr_survey_id' => $survey->id,
            'requested_by' => $request->user()->id,
            'reason' => isset($data['reason']) ? trim((string) $data['reason']) : null,
            'status' => DtrReactivationRequest::STATUS_PENDING,
        ]);

        ActivityLog::record('consumer.dtr_reactivation_requested', $survey, [
            'request_id' => $row->id,
            'by' => $request->user()->id,
            'reason' => $row->reason,
        ]);

        // Notify managers/PMs in scope (best-effort).
        try {
            $managers = User::query()
                ->whereIn('role', [
                    User::ROLE_ADMIN,
                    User::ROLE_SUPER_ADMIN,
                    User::ROLE_MANAGER,
                    'supervisor',
                    User::ROLE_PROJECT_MANAGER,
                ])
                ->where('is_active', true)
                ->limit(80)
                ->get();
            foreach ($managers as $mgr) {
                if ($mgr->id === $request->user()->id) {
                    continue;
                }
                if (! $mgr->isAdmin() && ! SurveyScope::canView($mgr, $survey)) {
                    continue;
                }
                AppNotification::notifyUser(
                    (int) $mgr->id,
                    'DTR re-activation requested',
                    ($request->user()->name ?? 'FE').' requested reopen of DTR '.($survey->dtr_code ?: '#'.$survey->id),
                    null,
                    DtrReactivationRequest::class,
                    (int) $row->id
                );
            }
        } catch (\Throwable $e) {
            // ignore notification fan-out failures
        }

        return response()->json([
            'message' => 'Re-activation request sent for approval.',
            'request' => $row,
        ], 201);
    }

    /** List current user's DTR reactivation requests (pending + past). */
    public function listDtrReactivationRequests(Request $request)
    {
        abort_unless($request->user()->canCaptureSurveys() || $request->user()->isAdmin() || $request->user()->canApproveSurveys(), 403);

        if (! DtrConsumerReopen::tableReady()) {
            return response()->json(['data' => []]);
        }

        $user = $request->user();
        $query = DtrReactivationRequest::query()
            ->with(['dtrSurvey:id,dtr_code,dtr_name,feeder_code,feeder_name,consumer_survey_completed_at,status'])
            ->latest('id');

        if ($user->canApproveSurveys() || $user->isAdmin()) {
            if (! $user->isAdmin()) {
                $surveyIds = SurveyScope::apply(DtrSurvey::query(), $user)->pluck('id');
                $query->whereIn('dtr_survey_id', $surveyIds);
            }
        } else {
            $query->where('requested_by', $user->id);
        }

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        $rows = $query->limit(100)->get()->map(function (DtrReactivationRequest $r) {
            return [
                'id' => $r->id,
                'dtr_survey_id' => $r->dtr_survey_id,
                'reason' => $r->reason,
                'status' => $r->status,
                'status_label' => $r->statusLabel(),
                'review_remarks' => $r->review_remarks,
                'reviewed_at' => $r->reviewed_at,
                'created_at' => $r->created_at,
                'dtr_code' => $r->dtrSurvey?->dtr_code,
                'dtr_name' => $r->dtrSurvey?->dtr_name,
                'feeder_code' => $r->dtrSurvey?->feeder_code,
                'is_open' => $r->dtrSurvey ? $r->dtrSurvey->consumer_survey_completed_at === null : false,
            ];
        });

        return response()->json(['data' => $rows]);
    }

    /** @return array{dtr_label: string, dtr_code: string, dtr_name: string, total_poles: int, total_consumers: int, survey_date: string, survey_date_iso: string} */
    private function dtrFinishSummary(DtrSurvey $survey): array
    {
        $poles = Pole::where('dtr_id', $survey->dtr_id)->get(['id', 'houses_connected']);
        $totalPoles = $poles->count();
        // Total Consumers = sum of expected consumers (houses_connected) on poles for this DTR
        $totalConsumers = (int) $poles->sum('houses_connected');
        $completedAt = $survey->consumer_survey_completed_at ?? now();

        return [
            'dtr_label' => trim(($survey->dtr_code ?? '').' - '.($survey->dtr_name ?? '')),
            'dtr_code' => (string) ($survey->dtr_code ?? ''),
            'dtr_name' => (string) ($survey->dtr_name ?? ''),
            'total_poles' => $totalPoles,
            'total_consumers' => $totalConsumers,
            'survey_date' => $completedAt->format('d M Y'),
            'survey_date_iso' => $completedAt->toIso8601String(),
        ];
    }

    /** Not Accessible / Permanently Disconnected exception capture. */
    public function exceptionConsumer(Request $request, DtrSurvey $survey)
    {
        abort_unless($request->user()->canCaptureSurveys() || $request->user()->isAdmin(), 403);
        abort_unless($this->dtrReadyForConsumer($survey), 403);
        abort_unless(SurveyScope::canView($request->user(), $survey), 403);

        $data = $request->validate([
            'pole_id' => ['required', 'exists:poles,id'],
            'survey_flag' => ['required', Rule::in(['not_accessible', 'pdc'])],
            'reason' => ['required', 'string', 'max:120'],
            'observation' => ['nullable', 'string', 'max:250'],
            'consumer_id' => ['nullable', 'exists:consumers,id'],
            'ivrs' => ['nullable', 'string', 'max:50'],
            'msn' => ['nullable', 'string', 'max:50'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'gps_accuracy' => ['nullable', 'numeric'],
            'meter_photo' => ['required', 'file', 'max:5120', new ClientImageFile],
        ]);

        $pole = Pole::findOrFail($data['pole_id']);
        abort_unless((int) $pole->dtr_id === (int) $survey->dtr_id, 422, 'Pole does not belong to this DTR.');

        if (! empty($data['consumer_id'])) {
            $existingConsumer = ConsumerSurvey::query()
                ->where('dtr_id', $survey->dtr_id)
                ->where('consumer_id', $data['consumer_id'])
                ->whereIn('status', ['pending_approval', 'approved'])
                ->latest('id')
                ->first();
            if ($existingConsumer) {
                return response()->json([
                    'message' => 'This consumer was already surveyed on this DTR. Duplicate survey is not allowed.',
                    'existing_survey_id' => $existingConsumer->id,
                ], 409);
            }
        }

        $row = new ConsumerSurvey([
            'consumer_id' => $data['consumer_id'] ?? null,
            'ivrs' => $data['ivrs'] ?? null,
            'msn' => $data['msn'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'gps_accuracy' => $data['gps_accuracy'] ?? null,
            'observation' => trim(($data['reason'] ?? '').(($data['observation'] ?? '') !== '' ? ' — '.$data['observation'] : '')),
            'verification_status' => $data['survey_flag'] === 'pdc' ? 'PDC' : 'Not Accessible',
            'status' => 'not_accessible',
            'survey_flag' => $data['survey_flag'],
        ]);
        $row->dtr_survey_id = $survey->id;
        $row->surveyor_id = $request->user()->id;
        $row->dtr_id = $survey->dtr_id;
        $row->pole_id = $pole->id;
        $row->surveyed_at = now();
        $row->meter_photo = $this->storeSurveyPhoto($request->file('meter_photo'), 'surveys/consumers');
        $row->save();
        ActivityLog::record('consumer.exception_saved', $row);

        return response()->json([
            'message' => $data['survey_flag'] === 'pdc'
                ? 'Permanently disconnected recorded.'
                : 'Consumer not accessible recorded.',
            'consumer_survey' => $row->fresh(['pole']),
        ], 201);
    }

    public function assignments(Request $request)
    {
        return $this->workAssignments($request);
    }

    public function workAssignments(Request $request)
    {
        WorkAssignment::syncClosedStatuses();

        $user = $request->user();
        $statusFilter = trim((string) $request->query('status', ''));
        // FE active work list: hide completed (done/closed) unless explicitly requested.
        $defaultActiveOnly = $user->isFieldExecutive()
            && $statusFilter === ''
            && ! $request->boolean('include_all');

        $rows = WorkAssignment::with(['feeder.substation', 'zone', 'dtr', 'assigner', 'assignee'])
            ->when($user->isFieldExecutive(), fn ($q) => $q->where('assigned_to', $user->id))
            ->when($user->isManager() || $user->isProjectManager(), function ($q) use ($user) {
                $q->where(function ($q) use ($user) {
                    $q->where('assigned_by', $user->id)
                        ->orWhereIn('assigned_to', User::where('supervisor_id', $user->id)->pluck('id'));
                });
            })
            ->when($request->filled('zone_id'), fn ($q) => $q->where('zone_id', $request->integer('zone_id')))
            ->when($defaultActiveOnly || $statusFilter === 'active', fn ($q) => $q->whereIn('status', WorkAssignment::ACTIVE_STATUSES))
            ->when($statusFilter !== '' && $statusFilter !== 'active', fn ($q) => $q->where('status', $statusFilter))
            ->when($request->filled('assigned_to'), fn ($q) => $q->where('assigned_to', $request->integer('assigned_to')))
            ->latest()
            ->paginate((int) $request->integer('per_page', 50));

        $data = collect($rows->items())->map(fn (WorkAssignment $a) => $a->toApiArray())->values();

        return response()->json([
            'data' => $data,
            'current_page' => $rows->currentPage(),
            'last_page' => $rows->lastPage(),
            'per_page' => $rows->perPage(),
            'total' => $rows->total(),
        ]);
    }

    public function storeAssignment(Request $request)
    {
        abort_unless($request->user()->canAssignWork(), 403);

        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
            'feeder_id' => ['nullable', 'exists:feeders,id'],
            'dtr_id' => ['nullable', 'exists:dtrs,id'],
            'zone_id' => ['nullable', 'exists:zones,id'],
            'work_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if (empty($data['feeder_id']) && empty($data['dtr_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'feeder_id' => ['Select a feeder or DTR.'],
            ]);
        }

        $fe = User::findOrFail($data['assigned_to']);
        abort_unless($fe->isFieldExecutive(), 422, 'Assignments can only go to Field Executives.');

        $zoneId = $data['zone_id'] ?? null;
        if (! empty($data['feeder_id'])) {
            $feeder = Feeder::with('substation')->findOrFail($data['feeder_id']);
            $zoneId = $zoneId ?: $feeder->substation?->zone_id;
            $this->assertZoneAssignable($request->user(), (int) $zoneId);
        }

        $workDate = $data['work_date'] ?? now()->toDateString();
        $status = WorkAssignment::STATUS_OPEN;
        if (\Illuminate\Support\Carbon::parse($workDate)->lt(now()->startOfDay())) {
            $status = WorkAssignment::STATUS_CLOSED;
        }

        $assignment = WorkAssignment::create([
            'assigned_to' => $data['assigned_to'],
            'assigned_by' => $request->user()->id,
            'feeder_id' => $data['feeder_id'] ?? null,
            'dtr_id' => $data['dtr_id'] ?? null,
            'zone_id' => $zoneId,
            'work_date' => $workDate,
            'notes' => $data['notes'] ?? null,
            'status' => $status,
        ]);

        if ($zoneId) {
            $this->ensureFeZoneScope((int) $data['assigned_to'], (int) $zoneId);
        }

        $dateLabel = \Illuminate\Support\Carbon::parse($workDate)->format('d M Y');
        AppNotification::notifyUser(
            (int) $assignment->assigned_to,
            'New work assignment',
            ($assignment->notes ?: 'You have been assigned field work.').' Work date: '.$dateLabel,
            null,
            WorkAssignment::class,
            (int) $assignment->id
        );
        ActivityLog::record('assignment.created', $assignment);

        return response()->json([
            'message' => 'Work assigned.',
            'assignment' => $assignment->load(['feeder', 'dtr', 'zone', 'assignee', 'assigner'])->toApiArray(),
        ], 201);
    }

    /**
     * Assign one or more feeders in a zone to a Field Executive.
     * Body: { zone_id, assigned_to, feeder_ids: [], notes? }
     */
    public function storeWorkAssignments(Request $request)
    {
        abort_unless($request->user()->canAssignWork(), 403);

        $data = $request->validate([
            'zone_id' => ['required', 'exists:zones,id'],
            'assigned_to' => ['required', 'exists:users,id'],
            'feeder_ids' => ['required', 'array', 'min:1'],
            'feeder_ids.*' => ['integer', 'exists:feeders,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'work_date' => ['nullable', 'date'],
        ]);

        $fe = User::findOrFail($data['assigned_to']);
        abort_unless($fe->isFieldExecutive(), 422, 'Assignments can only go to Field Executives.');

        $zoneId = (int) $data['zone_id'];
        $this->assertZoneAssignable($request->user(), $zoneId);

        $feederIds = collect($data['feeder_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $feeders = Feeder::with('substation')
            ->whereIn('id', $feederIds)
            ->get();

        abort_unless($feeders->count() === $feederIds->count(), 422, 'One or more feeders were not found.');

        foreach ($feeders as $feeder) {
            abort_unless(
                (int) ($feeder->substation?->zone_id) === $zoneId,
                422,
                "Feeder {$feeder->code} is not in the selected zone."
            );
        }

        $workDate = $data['work_date'] ?? now()->toDateString();
        $created = [];

        foreach ($feeders as $feeder) {
            // Cancel other open assignments for this feeder (mistake / takeover).
            WorkAssignment::query()
                ->where('feeder_id', $feeder->id)
                ->where('status', WorkAssignment::STATUS_OPEN)
                ->whereNull('started_at')
                ->where('assigned_to', '!=', $fe->id)
                ->update(['status' => WorkAssignment::STATUS_CANCELLED]);

            $existing = WorkAssignment::query()
                ->where('feeder_id', $feeder->id)
                ->where('assigned_to', $fe->id)
                ->whereIn('status', WorkAssignment::ACTIVE_STATUSES)
                ->latest('id')
                ->first();

            if ($existing) {
                $created[] = $existing->load(['feeder', 'zone', 'assignee', 'assigner']);
                continue;
            }

            $assignment = WorkAssignment::create([
                'assigned_to' => $fe->id,
                'assigned_by' => $request->user()->id,
                'feeder_id' => $feeder->id,
                'zone_id' => $zoneId,
                'work_date' => $workDate,
                'notes' => $data['notes'] ?? null,
                'status' => WorkAssignment::STATUS_OPEN,
            ]);
            $created[] = $assignment->load(['feeder', 'zone', 'assignee', 'assigner']);
        }

        $this->ensureFeZoneScope($fe->id, $zoneId);

        AppNotification::notifyUser(
            (int) $fe->id,
            'New feeder assignment',
            'You were assigned '.$feederIds->count().' feeder(s). You can survey only assigned feeders.',
            null,
            WorkAssignment::class,
            isset($created[0]) ? (int) $created[0]->id : null
        );
        ActivityLog::record('assignment.bulk_created', $fe, [
            'zone_id' => $zoneId,
            'feeder_ids' => $feederIds->all(),
            'by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Feeders assigned.',
            'count' => count($created),
            'assignments' => collect($created)->map(fn (WorkAssignment $a) => $a->toApiArray())->values(),
        ], 201);
    }

    public function reassignWorkAssignment(Request $request, WorkAssignment $assignment)
    {
        abort_unless($request->user()->canAssignWork(), 403);

        $data = $request->validate([
            'assigned_to' => ['required', 'exists:users,id'],
        ]);

        abort_unless(
            $assignment->canReassign() && ! $assignment->hasSurveyActivity(),
            422,
            'Only open assignments that have not been started can be reassigned.'
        );

        $fe = User::findOrFail($data['assigned_to']);
        abort_unless($fe->isFieldExecutive(), 422, 'Assignments can only go to Field Executives.');

        if ($assignment->zone_id) {
            $this->assertZoneAssignable($request->user(), (int) $assignment->zone_id);
        }

        $previous = (int) $assignment->assigned_to;

        // Mark old row as reassigned and create a fresh open assignment for the new FE.
        $assignment->update([
            'status' => WorkAssignment::STATUS_REASSIGNED,
        ]);

        $new = WorkAssignment::create([
            'assigned_to' => $fe->id,
            'assigned_by' => $request->user()->id,
            'reassigned_from' => $previous,
            'feeder_id' => $assignment->feeder_id,
            'zone_id' => $assignment->zone_id,
            'dtr_id' => $assignment->dtr_id,
            'work_date' => $assignment->work_date?->toDateString() ?? now()->toDateString(),
            'notes' => $assignment->notes,
            'status' => WorkAssignment::STATUS_OPEN,
        ]);

        if ($assignment->zone_id) {
            $this->ensureFeZoneScope($fe->id, (int) $assignment->zone_id);
        }

        AppNotification::notifyUser(
            (int) $fe->id,
            'Feeder reassigned to you',
            'A feeder was reassigned to you. You can now survey it.',
            null,
            WorkAssignment::class,
            (int) $new->id
        );
        if ($previous !== (int) $fe->id) {
            AppNotification::notifyUser(
                $previous,
                'Feeder assignment removed',
                'A feeder assignment was reassigned to another executive.',
                null,
                WorkAssignment::class,
                (int) $assignment->id
            );
        }
        ActivityLog::record('assignment.reassigned', $new, [
            'from' => $previous,
            'previous_assignment_id' => $assignment->id,
            'by' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Assignment reassigned.',
            'assignment' => $new->load(['feeder', 'zone', 'assignee', 'assigner'])->toApiArray(),
            'previous' => $assignment->fresh()->toApiArray(),
        ]);
    }

    public function cancelWorkAssignment(Request $request, WorkAssignment $assignment)
    {
        abort_unless($request->user()->canAssignWork(), 403);

        $force = $request->boolean('force') || $request->boolean('complete');

        if (! $force) {
            abort_unless(
                $assignment->canReassign() && ! $assignment->hasSurveyActivity(),
                422,
                'Only open assignments that have not been started can be unassigned. Use complete=1 to close an in-progress assignment.'
            );
            $assignment->update(['status' => WorkAssignment::STATUS_CANCELLED]);
            $title = 'Feeder unassigned';
            $body = 'A feeder assignment was cancelled by your manager.';
            $action = 'assignment.cancelled';
        } else {
            abort_unless(
                in_array($assignment->status, WorkAssignment::ACTIVE_STATUSES, true),
                422,
                'Only open/started assignments can be completed by manager.'
            );
            $assignment->markCompletedByManager();
            $title = 'Assignment closed by manager';
            $body = 'Your manager marked this feeder assignment as complete.';
            $action = 'assignment.manager_closed';
        }

        AppNotification::notifyUser(
            (int) $assignment->assigned_to,
            $title,
            $body,
            null,
            WorkAssignment::class,
            (int) $assignment->id
        );
        ActivityLog::record($action, $assignment, ['by' => $request->user()->id]);

        return response()->json([
            'message' => $force ? 'Assignment marked complete.' : 'Assignment cancelled.',
            'assignment' => $assignment->fresh(['feeder', 'zone', 'assignee'])->toApiArray(),
        ]);
    }

    /** Feeders in a zone (for Zone Assign UI). */
    public function zoneFeeders(Request $request, Zone $zone)
    {
        abort_unless($request->user()->canAssignWork(), 403);
        $this->assertZoneAssignable($request->user(), (int) $zone->id);

        $q = trim((string) $request->query('q', ''));

        $feeders = Feeder::query()
            ->where('is_active', true)
            ->whereHas('substation', fn ($s) => $s->where('zone_id', $zone->id)->where('is_active', true))
            ->with(['substation:id,name,zone_id'])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%")
                        ->orWhereHas('substation', fn ($s) => $s->where('name', 'like', "%{$q}%"));
                });
            })
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'substation_id']);

        $activeByFeeder = WorkAssignment::query()
            ->whereIn('feeder_id', $feeders->pluck('id'))
            ->whereIn('status', WorkAssignment::ACTIVE_STATUSES)
            ->with('assignee:id,name,email')
            ->get()
            ->keyBy('feeder_id');

        return response()->json([
            'zone' => [
                'id' => $zone->id,
                'name' => $zone->name,
            ],
            'count' => $feeders->count(),
            'feeders' => $feeders->map(function (Feeder $f) use ($activeByFeeder) {
                $active = $activeByFeeder->get($f->id);

                return [
                    'id' => $f->id,
                    'code' => $f->code,
                    'name' => $f->name,
                    'substation_id' => $f->substation_id,
                    'substation' => $f->substation ? [
                        'id' => $f->substation->id,
                        'name' => $f->substation->name,
                    ] : null,
                    'assigned_to' => $active?->assignee?->only(['id', 'name', 'email']),
                    'assignment_id' => $active?->id,
                    'assignment_status' => $active?->status,
                ];
            })->values(),
        ]);
    }

    private function assertZoneAssignable(User $manager, int $zoneId): void
    {
        $assignable = HierarchyScope::assignableZoneIds($manager);
        if ($assignable === null) {
            return;
        }
        abort_unless($assignable->contains($zoneId), 403, 'This zone is outside your management scope.');
    }

    /** Ensure FE has a zone scope row so hierarchy browsing includes the zone. */
    private function ensureFeZoneScope(int $userId, int $zoneId): void
    {
        $exists = UserScope::query()
            ->where('user_id', $userId)
            ->where('scope_type', 'zone')
            ->where('scope_id', $zoneId)
            ->exists();

        if ($exists) {
            return;
        }

        // Drop broad region/circle/division scopes so zone + feeder assignment gates apply cleanly.
        UserScope::where('user_id', $userId)
            ->whereIn('scope_type', ['region', 'circle', 'division'])
            ->delete();

        UserScope::create([
            'user_id' => $userId,
            'scope_type' => 'zone',
            'scope_id' => $zoneId,
        ]);
    }

    public function fieldExecutives()
    {
        abort_unless(request()->user()->canAssignWork() || request()->user()->canApproveSurveys(), 403);

        $users = User::where('role', User::ROLE_FIELD_EXECUTIVE)
            ->where('is_active', true)
            ->with(['scopes' => fn ($q) => $q->where('scope_type', 'zone')])
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'supervisor_id']);

        $zoneIds = $users->flatMap(fn (User $u) => $u->scopes->pluck('scope_id'))->unique()->values();
        $zonesById = $zoneIds->isEmpty()
            ? collect()
            : Zone::query()->whereIn('id', $zoneIds)->with(['division.circle.region'])->get()->keyBy('id');

        return response()->json([
            'data' => $users->map(function (User $u) use ($zonesById) {
                $zoneScopes = $u->scopes->map(function ($s) use ($zonesById) {
                    $z = $zonesById->get((int) $s->scope_id);

                    return $z ? HierarchyScope::zonePayload($z) : [
                        'id' => (int) $s->scope_id,
                        'name' => 'Zone #'.$s->scope_id,
                    ];
                })->values();

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'supervisor_id' => $u->supervisor_id,
                    'zone_scopes' => $zoneScopes,
                ];
            }),
        ]);
    }

    /** Flat searchable zones within manager scope (for Zone Assign UI). */
    public function assignableZones(Request $request)
    {
        abort_unless($request->user()->canAssignWork(), 403);

        $allowed = HierarchyScope::assignableZoneIds($request->user());
        $zones = HierarchyScope::zonesWithAncestry($allowed, $request->query('q'));

        return response()->json([
            'data' => $zones,
            'total' => $zones->count(),
        ]);
    }

    public function teamZoneScopes(Request $request, User $user)
    {
        abort_unless($request->user()->canAssignWork(), 403);
        abort_unless($user->isFieldExecutive(), 422, 'Zone scopes apply to Field Executives only.');

        $zoneIds = $user->scopeIds('zone');
        $zones = $zoneIds->isEmpty()
            ? collect()
            : HierarchyScope::zonesWithAncestry($zoneIds);

        return response()->json([
            'user' => $user->only(['id', 'name', 'email']),
            'zone_ids' => $zoneIds->values(),
            'zones' => $zones,
        ]);
    }

    /**
     * Replace FE zone scopes. Clears broader region/circle/division scopes so
     * the FE can only survey within the assigned zone(s).
     */
    public function updateTeamZoneScopes(Request $request, User $user)
    {
        abort_unless($request->user()->canAssignWork(), 403);
        abort_unless($user->isFieldExecutive(), 422, 'Zone scopes apply to Field Executives only.');

        $data = $request->validate([
            'zone_ids' => ['present', 'array'],
            'zone_ids.*' => ['integer', 'exists:zones,id'],
        ]);

        $zoneIds = collect($data['zone_ids'])->map(fn ($id) => (int) $id)->unique()->values();
        $assignable = HierarchyScope::assignableZoneIds($request->user());
        if ($assignable !== null) {
            $illegal = $zoneIds->diff($assignable);
            abort_unless($illegal->isEmpty(), 403, 'One or more zones are outside your management scope.');
        }

        // Replace: drop broad scopes + existing zone scopes, then write new zone rows.
        UserScope::where('user_id', $user->id)
            ->whereIn('scope_type', ['region', 'circle', 'division', 'zone'])
            ->delete();

        foreach ($zoneIds as $zoneId) {
            UserScope::create([
                'user_id' => $user->id,
                'scope_type' => 'zone',
                'scope_id' => $zoneId,
            ]);
        }

        ActivityLog::record('user.zone_scopes_updated', $user, [
            'by' => $request->user()->id,
            'zone_ids' => $zoneIds->all(),
        ]);

        AppNotification::notifyUser(
            (int) $user->id,
            'Zone work assignment updated',
            $zoneIds->isEmpty()
                ? 'Your zone assignments were cleared. Contact your manager.'
                : 'You can now survey in '.$zoneIds->count().' assigned zone(s).'
        );

        $zones = $zoneIds->isEmpty()
            ? collect()
            : HierarchyScope::zonesWithAncestry($zoneIds);

        return response()->json([
            'message' => 'Zone assignments saved.',
            'user' => $user->only(['id', 'name', 'email']),
            'zone_ids' => $zoneIds,
            'zones' => $zones,
        ]);
    }

    public function activity(Request $request)
    {
        abort_unless($request->user()->canAssignWork() || $request->user()->isAdmin(), 403);

        $rows = ActivityLog::with('user')
            ->latest()
            ->paginate(40);

        return response()->json($rows);
    }

    public function notifications(Request $request)
    {
        return response()->json(
            AppNotification::where('user_id', $request->user()->id)->latest()->paginate(30)
        );
    }

    public function markNotificationRead(Request $request, AppNotification $notification)
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->id, 403);
        $notification->markRead();

        return response()->json(['message' => 'Marked read', 'notification' => $notification->fresh()]);
    }

    public function markAllNotificationsRead(Request $request)
    {
        AppNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked read.']);
    }

    public function feederSurveys(Request $request)
    {
        abort_unless($request->user()->canCaptureSurveys() || $request->user()->isAdmin() || $request->user()->canApproveSurveys(), 403);
        $user = $request->user();
        $perPage = min(100, max(20, $request->integer('per_page', 50)));

        $query = FeederSurvey::with([
            'surveyor',
            'region',
            'circle',
            'division',
            'zone',
            'substation',
            'feeder',
            'sldPhotos',
        ]);
        if ($user->canApproveSurveys() && ! $user->isFieldExecutive()) {
            $query = SurveyScope::apply($query, $user);
        } else {
            $query->where('surveyor_id', $user->id);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $surveys = $query->latest()->paginate($perPage);
        $surveys->getCollection()->transform(function (FeederSurvey $s) use ($user) {
            $s->releaseExpiredLock();
            $ownPending = FeederSurveyDeleter::canOwnDelete($s)
                && ($user->isAdmin() || (int) $s->surveyor_id === (int) $user->id);
            $managerDelete = $user->canApproveSurveys() && SurveyScope::canView($user, $s);
            $s->setAttribute('can_delete', $ownPending || $managerDelete);

            return $s;
        });

        $statsQuery = FeederSurvey::query();
        if ($user->canApproveSurveys() && ! $user->isFieldExecutive()) {
            $statsQuery = SurveyScope::apply($statsQuery, $user);
        } else {
            $statsQuery->where('surveyor_id', $user->id);
        }

        return response()->json(array_merge($surveys->toArray(), [
            'review_stats' => [
                'dtr_pending' => (clone $statsQuery)->where('status', FeederSurvey::STATUS_DRAFT)->count(),
                'sld_pending' => (clone $statsQuery)->where('status', FeederSurvey::STATUS_SLD_PENDING)->count(),
                'pending_approval' => (clone $statsQuery)->where('status', FeederSurvey::STATUS_PENDING_APPROVAL)->count(),
                'approved' => (clone $statsQuery)->whereIn('status', [FeederSurvey::STATUS_APPROVED, FeederSurvey::STATUS_COMPLETED])->count(),
            ],
        ]));
    }

    public function showFeederSurvey(Request $request, FeederSurvey $feederSurvey)
    {
        abort_unless(SurveyScope::canView($request->user(), $feederSurvey), 403);
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
            ->get(['id', 'dtr_id', 'dtr_code', 'dtr_name', 'status', 'surveyed_at', 'surveyor_id', 'feeder_id', 'created_at']);

        $user = $request->user();
        $ownPending = FeederSurveyDeleter::canOwnDelete($feederSurvey)
            && ($user->isAdmin() || (int) $feederSurvey->surveyor_id === (int) $user->id);
        $managerDelete = $user->canApproveSurveys() && SurveyScope::canView($user, $feederSurvey);

        return response()->json([
            'survey' => $feederSurvey,
            'dtr_surveys' => $dtrSurveys,
            'can_approve' => SurveyScope::canApprove($user, $feederSurvey),
            'can_unlock' => $user->canApproveSurveys() && SurveyScope::canView($user, $feederSurvey) && $feederSurvey->locked_at !== null,
            'can_edit' => $user->canApproveSurveys() && SurveyScope::canView($user, $feederSurvey),
            'can_delete' => $ownPending || $managerDelete,
            'review_counts' => $feederSurvey->reviewCounts($dtrSurveys),
        ]);
    }

    /** FE: permanently delete own pending / rejected feeder survey (then re-survey allowed). */
    public function destroyOwnFeederSurvey(Request $request, FeederSurvey $feederSurvey)
    {
        $user = $request->user();
        abort_unless(
            $user->isFieldExecutive() || $user->isAdmin(),
            403
        );
        abort_unless(
            $user->isAdmin() || (int) $feederSurvey->surveyor_id === (int) $user->id,
            403,
            'You can only delete your own feeder surveys.'
        );

        $id = (int) $feederSurvey->id;
        FeederSurveyDeleter::deleteOwn($feederSurvey, $user);

        return response()->json([
            'message' => 'Feeder survey deleted. You can survey this feeder again.',
            'id' => $id,
        ]);
    }

    /** Manager / admin correction of feeder survey field mismatches (JSON). */
    public function managerUpdateFeederSurvey(Request $request, FeederSurvey $feederSurvey)
    {
        $user = $request->user();
        abort_unless($user->canApproveSurveys() && SurveyScope::canView($user, $feederSurvey), 403);

        $data = $request->validate([
            'feeder_voltage' => ['nullable', 'string', 'max:30'],
            'metering_type' => ['nullable', 'string', 'max:40'],
            'ctpt_available' => ['nullable', 'string', 'max:10'],
            'me_pt_ratio' => ['nullable', 'string', 'max:40'],
            'me_ct_ratio' => ['nullable', 'string', 'max:40'],
            'new_mf' => ['nullable', 'string', 'max:40'],
            'me_installed' => ['nullable', 'string', 'max:10'],
            'me_working' => ['nullable', 'string', 'max:10'],
            'new_smart_meter_installed' => ['nullable', 'string', 'max:40'],
            'new_meter_number' => ['nullable', 'string', 'max:80'],
            'old_meter_number' => ['nullable', 'string', 'max:80'],
            'old_meter_make' => ['nullable', 'string', 'max:80'],
            'old_meter_condition' => ['nullable', 'string', 'max:80'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'gps_accuracy' => ['nullable', 'numeric'],
            'substation_code' => ['nullable', 'string', 'max:50'],
            'substation_name' => ['nullable', 'string', 'max:120'],
            'feeder_code' => ['nullable', 'string', 'max:80'],
            'feeder_name' => ['nullable', 'string', 'max:120'],
        ]);

        $feederSurvey->fill(array_filter($data, fn ($v) => $v !== null));
        $feederSurvey->save();
        ActivityLog::record('feeder_survey.manager_updated', $feederSurvey, ['by' => $user->id]);

        return response()->json([
            'message' => 'Feeder survey updated.',
            'survey' => $feederSurvey->fresh([
                'surveyor',
                'region',
                'circle',
                'division',
                'zone',
                'substation',
                'feeder',
                'sldPhotos',
            ]),
        ]);
    }

    /** Manager / admin hard-delete feeder survey (cascades linked feeder-path DTR + consumers). */
    public function managerDeleteFeederSurvey(Request $request, FeederSurvey $feederSurvey)
    {
        $user = $request->user();
        abort_unless($user->canApproveSurveys() && SurveyScope::canView($user, $feederSurvey), 403);

        $id = (int) $feederSurvey->id;
        FeederSurveyDeleter::deleteManager($feederSurvey, $user);

        return response()->json(['message' => 'Feeder survey deleted.', 'id' => $id]);
    }

    /** Lightweight check: whether this feeder already has a submitted feeder survey. */
    public function feederSurveyStatus(Request $request)
    {
        abort_unless($request->user()->canCaptureSurveys() || $request->user()->isAdmin(), 403);
        $feederId = $request->integer('feeder_id');
        abort_unless($feederId > 0, 422, 'feeder_id is required.');

        // Basic feeder details (draft) count as surveyed so DTR can start; Finish → SLD → approval.
        $survey = FeederSurvey::query()
            ->where('feeder_id', $feederId)
            ->whereIn('status', ['draft', 'sld_pending', 'pending_approval', 'approved', 'completed'])
            ->latest()
            ->first();

        $standaloneDtrIds = DtrSurvey::query()
            ->where('feeder_id', $feederId)
            ->where('surveyor_id', $request->user()->id)
            ->where('entry_source', DtrSurvey::ENTRY_STANDALONE)
            ->whereIn('status', ['pending_approval', 'approved'])
            ->whereNotNull('dtr_id')
            ->pluck('dtr_id')
            ->unique()
            ->values();

        // All DTRs this surveyor already surveyed under this feeder (any path) — for active pickers.
        $surveyedDtrIds = DtrSurvey::query()
            ->where('feeder_id', $feederId)
            ->where('surveyor_id', $request->user()->id)
            ->whereIn('status', ['draft', 'pending_approval', 'approved', 'completed'])
            ->whereNotNull('dtr_id')
            ->pluck('dtr_id')
            ->unique()
            ->values();

        return response()->json([
            'surveyed' => (bool) $survey,
            'status' => $survey?->status,
            'display_status' => $survey?->display_status,
            'survey_id' => $survey?->id,
            'surveyed_at' => $survey?->surveyed_at,
            'feeder_id' => $feederId,
            'feeder_name' => $survey?->feeder_name,
            'dtrs_expected' => $survey?->dtrs_expected ?? 0,
            'dtrs_completed' => $survey?->dtrs_completed ?? 0,
            'standalone_surveyed_dtr_ids' => $standaloneDtrIds,
            'surveyed_dtr_ids' => $surveyedDtrIds,
        ]);
    }

    public function storeFeederSurvey(Request $request)
    {
        // #region agent log
        try {
            $payload = json_encode([
                'sessionId' => 'a2382b',
                'runId' => 'pre-fix',
                'hypothesisId' => 'H1',
                'location' => 'FieldApiController.php:storeFeederSurvey:entry',
                'message' => 'storeFeederSurvey entered',
                'data' => [
                    'user_id' => $request->user()?->id,
                    'action' => $request->input('action'),
                    'has_photo' => $request->hasFile('new_meter_photo'),
                    'field_keys' => array_values(array_diff(array_keys($request->all()), ['new_meter_photo'])),
                ],
                'timestamp' => (int) (microtime(true) * 1000),
            ], JSON_UNESCAPED_SLASHES);
            file_put_contents(dirname(base_path()).DIRECTORY_SEPARATOR.'debug-a2382b.log', $payload.PHP_EOL, FILE_APPEND);
        } catch (\Throwable $e) {
            // ignore debug log failures
        }
        // #endregion
        abort_unless($request->user()->canCaptureSurveys(), 403);
        $data = $this->validateFeederSurvey($request);
        $user = $request->user();
        HierarchyScope::assertZoneAllowed($user, (int) $data['zone_id']);
        WorkAssignment::assertFeederAssigned($user, (int) $data['feeder_id']);

        $feeder = Feeder::findOrFail($data['feeder_id']);
        $substation = Substation::findOrFail($data['substation_id']);

        // No duplicate feeder survey (same feeder) while an active/pending/approved one exists.
        $existingOwn = FeederSurvey::query()
            ->where('feeder_id', $feeder->id)
            ->where('surveyor_id', $user->id)
            ->whereIn('status', ['draft', 'sld_pending', 'pending_approval', 'approved', 'completed'])
            ->latest()
            ->first();
        if ($existingOwn) {
            return response()->json([
                'message' => 'This feeder was already surveyed. Open the existing survey from Continue / Survey Status — duplicate survey is not allowed.',
                'existing_survey_id' => $existingOwn->id,
                'survey' => $existingOwn->load(['surveyor', 'region', 'circle', 'division', 'zone', 'substation', 'feeder']),
            ], 409);
        }

        $existingOther = FeederSurvey::query()
            ->where('feeder_id', $feeder->id)
            ->where('surveyor_id', '!=', $user->id)
            ->whereIn('status', ['sld_pending', 'pending_approval', 'approved', 'completed'])
            ->latest()
            ->first();
        if ($existingOther) {
            return response()->json([
                'message' => 'This feeder was already surveyed by another field executive. Duplicate survey is not allowed.',
                'existing_survey_id' => null,
            ], 409);
        }

        $survey = new FeederSurvey($data);
        $survey->surveyor_id = $user->id;
        $survey->supervisor_id = $user->supervisor_id;
        $survey->surveyed_at = now();
        $survey->feeder_code = $feeder->code;
        $survey->feeder_name = $feeder->name;
        $survey->substation_name = $substation->name;
        $survey->substation_code = $data['substation_code'] ?? (string) $substation->id;
        // Basic feeder details always stay draft until SLD upload completes the survey.
        $survey->status = 'draft';
        $this->applyFeederMeterPhoto($request, $survey);
        $survey->save();
        // History rows need a persisted survey id.
        $this->applyFeederSldPhoto($request, $survey);
        if ($survey->isDirty('sld_photo')) {
            $survey->save();
        }

        // #region agent log
        try {
            $payload = json_encode([
                'sessionId' => 'a2382b',
                'runId' => 'pre-fix',
                'hypothesisId' => 'H1',
                'location' => 'FieldApiController.php:storeFeederSurvey:saved',
                'message' => 'storeFeederSurvey saved',
                'data' => [
                    'survey_id' => $survey->id,
                    'status' => $survey->status,
                ],
                'timestamp' => (int) (microtime(true) * 1000),
            ], JSON_UNESCAPED_SLASHES);
            file_put_contents(dirname(base_path()).DIRECTORY_SEPARATOR.'debug-a2382b.log', $payload.PHP_EOL, FILE_APPEND);
        } catch (\Throwable $e) {
            // ignore debug log failures
        }
        // #endregion

        ActivityLog::record('feeder_survey.draft', $survey);

        return response()->json([
            'message' => 'Feeder basic details submitted successfully.',
            'survey' => $survey->fresh(['surveyor', 'region', 'circle', 'division', 'zone', 'substation', 'feeder']),
        ], 201);
    }

    public function updateFeederSurvey(Request $request, FeederSurvey $feederSurvey)
    {
        abort_unless($request->user()->canCaptureSurveys(), 403);
        abort_unless(
            $request->user()->isAdmin() || (int) $feederSurvey->surveyor_id === (int) $request->user()->id,
            403
        );
        if (! $request->user()->isAdmin()) {
            $feederSurvey->assertEditableBySurveyor();
        }
        abort_unless(in_array($feederSurvey->status, ['draft', 'rejected'], true), 422, 'Only draft/rejected feeder surveys can be edited.');

        $data = $this->validateFeederSurvey($request);
        HierarchyScope::assertZoneAllowed($request->user(), (int) $data['zone_id']);
        WorkAssignment::assertFeederAssigned($request->user(), (int) $data['feeder_id']);
        $feeder = Feeder::findOrFail($data['feeder_id']);
        $substation = Substation::findOrFail($data['substation_id']);

        $feederSurvey->fill($data);
        $feederSurvey->feeder_code = $feeder->code;
        $feederSurvey->feeder_name = $feeder->name;
        $feederSurvey->substation_name = $substation->name;
        $feederSurvey->substation_code = $data['substation_code'] ?? (string) $substation->id;
        // Basic details submit stays draft until SLD upload completes the Feeder→DTR survey.
        $feederSurvey->status = 'draft';
        $feederSurvey->review_remarks = null;
        $feederSurvey->reviewed_at = null;
        $this->applyFeederMeterPhoto($request, $feederSurvey);
        $this->applyFeederSldPhoto($request, $feederSurvey);
        $feederSurvey->save();

        return response()->json([
            'message' => 'Feeder basic details saved.',
            'survey' => $feederSurvey->fresh(['surveyor', 'region', 'circle', 'division', 'zone', 'substation', 'feeder']),
        ]);
    }

    /** Finish DTR work under this feeder → ready for SLD upload. */
    public function finishFeederDtr(Request $request, FeederSurvey $feederSurvey)
    {
        abort_unless($request->user()->canCaptureSurveys(), 403);
        abort_unless(
            $request->user()->isAdmin() || (int) $feederSurvey->surveyor_id === (int) $request->user()->id,
            403
        );
        if (! $request->user()->isAdmin()) {
            $feederSurvey->assertEditableBySurveyor();
        }
        abort_unless(
            in_array($feederSurvey->status, ['draft', 'rejected'], true),
            422,
            'Only draft / rejected feeder surveys can be finished for SLD.'
        );

        $feederSurvey->status = FeederSurvey::STATUS_SLD_PENDING;
        $feederSurvey->save();

        ActivityLog::record('feeder_survey.dtr_finished', $feederSurvey, [
            'dtrs_expected' => $feederSurvey->dtrs_expected,
            'dtrs_completed' => $feederSurvey->dtrs_completed,
        ]);

        return response()->json([
            'message' => 'DTR work finished. Upload SLD from Feeder Survey Status.',
            'survey' => $feederSurvey->fresh(['surveyor', 'region', 'circle', 'division', 'zone', 'substation', 'feeder']),
            'dtrs_expected' => $feederSurvey->dtrs_expected,
            'dtrs_completed' => $feederSurvey->dtrs_completed,
        ]);
    }

    /** Upload SLD image and submit Feeder→DTR survey for manager approval. */
    public function uploadFeederSld(Request $request, FeederSurvey $feederSurvey)
    {
        abort_unless($request->user()->canCaptureSurveys(), 403);
        abort_unless(
            $request->user()->isAdmin() || (int) $feederSurvey->surveyor_id === (int) $request->user()->id,
            403
        );
        if (! $request->user()->isAdmin()) {
            $feederSurvey->assertEditableBySurveyor();
        }
        abort_unless(
            in_array($feederSurvey->status, ['sld_pending', 'rejected', 'pending_approval'], true),
            422,
            'SLD can only be uploaded after Finish DTR (SLD Verification Pending) or when rejected.'
        );
        abort_unless($request->hasFile('sld_photo'), 422, 'SLD photo is required.');

        $request->validate([
            'sld_photo' => ['required', 'file', 'max:12288', new ClientImageFile],
        ]);

        $this->applyFeederSldPhoto($request, $feederSurvey);
        $feederSurvey->status = FeederSurvey::STATUS_PENDING_APPROVAL;
        $feederSurvey->review_remarks = null;
        $feederSurvey->reviewed_at = null;
        $feederSurvey->locked_at = now();
        $feederSurvey->save();

        // Assignment stays until SLD is submitted — then mark done.
        if ($feederSurvey->feeder_id && $feederSurvey->surveyor_id) {
            WorkAssignment::markDoneForFeeder((int) $feederSurvey->surveyor_id, (int) $feederSurvey->feeder_id);
        }

        ActivityLog::record('feeder_survey.sld_submitted', $feederSurvey);
        $this->notifyFeederApprovers($feederSurvey);

        return response()->json([
            'message' => 'SLD uploaded. Feeder survey submitted for manager approval.',
            'survey' => $feederSurvey->fresh([
                'surveyor',
                'region',
                'circle',
                'division',
                'zone',
                'substation',
                'feeder',
                'sldPhotos',
            ]),
        ]);
    }

    public function approveFeederSurvey(Request $request, FeederSurvey $feederSurvey)
    {
        abort_unless(SurveyScope::canApprove($request->user(), $feederSurvey), 403);
        $request->validate(['review_remarks' => ['nullable', 'string', 'max:1000']]);

        $feederSurvey->update([
            'status' => FeederSurvey::STATUS_APPROVED,
            'review_remarks' => $request->review_remarks,
            'reviewed_at' => now(),
            'locked_at' => now(),
            'supervisor_id' => $feederSurvey->supervisor_id ?: $request->user()->id,
        ]);

        ActivityLog::record('feeder_survey.approved', $feederSurvey, ['by' => $request->user()->id]);
        AppNotification::notifyUser(
            (int) $feederSurvey->surveyor_id,
            'Feeder survey approved',
            ($feederSurvey->feeder_name ?: 'Feeder').' survey was approved.',
            null,
            FeederSurvey::class,
            (int) $feederSurvey->id
        );

        return response()->json([
            'message' => 'Feeder survey approved.',
            'survey' => $feederSurvey->fresh(['surveyor', 'region', 'circle', 'division', 'zone', 'substation', 'feeder', 'sldPhotos']),
        ]);
    }

    public function rejectFeederSurvey(Request $request, FeederSurvey $feederSurvey)
    {
        abort_unless(SurveyScope::canApprove($request->user(), $feederSurvey), 403);
        $data = $request->validate(['review_remarks' => ['required', 'string', 'min:1', 'max:1000']]);

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
            null,
            FeederSurvey::class,
            (int) $feederSurvey->id
        );

        return response()->json([
            'message' => 'Feeder survey rejected.',
            'survey' => $feederSurvey->fresh(['surveyor', 'region', 'circle', 'division', 'zone', 'substation', 'feeder', 'sldPhotos']),
        ]);
    }

    /** Manager unlock so surveyor can rework / be reassigned (including after approve). */
    public function unlockFeederSurvey(Request $request, FeederSurvey $feederSurvey)
    {
        $user = $request->user();
        abort_unless($user->canApproveSurveys() && SurveyScope::canView($user, $feederSurvey), 403);
        abort_unless($feederSurvey->locked_at !== null, 422, 'Feeder survey is not locked.');

        $feederSurvey->unlock();
        ActivityLog::record('feeder_survey.unlocked', $feederSurvey, ['by' => $user->id]);

        return response()->json([
            'message' => 'Feeder survey unlocked. Surveyor can rework until it is locked again.',
            'survey' => $feederSurvey->fresh([
                'surveyor',
                'region',
                'circle',
                'division',
                'zone',
                'substation',
                'feeder',
                'sldPhotos',
            ]),
        ]);
    }

    private function notifyFeederApprovers(FeederSurvey $survey): void
    {
        $managerIds = User::query()
            ->whereIn('role', ['manager', 'project_manager', 'admin', 'super_admin'])
            ->where('is_active', true)
            ->pluck('id');

        foreach ($managerIds as $id) {
            if ((int) $id === (int) $survey->surveyor_id) {
                continue;
            }
            AppNotification::notifyUser(
                (int) $id,
                'Feeder survey pending approval',
                ($survey->feeder_name ?: 'Feeder').' · '.$survey->substation_name,
                route('feeder-surveys.show', $survey),
                FeederSurvey::class,
                (int) $survey->id
            );
        }
    }

    private function validateFeederSurvey(Request $request): array
    {
        return $request->validate([
            'region_id' => ['required', 'exists:regions,id'],
            'circle_id' => ['required', 'exists:circles,id'],
            'division_id' => ['required', 'exists:divisions,id'],
            'zone_id' => ['required', 'exists:zones,id'],
            'substation_id' => ['required', 'exists:substations,id'],
            'feeder_id' => ['required', 'exists:feeders,id'],
            'substation_code' => ['nullable', 'string', 'max:50'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'gps_accuracy' => ['nullable', 'numeric'],
            'feeder_voltage' => ['required', 'string', 'max:30'],
            'metering_type' => ['required', 'string', 'max:40'],
            'ctpt_available' => ['required', 'string', 'max:10'],
            'me_pt_ratio' => ['nullable', 'string', 'max:40'],
            'me_ct_ratio' => ['required', 'string', 'max:40'],
            'new_mf' => ['nullable', 'string', 'max:40'],
            'me_installed' => ['required', 'string', 'max:10'],
            'me_working' => ['required', 'string', 'max:10'],
            'new_smart_meter_installed' => ['required', 'string', 'max:40'],
            'new_meter_number' => ['nullable', 'string', 'max:80'],
            'old_meter_number' => ['nullable', 'string', 'max:80'],
            'old_meter_make' => ['nullable', 'string', 'max:80'],
            'old_meter_condition' => ['nullable', 'string', 'max:80'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'action' => ['nullable', 'in:draft,submit'],
        ]);
    }

    private function applyFeederMeterPhoto(Request $request, FeederSurvey $survey): void
    {
        if ($request->hasFile('new_meter_photo')) {
            $survey->new_meter_photo = $this->storeSurveyPhoto(
                $request->file('new_meter_photo'),
                'surveys/feeder'
            );
        }
    }

    private function applyFeederSldPhoto(Request $request, FeederSurvey $survey): void
    {
        if (! $request->hasFile('sld_photo')) {
            return;
        }

        $path = $this->storeSurveyPhoto(
            $request->file('sld_photo'),
            'surveys/feeder/sld'
        );

        if ($survey->exists) {
            $survey->recordSldPhoto($path, $request->user()?->id);
        } else {
            $survey->sld_photo = $path;
        }
    }

    /**
     * Store a survey photo; map conversion/storage failures to HTTP 422 (not generic 500).
     */
    private function storeSurveyPhoto(\Illuminate\Http\UploadedFile $file, string $directory): string
    {
        try {
            return SurveyPhotoStorage::store($file, $directory);
        } catch (\Throwable $e) {
            abort(422, $e->getMessage() ?: 'Unable to store photo. Check PHP GD and storage/app/public permissions.');
        }
    }
}
