<?php

namespace App\Support;

use App\Models\DtrSurvey;
use App\Models\FeederSurvey;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SurveyScope
{
    /** Apply role + geo visibility to a DtrSurvey or FeederSurvey query. */
    public static function apply(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return $query;
        }

        if ($user->isFieldExecutive()) {
            return $query->where('surveyor_id', $user->id);
        }

        if ($user->isManager()) {
            $divisionIds = $user->scopeIds('division');
            $teamIds = $user->surveyors()->pluck('id');

            return $query->where(function ($q) use ($user, $divisionIds, $teamIds) {
                $q->where('supervisor_id', $user->id);
                if ($divisionIds->isNotEmpty()) {
                    $q->orWhereIn('division_id', $divisionIds);
                }
                if ($teamIds->isNotEmpty()) {
                    $q->orWhereIn('surveyor_id', $teamIds);
                }
            });
        }

        if ($user->isProjectManager()) {
            $circleIds = $user->scopeIds('circle');
            if ($circleIds->isNotEmpty()) {
                return $query->whereIn('circle_id', $circleIds);
            }

            return $query->whereRaw('1 = 0');
        }

        return $query->whereRaw('1 = 0');
    }

    public static function canApprove(User $user, Model $survey): bool
    {
        if ($survey->status !== 'pending_approval') {
            return false;
        }

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        if ($user->isManager()) {
            $divisionIds = $user->scopeIds('division');
            if ($divisionIds->contains((int) $survey->division_id)) {
                return true;
            }
            if ((int) $survey->supervisor_id === (int) $user->id) {
                return true;
            }

            return $user->surveyors()->where('id', (int) $survey->surveyor_id)->exists();
        }

        if ($user->isProjectManager()) {
            return $user->scopeIds('circle')->contains((int) $survey->circle_id);
        }

        return false;
    }

    public static function canView(User $user, Model $survey): bool
    {
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return true;
        }

        if ($user->isFieldExecutive()) {
            return (int) $survey->surveyor_id === (int) $user->id;
        }

        if ($user->isManager()) {
            $divisionIds = $user->scopeIds('division');
            if ($divisionIds->contains((int) $survey->division_id)) {
                return true;
            }
            if ((int) $survey->supervisor_id === (int) $user->id) {
                return true;
            }

            return $user->surveyors()->where('id', (int) $survey->surveyor_id)->exists();
        }

        if ($user->isProjectManager()) {
            return $user->scopeIds('circle')->contains((int) $survey->circle_id);
        }

        return false;
    }

    /** @deprecated Prefer canApprove(User, Model) — kept for call-site clarity. */
    public static function canApproveFeeder(User $user, FeederSurvey $survey): bool
    {
        return self::canApprove($user, $survey);
    }

    /** @deprecated Prefer canView(User, Model) — kept for call-site clarity. */
    public static function canViewFeeder(User $user, FeederSurvey $survey): bool
    {
        return self::canView($user, $survey);
    }
}
