<?php

namespace App\Models;

use App\Support\SurveyPhotoStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class FeederSurvey extends Model
{
    /** Keep only the newest N SLD verification photos per survey. */
    public const SLD_PHOTO_RETENTION = 3;

    /** Auto-clear locks after this many days (scheduler + on-read). */
    public const LOCK_AUTO_UNLOCK_DAYS = 2;

    /** Status machine:
     *  draft → (Finish DTR) → sld_pending → (SLD upload) → pending_approval → approved | rejected
     *  rejected can re-upload SLD → pending_approval
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SLD_PENDING = 'sld_pending';
    public const STATUS_PENDING_APPROVAL = 'pending_approval';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';

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
        'substation_code',
        'substation_name',
        'feeder_code',
        'feeder_name',
        'latitude',
        'longitude',
        'gps_accuracy',
        'feeder_voltage',
        'metering_type',
        'ctpt_available',
        'me_pt_ratio',
        'me_ct_ratio',
        'new_mf',
        'me_installed',
        'me_working',
        'new_smart_meter_installed',
        'new_meter_number',
        'new_meter_photo',
        'sld_photo',
        'old_meter_number',
        'old_meter_make',
        'old_meter_condition',
        'remarks',
        'status',
        'review_remarks',
        'reviewed_at',
        'locked_at',
    ];

    protected $appends = [
        'display_status',
        'dtrs_expected',
        'dtrs_completed',
        'is_locked',
        'sld_photo_url',
        'new_meter_photo_url',
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

    public function sldPhotos(): HasMany
    {
        return $this->hasMany(FeederSurveySldPhoto::class)->orderByDesc('id');
    }

    /**
     * Store a new SLD path as current + history, then prune to the last 3 photos.
     */
    public function recordSldPhoto(string $path, ?int $uploadedBy = null): void
    {
        $this->sldPhotos()->create([
            'path' => $path,
            'uploaded_by' => $uploadedBy,
        ]);

        $this->sld_photo = $path;

        $keepIds = $this->sldPhotos()
            ->reorder()
            ->orderByDesc('id')
            ->limit(self::SLD_PHOTO_RETENTION)
            ->pluck('id');

        $stale = $this->sldPhotos()
            ->reorder()
            ->when($keepIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $keepIds))
            ->when($keepIds->isEmpty(), fn ($q) => $q->whereRaw('1 = 0'))
            ->get();

        foreach ($stale as $photo) {
            if ($photo->path) {
                Storage::disk('public')->delete($photo->path);
            }
            $photo->delete();
        }
    }

    public function getDisplayStatusAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT => 'Pending DTR Survey',
            self::STATUS_SLD_PENDING => 'SLD Verification Pending',
            self::STATUS_PENDING_APPROVAL => 'Pending Approval',
            self::STATUS_APPROVED, self::STATUS_COMPLETED => 'Approved',
            self::STATUS_REJECTED => 'Rejected',
            default => str_replace('_', ' ', ucfirst((string) $this->status)),
        };
    }

    public function getDtrsExpectedAttribute(): int
    {
        if (! $this->feeder_id) {
            return 0;
        }

        return (int) Dtr::query()
            ->where('feeder_id', $this->feeder_id)
            ->where('is_active', true)
            ->count();
    }

    public function getDtrsCompletedAttribute(): int
    {
        if (! $this->feeder_id || ! $this->surveyor_id) {
            return 0;
        }

        return (int) DtrSurvey::query()
            ->where('feeder_id', $this->feeder_id)
            ->where('surveyor_id', $this->surveyor_id)
            ->whereIn('status', ['pending_approval', 'approved', 'completed'])
            ->distinct()
            ->count('dtr_id');
    }

    public function getSldPhotoUrlAttribute(): ?string
    {
        return SurveyPhotoStorage::url($this->sld_photo);
    }

    public function getNewMeterPhotoUrlAttribute(): ?string
    {
        return SurveyPhotoStorage::url($this->new_meter_photo);
    }

    public function getIsLockedAttribute(): bool
    {
        $this->releaseExpiredLock();

        return $this->locked_at !== null;
    }

    public function needsSldUpload(): bool
    {
        return in_array($this->status, [self::STATUS_SLD_PENDING, self::STATUS_REJECTED], true)
            || ($this->status === self::STATUS_PENDING_APPROVAL && empty($this->sld_photo));
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

    public function lock(?\DateTimeInterface $at = null): void
    {
        $this->forceFill(['locked_at' => $at ?? now()])->save();
    }

    public function unlock(): void
    {
        $this->forceFill(['locked_at' => null])->save();
    }

    /** Surveyor must not mutate while locked (pending approval / approved, etc.). */
    public function assertEditableBySurveyor(): void
    {
        $this->releaseExpiredLock();
        abort_if($this->locked_at !== null, 422, 'This feeder survey is locked. Ask a manager to unlock it for rework.');
    }

    /**
     * Counts for manager review chips.
     *
     * @param  \Illuminate\Support\Collection<int, DtrSurvey>|null  $dtrSurveys
     * @return array{dtr_pending:int,dtr_approved:int,sld_pending:int,feeder_approved:int}
     */
    public function reviewCounts($dtrSurveys = null): array
    {
        $expected = $this->dtrs_expected;
        $completed = $this->dtrs_completed;
        $dtrPending = max(0, $expected - $completed);

        $dtrApproved = 0;
        if ($dtrSurveys !== null) {
            $dtrApproved = $dtrSurveys
                ->whereIn('status', ['approved', 'completed'])
                ->unique('dtr_id')
                ->count();
        } elseif ($this->feeder_id && $this->surveyor_id) {
            $dtrApproved = (int) DtrSurvey::query()
                ->where('feeder_id', $this->feeder_id)
                ->where('surveyor_id', $this->surveyor_id)
                ->whereIn('status', ['approved', 'completed'])
                ->distinct()
                ->count('dtr_id');
        }

        return [
            'dtr_pending' => $dtrPending,
            'dtr_approved' => $dtrApproved,
            'sld_pending' => $this->needsSldUpload() || $this->status === self::STATUS_SLD_PENDING ? 1 : 0,
            'feeder_approved' => in_array($this->status, [self::STATUS_APPROVED, self::STATUS_COMPLETED], true) ? 1 : 0,
        ];
    }
}
