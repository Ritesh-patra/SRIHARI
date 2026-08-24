<?php

namespace App\Models;

use App\Support\SurveyPhotoStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class SubstationSurvey extends Model
{
    /** Auto-clear locks after this many days (same rule as feeder surveys). */
    public const LOCK_AUTO_UNLOCK_DAYS = 2;

    /** Status machine: draft → pending_approval → approved | rejected (rejected re-editable). */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'surveyor_id',
        'supervisor_id',
        'surveyed_at',
        'region_id',
        'circle_id',
        'division_id',
        'zone_id',
        'substation_id',
        'substation_code',
        'substation_name',
        'latitude',
        'longitude',
        'gps_accuracy',
        'substation_type',
        'capacity_mva',
        'transformer_count',
        'incoming_voltage',
        'outgoing_voltage',
        'feeder_count_declared',
        'meter_number',
        'meter_make',
        'meter_serial_no',
        'metering_type',
        'ct_ratio',
        'pt_ratio',
        'mf',
        'meter_condition',
        'meter_working',
        'substation_photo',
        'meter_photo',
        'nameplate_photo',
        'sld_photo',
        'observation',
        'remarks',
        'status',
        'review_remarks',
        'reviewed_at',
        'reviewed_by',
        'locked_at',
    ];

    protected $appends = [
        'display_status',
        'is_locked',
        'substation_photo_url',
        'meter_photo_url',
        'nameplate_photo_url',
        'sld_photo_url',
    ];

    protected function casts(): array
    {
        return [
            'surveyed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'locked_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'gps_accuracy' => 'float',
            'capacity_mva' => 'float',
            'transformer_count' => 'integer',
            'feeder_count_declared' => 'integer',
            'meter_working' => 'boolean',
        ];
    }

    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surveyor_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(Circle::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    public function substation(): BelongsTo
    {
        return $this->belongsTo(Substation::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(SubstationSurveyPhoto::class)->orderByDesc('id');
    }

    public function getDisplayStatusAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PENDING_APPROVAL => 'Pending Approval',
            self::STATUS_APPROVED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => str_replace('_', ' ', ucfirst((string) $this->status)),
        };
    }

    public function getSubstationPhotoUrlAttribute(): ?string
    {
        return SurveyPhotoStorage::url($this->substation_photo);
    }

    public function getMeterPhotoUrlAttribute(): ?string
    {
        return SurveyPhotoStorage::url($this->meter_photo);
    }

    public function getNameplatePhotoUrlAttribute(): ?string
    {
        return SurveyPhotoStorage::url($this->nameplate_photo);
    }

    public function getSldPhotoUrlAttribute(): ?string
    {
        return SurveyPhotoStorage::url($this->sld_photo);
    }

    public function getIsLockedAttribute(): bool
    {
        $this->releaseExpiredLock();

        return $this->locked_at !== null;
    }

    /** Keep an extra photo row so managers can see every upload attempt. */
    public function recordPhoto(string $path, string $kind, ?int $uploadedBy = null): void
    {
        if (! Schema::hasTable('substation_survey_photos')) {
            return;
        }

        $this->photos()->create([
            'path' => $path,
            'kind' => $kind,
            'uploaded_by' => $uploadedBy,
        ]);
    }

    /** Clear lock when locked_at is older than LOCK_AUTO_UNLOCK_DAYS (works without cron). */
    public function releaseExpiredLock(): bool
    {
        if ($this->locked_at === null) {
            return false;
        }

        if ($this->locked_at->gt(now()->subDays(self::LOCK_AUTO_UNLOCK_DAYS))) {
            return false;
        }

        $this->forceFill(['locked_at' => null])->saveQuietly();

        return true;
    }

    public function unlock(): void
    {
        $this->forceFill(['locked_at' => null])->save();
    }

    /** Surveyor must not mutate while locked (pending approval / approved). */
    public function assertEditableBySurveyor(): void
    {
        $this->releaseExpiredLock();
        abort_if($this->locked_at !== null, 422, 'This substation survey is locked. Ask a manager to unlock it for rework.');
    }

    /**
     * Copy approved GPS onto the substation master row so the network map can pin it.
     * Guarded because production may not have the geo columns yet.
     */
    public function syncSubstationCoordinates(): void
    {
        if (! $this->substation_id || $this->latitude === null || $this->longitude === null) {
            return;
        }

        if (! Schema::hasColumn('substations', 'latitude') || ! Schema::hasColumn('substations', 'longitude')) {
            return;
        }

        $substation = $this->substation ?: Substation::find($this->substation_id);
        if (! $substation) {
            return;
        }

        $substation->forceFill([
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ])->save();
    }
}
