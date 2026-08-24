<?php

namespace App\Support;

use App\Models\Circle;
use App\Models\Division;
use App\Models\Feeder;
use App\Models\Region;
use App\Models\Substation;
use App\Models\User;
use App\Models\WorkAssignment;
use App\Models\Zone;
use Illuminate\Support\Collection;

class HierarchyScope
{
    /**
     * Zone IDs the user may survey / see.
     * null = unrestricted (admin / no scopes configured).
     *
     * @return Collection<int, int>|null
     */
    public static function allowedZoneIds(User $user): ?Collection
    {
        if ($user->isAdmin() || $user->isSuperAdmin()) {
            return null;
        }

        $zoneIds = $user->scopeIds('zone');
        if ($zoneIds->isNotEmpty()) {
            return $zoneIds->unique()->values();
        }

        $divisionIds = $user->scopeIds('division');
        if ($divisionIds->isNotEmpty()) {
            return Zone::query()
                ->whereIn('division_id', $divisionIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
        }

        $circleIds = $user->scopeIds('circle');
        if ($circleIds->isNotEmpty()) {
            $divIds = Division::query()->whereIn('circle_id', $circleIds)->pluck('id');

            return Zone::query()
                ->whereIn('division_id', $divIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
        }

        $regionIds = $user->scopeIds('region');
        if ($regionIds->isNotEmpty()) {
            $circIds = Circle::query()->whereIn('region_id', $regionIds)->pluck('id');
            $divIds = Division::query()->whereIn('circle_id', $circIds)->pluck('id');

            return Zone::query()
                ->whereIn('division_id', $divIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
        }

        // Managers / PMs with no scopes: unrestricted listing for assign UI;
        // Field executives with no scopes: unrestricted (legacy demo).
        return null;
    }

    /**
     * Zones a manager/PM may assign to FEs (intersection of their own scope).
     *
     * @return Collection<int, int>|null null = all zones
     */
    public static function assignableZoneIds(User $manager): ?Collection
    {
        if ($manager->isAdmin() || $manager->isSuperAdmin()) {
            return null;
        }

        return self::allowedZoneIds($manager);
    }

    public static function assertZoneAllowed(User $user, int $zoneId): void
    {
        $allowed = self::allowedZoneIds($user);
        if ($allowed === null) {
            return;
        }

        abort_unless($allowed->contains($zoneId), 403, 'You are not assigned to survey this zone.');
    }

    /**
     * Prune a loaded Region→…→DTR tree to allowed zones (and drop empty parents).
     *
     * @param  Collection<int, Region>  $regions
     * @return Collection<int, Region>
     */
    public static function pruneRegionTree(Collection $regions, User $user): Collection
    {
        $allowed = self::allowedZoneIds($user);
        if ($allowed === null) {
            return $regions;
        }

        $allowedSet = $allowed->flip();

        return $regions
            ->map(function (Region $region) use ($allowedSet) {
                $circles = $region->circles
                    ->map(function (Circle $circle) use ($allowedSet) {
                        $divisions = $circle->divisions
                            ->map(function (Division $division) use ($allowedSet) {
                                $zones = $division->zones->filter(fn (Zone $z) => $allowedSet->has($z->id))->values();
                                $division->setRelation('zones', $zones);

                                return $zones->isNotEmpty() ? $division : null;
                            })
                            ->filter()
                            ->values();
                        $circle->setRelation('divisions', $divisions);

                        return $divisions->isNotEmpty() ? $circle : null;
                    })
                    ->filter()
                    ->values();
                $region->setRelation('circles', $circles);

                return $circles->isNotEmpty() ? $region : null;
            })
            ->filter()
            ->values();
    }

    /**
     * For field executives: keep only feeders with an active work assignment.
     * Empty substations / zones / parents are dropped so the FE cannot pick unassigned feeders.
     *
     * @param  Collection<int, Region>  $regions
     * @param  Collection<int, int>  $feederIds
     * @return Collection<int, Region>
     */
    public static function pruneToAssignedFeeders(Collection $regions, Collection $feederIds): Collection
    {
        $allowedSet = $feederIds->map(fn ($id) => (int) $id)->flip();

        return $regions
            ->map(function (Region $region) use ($allowedSet) {
                $circles = $region->circles
                    ->map(function (Circle $circle) use ($allowedSet) {
                        $divisions = $circle->divisions
                            ->map(function (Division $division) use ($allowedSet) {
                                $zones = $division->zones
                                    ->map(function (Zone $zone) use ($allowedSet) {
                                        $substations = $zone->substations
                                            ->map(function (Substation $sub) use ($allowedSet) {
                                                $feeders = $sub->feeders
                                                    ->filter(fn (Feeder $f) => $allowedSet->has($f->id))
                                                    ->values();
                                                $sub->setRelation('feeders', $feeders);

                                                return $feeders->isNotEmpty() ? $sub : null;
                                            })
                                            ->filter()
                                            ->values();
                                        $zone->setRelation('substations', $substations);

                                        return $substations->isNotEmpty() ? $zone : null;
                                    })
                                    ->filter()
                                    ->values();
                                $division->setRelation('zones', $zones);

                                return $zones->isNotEmpty() ? $division : null;
                            })
                            ->filter()
                            ->values();
                        $circle->setRelation('divisions', $divisions);

                        return $divisions->isNotEmpty() ? $circle : null;
                    })
                    ->filter()
                    ->values();
                $region->setRelation('circles', $circles);

                return $circles->isNotEmpty() ? $region : null;
            })
            ->filter()
            ->values();
    }

    /** Flat zone rows with ancestry for assign UI / autofill. */
    public static function zonesWithAncestry(?Collection $zoneIds = null, ?string $search = null): Collection
    {
        $q = Zone::query()
            ->where('is_active', true)
            ->with(['division.circle.region'])
            ->orderBy('name');

        if ($zoneIds !== null) {
            $q->whereIn('id', $zoneIds);
        }

        if ($search = trim((string) $search)) {
            $q->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhereHas('division', fn ($d) => $d->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('division.circle', fn ($c) => $c->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('division.circle.region', fn ($r) => $r->where('name', 'like', "%{$search}%"));
            });
        }

        return $q->get()->map(fn (Zone $z) => self::zonePayload($z));
    }

    public static function zonePayload(Zone $z): array
    {
        $division = $z->division;
        $circle = $division?->circle;
        $region = $circle?->region;

        return [
            'id' => $z->id,
            'name' => $z->name,
            'division_id' => $z->division_id,
            'division' => $division ? [
                'id' => $division->id,
                'name' => $division->name,
                'circle_id' => $division->circle_id,
                'circle' => $circle ? [
                    'id' => $circle->id,
                    'name' => $circle->name,
                    'region_id' => $circle->region_id,
                    'region' => $region ? [
                        'id' => $region->id,
                        'name' => $region->name,
                    ] : null,
                ] : null,
            ] : null,
            'region_id' => $region?->id,
            'circle_id' => $circle?->id,
            'label' => collect([
                $region?->name,
                $circle?->name,
                $division?->name,
                $z->name,
            ])->filter()->implode(' › '),
        ];
    }
}
