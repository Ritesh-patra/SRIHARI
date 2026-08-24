<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AppNotification;
use App\Models\Substation;
use App\Models\SubstationSurvey;
use App\Models\User;
use App\Rules\ClientImageFile;
use App\Support\HierarchyScope;
use App\Support\SurveyPhotoStorage;
use App\Support\SurveyScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Substation Survey / Audit (Flutter field app).
 *
 * GPS captured here is copied onto the `substations` master row when a survey is
 * APPROVED, so the network map can pin substations from master data.
 */
class SubstationSurveyApiController extends Controller
{
    /** Relations loaded on every response. */
    private const RELATIONS = ['surveyor', 'region', 'circle', 'division', 'zone', 'substation'];

    /** Photo request field → storage folder. */
    private const PHOTO_FIELDS = [
        'substation_photo' => 'surveys/substation',
        'meter_photo' => 'surveys/substation/meter',
        'nameplate_photo' => 'surveys/substation/nameplate',
        'sld_photo' => 'surveys/substation/sld',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user->canCaptureSurveys() || $user->isAdmin() || $user->canApproveSurveys(), 403);

        $perPage = min(100, max(20, $request->integer('per_page', 50)));

        $query = SubstationSurvey::with(self::RELATIONS);
        if ($user->canApproveSurveys() && ! $user->isFieldExecutive()) {
            $query = SurveyScope::apply($query, $user);
        } else {
            $query->where('surveyor_id', $user->id);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($substationId = $request->integer('substation_id')) {
            $query->where('substation_id', $substationId);
        }

        $surveys = $query->latest()->paginate($perPage);
        $surveys->getCollection()->transform(function (SubstationSurvey $s) {
            $s->releaseExpiredLock();

            return $s;
        });

        $statsQuery = SubstationSurvey::query();
        if ($user->canApproveSurveys() && ! $user->isFieldExecutive()) {
            $statsQuery = SurveyScope::apply($statsQuery, $user);
        } else {
            $statsQuery->where('surveyor_id', $user->id);
        }

        return response()->json(array_merge($surveys->toArray(), [
            'review_stats' => [
                'draft' => (clone $statsQuery)->where('status', SubstationSurvey::STATUS_DRAFT)->count(),
                'pending_approval' => (clone $statsQuery)->where('status', SubstationSurvey::STATUS_PENDING_APPROVAL)->count(),
                'approved' => (clone $statsQuery)->where('status', SubstationSurvey::STATUS_APPROVED)->count(),
                'rejected' => (clone $statsQuery)->where('status', SubstationSurvey::STATUS_REJECTED)->count(),
            ],
        ]));
    }

    public function show(Request $request, SubstationSurvey $substationSurvey)
    {
        abort_unless(SurveyScope::canView($request->user(), $substationSurvey), 403);
        $substationSurvey->releaseExpiredLock();
        $substationSurvey->load(array_merge(self::RELATIONS, ['supervisor']));

        $user = $request->user();

        return response()->json([
            'survey' => $substationSurvey,
            'photos' => $this->extraPhotos($substationSurvey),
            'can_approve' => SurveyScope::canApprove($user, $substationSurvey),
            'can_edit' => $this->canSurveyorEdit($user, $substationSurvey),
            'can_unlock' => $user->canApproveSurveys()
                && SurveyScope::canView($user, $substationSurvey)
                && $substationSurvey->locked_at !== null,
        ]);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->canCaptureSurveys(), 403);
        $data = $this->validateSurvey($request);
        $user = $request->user();
        HierarchyScope::assertZoneAllowed($user, (int) $data['zone_id']);

        $substation = Substation::findOrFail($data['substation_id']);

        $existingOwn = SubstationSurvey::query()
            ->where('substation_id', $substation->id)
            ->where('surveyor_id', $user->id)
            ->whereIn('status', ['draft', 'pending_approval', 'approved'])
            ->latest()
            ->first();
        if ($existingOwn) {
            return response()->json([
                'message' => 'This substation was already surveyed. Open the existing survey to continue — duplicate survey is not allowed.',
                'existing_survey_id' => $existingOwn->id,
                'survey' => $existingOwn->load(self::RELATIONS),
            ], 409);
        }

        $isSubmit = ($data['action'] ?? 'draft') === 'submit';
        unset($data['action']);

        $survey = new SubstationSurvey($this->filterAvailableColumns($data));
        $survey->surveyor_id = $user->id;
        $survey->supervisor_id = $user->supervisor_id;
        $survey->surveyed_at = now();
        $survey->substation_name = $substation->name;
        $survey->substation_code = $data['substation_code'] ?? (string) $substation->id;
        $survey->status = $isSubmit ? SubstationSurvey::STATUS_PENDING_APPROVAL : SubstationSurvey::STATUS_DRAFT;
        $survey->locked_at = $isSubmit ? now() : null;

        try {
            $this->applyPhotos($request, $survey);
            $survey->save();
            $this->recordExtraPhotos($request, $survey);
        } catch (\Throwable $e) {
            return response()->json(['message' => $this->saveErrorMessage($e)], 500);
        }

        ActivityLog::record($isSubmit ? 'substation_survey.submitted' : 'substation_survey.draft', $survey);
        if ($isSubmit) {
            $this->notifyApprovers($survey);
        }

        return response()->json([
            'message' => $isSubmit
                ? 'Substation survey submitted for approval.'
                : 'Substation survey draft saved.',
            'survey' => $survey->fresh(self::RELATIONS),
        ], 201);
    }

    public function update(Request $request, SubstationSurvey $substationSurvey)
    {
        $user = $request->user();
        abort_unless($user->canCaptureSurveys(), 403);
        abort_unless($user->isAdmin() || (int) $substationSurvey->surveyor_id === (int) $user->id, 403);
        if (! $user->isAdmin()) {
            $substationSurvey->assertEditableBySurveyor();
        }
        abort_unless(
            in_array($substationSurvey->status, ['draft', 'rejected'], true),
            422,
            'Only draft / rejected substation surveys can be edited.'
        );

        $data = $this->validateSurvey($request);
        HierarchyScope::assertZoneAllowed($user, (int) $data['zone_id']);
        $substation = Substation::findOrFail($data['substation_id']);

        $isSubmit = ($data['action'] ?? 'draft') === 'submit';
        unset($data['action']);

        $substationSurvey->fill($this->filterAvailableColumns($data));
        $substationSurvey->substation_name = $substation->name;
        $substationSurvey->substation_code = $data['substation_code'] ?? (string) $substation->id;
        $substationSurvey->status = $isSubmit
            ? SubstationSurvey::STATUS_PENDING_APPROVAL
            : SubstationSurvey::STATUS_DRAFT;
        $substationSurvey->review_remarks = null;
        $substationSurvey->reviewed_at = null;
        $substationSurvey->locked_at = $isSubmit ? now() : null;

        try {
            $this->applyPhotos($request, $substationSurvey);
            $substationSurvey->save();
            $this->recordExtraPhotos($request, $substationSurvey);
        } catch (\Throwable $e) {
            return response()->json(['message' => $this->saveErrorMessage($e)], 500);
        }

        ActivityLog::record($isSubmit ? 'substation_survey.submitted' : 'substation_survey.updated', $substationSurvey);
        if ($isSubmit) {
            $this->notifyApprovers($substationSurvey);
        }

        return response()->json([
            'message' => $isSubmit ? 'Substation survey submitted for approval.' : 'Substation survey saved.',
            'survey' => $substationSurvey->fresh(self::RELATIONS),
        ]);
    }

    public function submit(Request $request, SubstationSurvey $substationSurvey)
    {
        $user = $request->user();
        abort_unless($user->canCaptureSurveys(), 403);
        abort_unless($user->isAdmin() || (int) $substationSurvey->surveyor_id === (int) $user->id, 403);
        abort_unless(
            in_array($substationSurvey->status, ['draft', 'rejected'], true),
            422,
            'Only draft / rejected substation surveys can be submitted.'
        );
        abort_unless(
            (string) $substationSurvey->meter_photo !== '',
            422,
            'Meter photo is required before submitting.'
        );

        $substationSurvey->status = SubstationSurvey::STATUS_PENDING_APPROVAL;
        $substationSurvey->review_remarks = null;
        $substationSurvey->reviewed_at = null;
        $substationSurvey->locked_at = now();

        try {
            $substationSurvey->save();
        } catch (\Throwable $e) {
            return response()->json(['message' => $this->saveErrorMessage($e)], 500);
        }

        ActivityLog::record('substation_survey.submitted', $substationSurvey);
        $this->notifyApprovers($substationSurvey);

        return response()->json([
            'message' => 'Substation survey submitted for approval.',
            'survey' => $substationSurvey->fresh(self::RELATIONS),
        ]);
    }

    public function approve(Request $request, SubstationSurvey $substationSurvey)
    {
        abort_unless(SurveyScope::canApprove($request->user(), $substationSurvey), 403);
        $request->validate(['review_remarks' => ['nullable', 'string', 'max:1000']]);

        $substationSurvey->fill([
            'status' => SubstationSurvey::STATUS_APPROVED,
            'review_remarks' => $request->review_remarks,
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
            'locked_at' => now(),
            'supervisor_id' => $substationSurvey->supervisor_id ?: $request->user()->id,
        ]);

        try {
            $substationSurvey->save();
            $substationSurvey->syncSubstationCoordinates();
        } catch (\Throwable $e) {
            return response()->json(['message' => $this->saveErrorMessage($e)], 500);
        }

        ActivityLog::record('substation_survey.approved', $substationSurvey, ['by' => $request->user()->id]);
        AppNotification::notifyUser(
            (int) $substationSurvey->surveyor_id,
            'Substation survey approved',
            ($substationSurvey->substation_name ?: 'Substation').' survey was approved.',
            null,
            SubstationSurvey::class,
            (int) $substationSurvey->id
        );

        return response()->json([
            'message' => 'Substation survey approved.',
            'survey' => $substationSurvey->fresh(self::RELATIONS),
        ]);
    }

    public function reject(Request $request, SubstationSurvey $substationSurvey)
    {
        abort_unless(SurveyScope::canApprove($request->user(), $substationSurvey), 403);
        $data = $request->validate(['review_remarks' => ['required', 'string', 'min:1', 'max:1000']]);

        $substationSurvey->fill([
            'status' => SubstationSurvey::STATUS_REJECTED,
            'review_remarks' => $data['review_remarks'],
            'reviewed_at' => now(),
            'reviewed_by' => $request->user()->id,
            'locked_at' => null,
        ]);

        try {
            $substationSurvey->save();
        } catch (\Throwable $e) {
            return response()->json(['message' => $this->saveErrorMessage($e)], 500);
        }

        ActivityLog::record('substation_survey.rejected', $substationSurvey, ['reason' => $data['review_remarks']]);
        AppNotification::notifyUser(
            (int) $substationSurvey->surveyor_id,
            'Substation survey rejected',
            'Substation '.($substationSurvey->substation_name ?: '').' was rejected. Reason: '.$data['review_remarks'],
            null,
            SubstationSurvey::class,
            (int) $substationSurvey->id
        );

        return response()->json([
            'message' => 'Substation survey rejected.',
            'survey' => $substationSurvey->fresh(self::RELATIONS),
        ]);
    }

    /** Manager unlock so the surveyor can rework. */
    public function unlock(Request $request, SubstationSurvey $substationSurvey)
    {
        $user = $request->user();
        abort_unless($user->canApproveSurveys() && SurveyScope::canView($user, $substationSurvey), 403);
        abort_unless($substationSurvey->locked_at !== null, 422, 'Substation survey is not locked.');

        $substationSurvey->unlock();
        ActivityLog::record('substation_survey.unlocked', $substationSurvey, ['by' => $user->id]);

        return response()->json([
            'message' => 'Substation survey unlocked. Surveyor can rework until it is locked again.',
            'survey' => $substationSurvey->fresh(self::RELATIONS),
        ]);
    }

    /** @return array<string, mixed> */
    private function validateSurvey(Request $request): array
    {
        $data = $request->validate([
            'region_id' => ['required', 'exists:regions,id'],
            'circle_id' => ['required', 'exists:circles,id'],
            'division_id' => ['required', 'exists:divisions,id'],
            'zone_id' => ['required', 'exists:zones,id'],
            'substation_id' => ['required', 'exists:substations,id'],
            'substation_code' => ['nullable', 'string', 'max:50'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'gps_accuracy' => ['nullable', 'numeric'],
            'substation_type' => ['nullable', 'string', 'max:60'],
            'capacity_mva' => ['nullable', 'numeric'],
            'transformer_count' => ['nullable', 'integer', 'min:0', 'max:99'],
            'incoming_voltage' => ['nullable', 'string', 'max:40'],
            'outgoing_voltage' => ['nullable', 'string', 'max:40'],
            'feeder_count_declared' => ['nullable', 'integer', 'min:0', 'max:99'],
            'meter_number' => ['nullable', 'string', 'max:80'],
            'meter_make' => ['nullable', 'string', 'max:80'],
            'meter_serial_no' => ['nullable', 'string', 'max:80'],
            'metering_type' => ['nullable', 'string', 'max:40'],
            'ct_ratio' => ['nullable', 'string', 'max:40'],
            'pt_ratio' => ['nullable', 'string', 'max:40'],
            'mf' => ['nullable', 'string', 'max:40'],
            'meter_condition' => ['nullable', 'string', 'max:80'],
            'meter_working' => ['nullable', 'string', 'max:10'],
            'observation' => ['nullable', 'string', 'max:2000'],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'substation_photo' => ['nullable', 'file', 'max:12288', new ClientImageFile],
            'meter_photo' => ['nullable', 'file', 'max:12288', new ClientImageFile],
            'nameplate_photo' => ['nullable', 'file', 'max:12288', new ClientImageFile],
            'sld_photo' => ['nullable', 'file', 'max:12288', new ClientImageFile],
            'action' => ['nullable', 'in:draft,submit'],
        ]);

        // Files are stored separately — never mass-assign the uploaded file objects.
        foreach (array_keys(self::PHOTO_FIELDS) as $field) {
            unset($data[$field]);
        }

        $data['meter_working'] = $this->normalizeBool($data['meter_working'] ?? null);

        return $data;
    }

    private function normalizeBool(?string $value): ?bool
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'y', 'on', 'working'], true);
    }

    /**
     * Drop values whose column is missing (production schema may lag behind code).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filterAvailableColumns(array $data): array
    {
        foreach (array_keys($data) as $column) {
            if (! Schema::hasColumn('substation_surveys', (string) $column)) {
                unset($data[$column]);
            }
        }

        return $data;
    }

    private function applyPhotos(Request $request, SubstationSurvey $survey): void
    {
        foreach (self::PHOTO_FIELDS as $field => $directory) {
            if (! $request->hasFile($field)) {
                continue;
            }
            if (! Schema::hasColumn('substation_surveys', $field)) {
                continue;
            }
            $survey->{$field} = $this->storeSurveyPhoto($request->file($field), $directory);
        }
    }

    /** Keep a history row per uploaded photo (optional table). */
    private function recordExtraPhotos(Request $request, SubstationSurvey $survey): void
    {
        foreach (array_keys(self::PHOTO_FIELDS) as $field) {
            if (! $request->hasFile($field) || empty($survey->{$field})) {
                continue;
            }
            $kind = str_replace(['substation_photo', '_photo'], ['substation', ''], $field);
            $survey->recordPhoto((string) $survey->{$field}, $kind, $request->user()?->id);
        }
    }

    /** @return array<int, array{url: string|null, label: string, meta: string}> */
    private function extraPhotos(SubstationSurvey $survey): array
    {
        if (! Schema::hasTable('substation_survey_photos')) {
            return [];
        }

        return $survey->photos()
            ->get()
            ->map(fn ($photo) => [
                'url' => $photo->url,
                'label' => ucfirst((string) ($photo->kind ?: 'photo')),
                'meta' => $photo->created_at?->format('d M Y, H:i') ?? '',
            ])
            ->all();
    }

    private function canSurveyorEdit(User $user, SubstationSurvey $survey): bool
    {
        if (! in_array($survey->status, ['draft', 'rejected'], true)) {
            return false;
        }

        return $user->isAdmin() || (int) $survey->surveyor_id === (int) $user->id;
    }

    private function notifyApprovers(SubstationSurvey $survey): void
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
                'Substation survey pending approval',
                ($survey->substation_name ?: 'Substation').' · '.($survey->zone?->name ?? ''),
                route('substation-surveys.show', $survey),
                SubstationSurvey::class,
                (int) $survey->id
            );
        }
    }

    /** Store a survey photo; map conversion/storage failures to HTTP 422 (not generic 500). */
    private function storeSurveyPhoto(\Illuminate\Http\UploadedFile $file, string $directory): string
    {
        try {
            return SurveyPhotoStorage::store($file, $directory);
        } catch (\Throwable $e) {
            abort(422, $e->getMessage() ?: 'Unable to store photo. Check PHP GD and storage/app/public permissions.');
        }
    }

    private function saveErrorMessage(\Throwable $e): string
    {
        $raw = $e->getMessage();

        if ($e instanceof QueryException || str_contains($raw, 'Unknown column')) {
            if (preg_match("/Unknown column ['`]([^'`]+)['`]/i", $raw, $m)) {
                return 'Database missing column `'.$m[1].'` on substation_surveys. Run ENSURE-substation-survey.sql in phpMyAdmin, then retry.';
            }

            return 'Database schema mismatch on substation_surveys. Run ENSURE-substation-survey.sql, then retry.';
        }

        if (str_contains($raw, 'finfo') || str_contains($raw, 'Fileinfo') || str_contains($raw, 'mime')) {
            return 'Photo processing failed (PHP fileinfo/GD). Check hosting PHP extensions and storage permissions.';
        }

        $short = trim(preg_replace('/\s+/', ' ', $raw) ?? $raw);
        if (strlen($short) > 220) {
            $short = substr($short, 0, 217).'...';
        }

        return $short !== '' ? 'Server error while saving substation survey: '.$short : 'Server error while saving substation survey.';
    }
}
