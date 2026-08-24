<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class WorkAssignment extends Model
{
    public const STATUS_OPEN = 'open';

    /** @deprecated use STATUS_STARTED */
    public const STATUS_IN_PROGRESS = 'started';

    public const STATUS_STARTED = 'started';

    public const STATUS_DONE = 'done';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_REASSIGNED = 'reassigned';

    public const STATUS_CANCELLED = 'cancelled';

    /** Statuses that grant FE survey access to the feeder. */
    public const ACTIVE_STATUSES = [self::STATUS_OPEN, self::STATUS_STARTED];

    protected $fillable = [
        'assigned_to',
        'assigned_by',
        'reassigned_from',
        'feeder_id',
        'zone_id',
        'dtr_id',
        'status',
        'started_at',
        'work_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'started_at' => 'datetime',
        ];
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function previousAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reassigned_from');
    }

    public function feeder(): BelongsTo
    {
        return $this->belongsTo(Feeder::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function dtr(): BelongsTo
    {
        return $this->belongsTo(Dtr::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function canReassign(): bool
    {
        return $this->status === self::STATUS_OPEN && $this->started_at === null;
    }

    /** True if this FE already has a feeder/DTR survey for the feeder (locks reassignment). */
    public function hasSurveyActivity(): bool
    {
        if (! $this->feeder_id || ! $this->assigned_to) {
            return false;
        }

        if (FeederSurvey::query()
            ->where('surveyor_id', $this->assigned_to)
            ->where('feeder_id', $this->feeder_id)
            ->exists()) {
            return true;
        }

        return DtrSurvey::query()
            ->where('surveyor_id', $this->assigned_to)
            ->where('feeder_id', $this->feeder_id)
            ->exists();
    }

    /** Mark open → started when FE begins surveying this feeder. */
    public function markStarted(): bool
    {
        if ($this->status !== self::STATUS_OPEN) {
            return false;
        }

        $this->status = self::STATUS_STARTED;
        $this->started_at = $this->started_at ?? now();
        $this->save();

        return true;
    }

    /**
     * Active feeder IDs assigned to this FE (open or started).
     *
     * @return Collection<int, int>
     */
    public static function assignedFeederIdsFor(User $user): Collection
    {
        if (! $user->isFieldExecutive()) {
            return collect();
        }

        return static::query()
            ->where('assigned_to', $user->id)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->whereNotNull('feeder_id')
            ->pluck('feeder_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Abort 403 unless FE has an active assignment for the feeder.
     * Admins / managers / PMs are exempt.
     */
    public static function assertFeederAssigned(User $user, int $feederId): void
    {
        // Only Field Executives are gated by work assignments.
        // Super Admin / Admin / managers may survey any feeder.
        if (! $user->requiresFeederAssignment()) {
            return;
        }

        $assignment = static::query()
            ->where('assigned_to', $user->id)
            ->where('feeder_id', $feederId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->latest('id')
            ->first();

        abort_unless(
            $assignment !== null,
            403,
            'This feeder is not assigned to you. Contact your manager.'
        );

        if ($assignment->status === self::STATUS_OPEN) {
            $assignment->markStarted();
        }
    }

    /**
     * Legacy hook — assignments no longer auto-close by work_date.
     * They stay active until SLD completion (done) or manager cancel/close.
     */
    public static function syncClosedStatuses(): int
    {
        return 0;
    }

    /** Mark active assignment(s) done when FE finishes feeder with SLD. */
    public static function markDoneForFeeder(int $surveyorId, int $feederId): int
    {
        return static::query()
            ->where('assigned_to', $surveyorId)
            ->where('feeder_id', $feederId)
            ->whereIn('status', self::ACTIVE_STATUSES)
            ->update(['status' => self::STATUS_DONE]);
    }

    /** Manager marks assignment complete without requiring SLD. */
    public function markCompletedByManager(): void
    {
        $this->status = self::STATUS_CLOSED;
        $this->save();
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [
            self::STATUS_CLOSED,
            self::STATUS_DONE,
            self::STATUS_CANCELLED,
            self::STATUS_REASSIGNED,
        ], true);
    }

    /** API payload helpers. */
    public function toApiArray(): array
    {
        $canReassign = $this->canReassign() && ! $this->hasSurveyActivity();
        $canComplete = in_array($this->status, self::ACTIVE_STATUSES, true);

        return [
            'id' => $this->id,
            'assigned_to' => $this->assigned_to,
            'assigned_by' => $this->assigned_by,
            'feeder_id' => $this->feeder_id,
            'zone_id' => $this->zone_id,
            'dtr_id' => $this->dtr_id,
            'status' => $this->status,
            'started_at' => $this->started_at?->toIso8601String(),
            'work_date' => $this->work_date?->toDateString(),
            'notes' => $this->notes,
            'can_reassign' => $canReassign,
            'can_complete' => $canComplete,
            'assignee' => $this->assignee?->only(['id', 'name', 'email']),
            'assigner' => $this->assigner?->only(['id', 'name', 'email']),
            'feeder' => $this->feeder ? $this->feeder->only(['id', 'code', 'name', 'substation_id']) : null,
            'zone' => $this->zone ? [
                'id' => $this->zone->id,
                'name' => $this->zone->name,
            ] : null,
            'dtr' => $this->dtr?->only(['id', 'code', 'name']),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
