<?php

namespace App\Support;

use App\Models\FeederSurvey;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Shared query / row mapping for Feeder Survey browse (filter + Excel).
 */
class FeederSurveyBrowse
{
    /** @return list<string> */
    public static function excelHeaders(): array
    {
        return [
            'Feeder Code',
            'Feeder Name',
            'Substation',
            'Region',
            'Circle',
            'Division',
            'Zone',
            'Surveyor',
            'Status',
            'DTRs Done',
            'DTRs Expected',
            'Latitude',
            'Longitude',
            'Remarks',
            'Date',
        ];
    }

    public static function baseQuery(User $user): Builder
    {
        $query = FeederSurvey::query()
            ->with([
                'surveyor:id,name,email',
                'region:id,name',
                'circle:id,name',
                'division:id,name',
                'zone:id,name',
                'substation:id,name',
                'feeder:id,code,name',
            ]);

        return SurveyScope::apply($query, $user);
    }

    public static function exportQuery(User $user): Builder
    {
        $query = FeederSurvey::query()
            ->select([
                'feeder_surveys.id',
                'feeder_surveys.surveyor_id',
                'feeder_surveys.region_id',
                'feeder_surveys.circle_id',
                'feeder_surveys.division_id',
                'feeder_surveys.zone_id',
                'feeder_surveys.substation_id',
                'feeder_surveys.feeder_id',
                'feeder_surveys.substation_name',
                'feeder_surveys.feeder_code',
                'feeder_surveys.feeder_name',
                'feeder_surveys.latitude',
                'feeder_surveys.longitude',
                'feeder_surveys.status',
                'feeder_surveys.remarks',
                'feeder_surveys.review_remarks',
                'feeder_surveys.surveyed_at',
                'feeder_surveys.created_at',
            ])
            ->with([
                'surveyor:id,name',
                'region:id,name',
                'circle:id,name',
                'division:id,name',
                'zone:id,name',
            ]);

        return SurveyScope::apply($query, $user);
    }

    public static function applyFilters(Builder $query, Request $request): Builder
    {
        $status = trim((string) $request->input('status', ''));
        if ($status !== '' && $status !== 'all') {
            if ($status === 'approved') {
                $query->whereIn('feeder_surveys.status', ['approved', 'completed']);
            } else {
                $query->where('feeder_surveys.status', $status);
            }
        }

        if ($feederCode = trim((string) $request->input('feeder_code', ''))) {
            $query->where(function (Builder $q) use ($feederCode) {
                $q->where('feeder_surveys.feeder_code', 'like', '%'.$feederCode.'%')
                    ->orWhere('feeder_surveys.feeder_name', 'like', '%'.$feederCode.'%');
            });
        }

        if ($from = $request->input('from')) {
            $query->whereDate('feeder_surveys.surveyed_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('feeder_surveys.surveyed_at', '<=', $to);
        }

        if ($regionId = (int) $request->input('region_id')) {
            $query->where('feeder_surveys.region_id', $regionId);
        }
        if ($circleId = (int) $request->input('circle_id')) {
            $query->where('feeder_surveys.circle_id', $circleId);
        }
        if ($divisionId = (int) $request->input('division_id')) {
            $query->where('feeder_surveys.division_id', $divisionId);
        }
        if ($zoneId = (int) $request->input('zone_id')) {
            $query->where('feeder_surveys.zone_id', $zoneId);
        }
        if ($surveyorId = (int) $request->input('surveyor_id')) {
            $query->where('feeder_surveys.surveyor_id', $surveyorId);
        }

        return $query;
    }

    /** @return list<scalar|null> */
    public static function excelRow(FeederSurvey $row): array
    {
        return [
            $row->feeder_code ?? '',
            $row->feeder_name ?? '',
            $row->substation_name ?? '',
            $row->region?->name ?? '',
            $row->circle?->name ?? '',
            $row->division?->name ?? '',
            $row->zone?->name ?? '',
            $row->surveyor?->name ?? '',
            $row->display_status ?? $row->status ?? '',
            $row->dtrs_completed ?? 0,
            $row->dtrs_expected ?? 0,
            $row->latitude ?? '',
            $row->longitude ?? '',
            $row->remarks ?? $row->review_remarks ?? '',
            self::formatExcelDate($row->surveyed_at ?? $row->created_at),
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
