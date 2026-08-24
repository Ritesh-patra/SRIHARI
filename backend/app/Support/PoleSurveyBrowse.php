<?php

namespace App\Support;

use App\Models\Pole;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Shared query / row mapping for Pole survey browse (field poles + filters + Excel).
 */
class PoleSurveyBrowse
{
    /** @return list<string> */
    public static function excelHeaders(): array
    {
        return [
            'Pole No',
            'Source',
            'Houses Connected',
            'DTR Code',
            'DTR Name',
            'Feeder',
            'Substation',
            'Zone',
            'Division',
            'Circle',
            'Region',
            'Survey Count',
            'Latitude',
            'Longitude',
            'Created',
        ];
    }

    public static function baseQuery(User $user): Builder
    {
        $query = Pole::query()
            ->with([
                'dtr:id,feeder_id,code,name',
                'dtr.feeder:id,substation_id,code,name',
                'dtr.feeder.substation:id,zone_id,name',
                'dtr.feeder.substation.zone:id,division_id,name',
                'dtr.feeder.substation.zone.division:id,circle_id,name',
                'dtr.feeder.substation.zone.division.circle:id,region_id,name',
                'dtr.feeder.substation.zone.division.circle.region:id,name',
            ])
            ->withCount('consumerSurveys');

        return self::applyScope($query, $user);
    }

    public static function exportQuery(User $user): Builder
    {
        return self::baseQuery($user);
    }

    public static function applyScope(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return $query;
        }

        if ($user->isFieldExecutive()) {
            return $query->whereHas('consumerSurveys', fn (Builder $q) => $q->where('surveyor_id', $user->id));
        }

        // Manager / PM: poles under DTRs that have surveys in their scope,
        // or poles linked via hierarchy under scoped DTR surveys.
        return $query->where(function (Builder $outer) use ($user) {
            $outer->whereHas('consumerSurveys.dtrSurvey', function (Builder $q) use ($user) {
                SurveyScope::apply($q, $user);
            })->orWhereHas('dtr.feeder.substation.zone', function (Builder $q) use ($user) {
                if ($user->isManager()) {
                    $divisionIds = $user->scopeIds('division');
                    if ($divisionIds->isNotEmpty()) {
                        $q->whereIn('division_id', $divisionIds);
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                } elseif ($user->isProjectManager()) {
                    $circleIds = $user->scopeIds('circle');
                    if ($circleIds->isNotEmpty()) {
                        $q->whereHas('division', fn (Builder $d) => $d->whereIn('circle_id', $circleIds));
                    } else {
                        $q->whereRaw('1 = 0');
                    }
                } else {
                    $q->whereRaw('1 = 0');
                }
            });
        });
    }

    public static function applyFilters(Builder $query, Request $request): Builder
    {
        $status = trim((string) $request->input('status', ''));
        if ($status === 'surveyed') {
            $query->has('consumerSurveys');
        } elseif ($status === 'not_surveyed') {
            $query->doesntHave('consumerSurveys');
        }

        if ($poleCode = trim((string) $request->input('pole_code', ''))) {
            $query->where('poles.pole_no', 'like', '%'.$poleCode.'%');
        }

        if ($dtrCode = trim((string) $request->input('dtr_code', ''))) {
            $query->whereHas('dtr', function (Builder $q) use ($dtrCode) {
                $q->where(function (Builder $inner) use ($dtrCode) {
                    $inner->where('code', 'like', '%'.$dtrCode.'%')
                        ->orWhere('name', 'like', '%'.$dtrCode.'%');
                });
            });
        }

        if ($from = $request->input('from')) {
            $query->whereDate('poles.created_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('poles.created_at', '<=', $to);
        }

        if ($regionId = (int) $request->input('region_id')) {
            $query->whereHas('dtr.feeder.substation.zone.division.circle', fn (Builder $q) => $q->where('region_id', $regionId));
        }
        if ($circleId = (int) $request->input('circle_id')) {
            $query->whereHas('dtr.feeder.substation.zone.division', fn (Builder $q) => $q->where('circle_id', $circleId));
        }
        if ($divisionId = (int) $request->input('division_id')) {
            $query->whereHas('dtr.feeder.substation.zone', fn (Builder $q) => $q->where('division_id', $divisionId));
        }
        if ($zoneId = (int) $request->input('zone_id')) {
            $query->whereHas('dtr.feeder.substation', fn (Builder $q) => $q->where('zone_id', $zoneId));
        }
        if ($surveyorId = (int) $request->input('surveyor_id')) {
            $query->whereHas('consumerSurveys', fn (Builder $q) => $q->where('surveyor_id', $surveyorId));
        }

        return $query;
    }

    /** @return list<scalar|null> */
    public static function excelRow(Pole $row): array
    {
        $dtr = $row->dtr;
        $feeder = $dtr?->feeder;
        $sub = $feeder?->substation;
        $zone = $sub?->zone;
        $division = $zone?->division;
        $circle = $division?->circle;

        return [
            $row->pole_no ?? '',
            $row->source_type === 'previous_pole' ? 'Previous pole' : 'DTR',
            $row->houses_connected ?? 0,
            $dtr?->code ?? '',
            $dtr?->name ?? '',
            $feeder?->name ?? '',
            $sub?->name ?? '',
            $zone?->name ?? '',
            $division?->name ?? '',
            $circle?->name ?? '',
            $circle?->region?->name ?? '',
            $row->consumer_surveys_count ?? 0,
            $row->latitude ?? '',
            $row->longitude ?? '',
            self::formatExcelDate($row->created_at),
        ];
    }

    private static function formatExcelDate(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d M Y, H:i');
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return \Carbon\Carbon::parse($value)->format('d M Y, H:i');
            } catch (\Throwable) {
                return $value;
            }
        }

        return '';
    }
}
