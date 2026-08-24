<?php

namespace App\Support;

use App\Models\SubstationSurvey;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Shared query / row mapping for Substation Survey browse (filter + Excel).
 */
class SubstationSurveyBrowse
{
    /** @return list<string> */
    public static function excelHeaders(): array
    {
        return [
            'Substation Code',
            'Substation',
            'Region',
            'Circle',
            'Division',
            'Zone',
            'Surveyor',
            'Status',
            'Substation Type',
            'Capacity (MVA)',
            'Transformers',
            'Incoming Voltage',
            'Outgoing Voltage',
            'Declared Feeders',
            'Meter No',
            'Meter Make',
            'Metering Type',
            'CT Ratio',
            'PT Ratio',
            'MF',
            'Meter Condition',
            'Meter Working',
            'Latitude',
            'Longitude',
            'Observation',
            'Remarks',
            'Date',
        ];
    }

    public static function baseQuery(User $user): Builder
    {
        $query = SubstationSurvey::query()
            ->with([
                'surveyor:id,name,email',
                'region:id,name',
                'circle:id,name',
                'division:id,name',
                'zone:id,name',
                'substation:id,name',
            ]);

        return SurveyScope::apply($query, $user);
    }

    public static function exportQuery(User $user): Builder
    {
        $query = SubstationSurvey::query()
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
            $query->where('substation_surveys.status', $status);
        }

        if ($search = trim((string) $request->input('substation_code', ''))) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('substation_surveys.substation_code', 'like', '%'.$search.'%')
                    ->orWhere('substation_surveys.substation_name', 'like', '%'.$search.'%');
            });
        }

        if ($from = $request->input('from')) {
            $query->whereDate('substation_surveys.surveyed_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('substation_surveys.surveyed_at', '<=', $to);
        }

        if ($regionId = (int) $request->input('region_id')) {
            $query->where('substation_surveys.region_id', $regionId);
        }
        if ($circleId = (int) $request->input('circle_id')) {
            $query->where('substation_surveys.circle_id', $circleId);
        }
        if ($divisionId = (int) $request->input('division_id')) {
            $query->where('substation_surveys.division_id', $divisionId);
        }
        if ($zoneId = (int) $request->input('zone_id')) {
            $query->where('substation_surveys.zone_id', $zoneId);
        }
        if ($substationId = (int) $request->input('substation_id')) {
            $query->where('substation_surveys.substation_id', $substationId);
        }
        if ($surveyorId = (int) $request->input('surveyor_id')) {
            $query->where('substation_surveys.surveyor_id', $surveyorId);
        }

        return $query;
    }

    /** @return list<scalar|null> */
    public static function excelRow(SubstationSurvey $row): array
    {
        return [
            $row->substation_code ?? '',
            $row->substation_name ?? $row->substation?->name ?? '',
            $row->region?->name ?? '',
            $row->circle?->name ?? '',
            $row->division?->name ?? '',
            $row->zone?->name ?? '',
            $row->surveyor?->name ?? '',
            $row->display_status ?? $row->status ?? '',
            $row->substation_type ?? '',
            $row->capacity_mva ?? '',
            $row->transformer_count ?? '',
            $row->incoming_voltage ?? '',
            $row->outgoing_voltage ?? '',
            $row->feeder_count_declared ?? '',
            $row->meter_number ?? '',
            $row->meter_make ?? '',
            $row->metering_type ?? '',
            $row->ct_ratio ?? '',
            $row->pt_ratio ?? '',
            $row->mf ?? '',
            $row->meter_condition ?? '',
            $row->meter_working === null ? '' : ($row->meter_working ? 'Yes' : 'No'),
            $row->latitude ?? '',
            $row->longitude ?? '',
            $row->observation ?? '',
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
