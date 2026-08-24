<?php

namespace App\Models;

use App\Support\SurveyPhotoStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DtrSurvey extends Model
{
    /** Auto-clear locks after this many days (on-read, mirrors feeder). */
    public const LOCK_AUTO_UNLOCK_DAYS = 2;

    protected $appends = [
        'dtr_overall_photo_url',
        'smart_meter_photo_url',
        'ct_ratio_photo_url',
        'is_locked',
    ];

    protected $fillable = [
        'surveyor_id',
        'supervisor_id',
        'surveyed_at',
        'region_id',
        'circle_id',
        'division_id',
        'zone_id',
        'substation_id',
        'feeder_id',
        'dtr_id',
        'feeder_code',
        'feeder_name',
        'dtr_code',
        'dtr_name',
        'latitude',
        'longitude',
        'gps_accuracy',
        'dtr_capacity_kva',
        'dtr_condition',
        'lt_line_type',
        'smart_meter_status',
        'old_meter_condition',
        'old_msn',
        'old_meter_make',
        'new_msn',
        'new_meter_make',
        'new_meter_ct_ratio',
        'new_meter_mf',
        'dtr_overall_photo',
        'smart_meter_photo',
        'ct_ratio_photo',
        'observation',
        'entry_source',
        'feeder_survey_id',
        'status',
        'review_remarks',
        'reviewed_at',
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

    public const ENTRY_STANDALONE = 'standalone';

    public const ENTRY_FEEDER = 'feeder';

    /** Mapping correction review (parallel to survey status — does not block survey). */
    public const MAPPING_PENDING = 'pending';

    public const MAPPING_APPROVED = 'approved';

    public const MAPPING_REJECTED = 'rejected';

    protected function casts(): array
    {
        return [
            'surveyed_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'locked_at' => 'datetime',
            'consumer_survey_completed_at' => 'datetime',
            'mapping_correction_reviewed_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'gps_accuracy' => 'float',
        ];
    }

    public function isMappingCorrectionPending(): bool
    {
        return $this->mapping_correction_status === self::MAPPING_PENDING;
    }

    public function mappingCorrectionLabel(): ?string
    {
        return match ($this->mapping_correction_status) {
            self::MAPPING_PENDING => 'Mapping Correction Request (Field Verification)',
            self::MAPPING_APPROVED => 'Mapping Correction Approved',
            self::MAPPING_REJECTED => 'Mapping Correction Rejected',
            default => null,
        };
    }

    public function getIsLockedAttribute(): bool
    {
        $this->releaseExpiredLock();

        return $this->locked_at !== null;
    }

    /** Clear lock when locked_at is older than LOCK_AUTO_UNLOCK_DAYS. */
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

    public function lock(?\DateTimeInterface $at = null): void
    {
        $this->forceFill(['locked_at' => $at ?? now()])->save();
    }

    public function unlock(): void
    {
        $this->forceFill(['locked_at' => null])->save();
    }

    public function surveyor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'surveyor_id');
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
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

    public function feeder(): BelongsTo
    {
        return $this->belongsTo(Feeder::class);
    }

    public function dtr(): BelongsTo
    {
        return $this->belongsTo(Dtr::class);
    }

    public function feederSurvey(): BelongsTo
    {
        return $this->belongsTo(FeederSurvey::class);
    }

    public function masterFeeder(): BelongsTo
    {
        return $this->belongsTo(Feeder::class, 'master_feeder_id');
    }

    public function reportedFeeder(): BelongsTo
    {
        return $this->belongsTo(Feeder::class, 'reported_feeder_id');
    }

    public function mappingCorrectionReviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mapping_correction_reviewed_by');
    }

    public function consumerSurveys(): HasMany
    {
        return $this->hasMany(ConsumerSurvey::class);
    }

    public function reactivationRequests(): HasMany
    {
        return $this->hasMany(DtrReactivationRequest::class);
    }

    /** Submitted/completed standalone DTR surveys (exclude from Feeder→DTR redo). */
    public function isStandaloneSubmitted(): bool
    {
        return $this->entry_source === self::ENTRY_STANDALONE
            && in_array($this->status, ['pending_approval', 'approved'], true);
    }

    public function isEditable(): bool
    {
        $this->releaseExpiredLock();
        if ($this->locked_at !== null) {
            return false;
        }

        // Unlocked approved surveys can be reworked after manager Unlock.
        return in_array($this->status, ['draft', 'rejected', 'pending_approval', 'approved'], true);
    }

    public function isApprovedForConsumerSurvey(): bool
    {
        // pending_approval / completed kept for legacy rows before DTR auto-approve-on-submit.
        return in_array($this->status, ['approved', 'pending_approval', 'completed'], true)
            && ! $this->consumer_survey_completed_at;
    }

    /** Display status for DTR Status board */
    public function displayStatus(): string
    {
        if ($this->consumer_survey_completed_at) {
            return 'consumer_survey_completed';
        }

        return match ($this->status) {
            'pending_approval' => 'pending_for_approval',
            'rejected' => 'rejected',
            'approved' => 'approved',
            default => $this->status,
        };
    }

    public function displayStatusLabel(): string
    {
        $base = match ($this->displayStatus()) {
            'pending_for_approval' => 'Pending for Approval',
            'rejected' => 'Rejected',
            'approved' => 'DTR Already Surveyed',
            'consumer_survey_completed' => 'Consumer Survey Completed',
            'draft' => 'Draft',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };

        $mapping = $this->mappingCorrectionLabel();
        if ($mapping && $this->isMappingCorrectionPending()) {
            return $base.' · Mapping Correction Pending';
        }

        return $base;
    }

    public function getDtrOverallPhotoUrlAttribute(): ?string
    {
        return SurveyPhotoStorage::url($this->dtr_overall_photo);
    }

    public function getSmartMeterPhotoUrlAttribute(): ?string
    {
        return SurveyPhotoStorage::url($this->smart_meter_photo);
    }

    public function getCtRatioPhotoUrlAttribute(): ?string
    {
        return SurveyPhotoStorage::url($this->ct_ratio_photo);
    }
}
