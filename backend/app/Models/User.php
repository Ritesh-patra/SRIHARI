<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

#[Fillable([
    'name',
    'email',
    'phone',
    'avatar',
    'password',
    'role',
    'supervisor_id',
    'is_active',
    'can_consumer_survey_approve',
    'force_password_change',
    'last_login_at',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_PROJECT_MANAGER = 'project_manager';

    public const ROLE_MANAGER = 'manager';

    public const ROLE_FIELD_EXECUTIVE = 'field_executive';

    public const ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMIN,
        self::ROLE_PROJECT_MANAGER,
        self::ROLE_MANAGER,
        self::ROLE_FIELD_EXECUTIVE,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'can_consumer_survey_approve' => 'boolean',
            'force_password_change' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function surveyors(): HasMany
    {
        return $this->hasMany(User::class, 'supervisor_id');
    }

    public function surveys(): HasMany
    {
        return $this->hasMany(DtrSurvey::class, 'surveyor_id');
    }

    public function scopes(): HasMany
    {
        return $this->hasMany(UserScope::class);
    }

    public function notificationsInbox(): HasMany
    {
        return $this->hasMany(AppNotification::class)->latest();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkAssignment::class, 'assigned_to');
    }

    public function scopeIds(string $type): Collection
    {
        return $this->scopes()
            ->where('scope_type', $type)
            ->pluck('scope_id')
            ->map(fn ($id) => (int) $id);
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_SUPER_ADMIN => 'Super Admin',
            self::ROLE_ADMIN => 'Admin',
            self::ROLE_PROJECT_MANAGER => 'Project Manager',
            self::ROLE_MANAGER => 'Manager',
            self::ROLE_FIELD_EXECUTIVE => 'Field Executive',
            'supervisor' => 'Manager',
            'surveyor' => 'Field Executive',
            default => ucfirst(str_replace('_', ' ', (string) $this->role)),
        };
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPER_ADMIN;
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPER_ADMIN], true);
    }

    public function isProjectManager(): bool
    {
        return $this->role === self::ROLE_PROJECT_MANAGER;
    }

    public function isManager(): bool
    {
        return in_array($this->role, [self::ROLE_MANAGER, 'supervisor'], true);
    }

    /** @deprecated use isManager */
    public function isSupervisor(): bool
    {
        return $this->isManager() || $this->isProjectManager();
    }

    public function isFieldExecutive(): bool
    {
        return in_array($this->role, [self::ROLE_FIELD_EXECUTIVE, 'surveyor'], true);
    }

    /** @deprecated use isFieldExecutive */
    public function isSurveyor(): bool
    {
        return $this->isFieldExecutive();
    }

    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    public function canApproveSurveys(): bool
    {
        // Manager / Project Manager review field work (mobile). Super Admin / Admin also can.
        return $this->isAdmin() || $this->isManager() || $this->isProjectManager();
    }

    /**
     * Consumer survey approval (web + mobile).
     * Admin / Super Admin / Manager / Project Manager always allowed
     * (same review roles as DTR survey approval).
     */
    public function canApproveConsumerSurveys(): bool
    {
        return $this->isAdmin() || $this->isManager() || $this->isProjectManager();
    }

    public function canAssignWork(): bool
    {
        return $this->isManager() || $this->isProjectManager() || $this->isAdmin();
    }

    public function canCaptureSurveys(): bool
    {
        // Super Admin / Admin may run field capture flows unrestricted.
        return $this->isFieldExecutive() || $this->isAdmin();
    }

    /**
     * Field executives must use manager-assigned feeders.
     * Super Admin / Admin / managers are never assignment-gated.
     */
    public function requiresFeederAssignment(): bool
    {
        return $this->isFieldExecutive();
    }

    /** Mobile app roles (Super Admin may use the app with full control). */
    public function isMobileUser(): bool
    {
        return $this->isFieldExecutive()
            || $this->isManager()
            || $this->isProjectManager()
            || $this->isSuperAdmin();
    }
}
