<?php

namespace App\Support;

use App\Models\DtrSurvey;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Shared query / row mapping for DTR Survey browse (filter + Excel).
 */
class DtrSurveyBrowse
{
    /** @return list<string> */
    public static function excelHeaders(): array
    {
        return [
            'DTR Code',
            'DTR Name',
            'Feeder Code',
            'Feeder Name',
            'Region',
            'Circle',
            'Division',
            'Zone',
            'Substation',
            'Surveyor',
            'Status',
            'Smart Meter',
            'New MSN',
            'Capacity kVA',
            'Condition',
            'LT Line Type',
            'Latitude',
            'Longitude',
            'Observation',
            'Date',
        ];
    }

    public static function baseQuery(User $user): Builder
    {
        $query = DtrSurvey::query()
            ->with([
                'surveyor:id,name,email',
                'region:id,name',
                'circle:id,name',
                'division:id,name',
                'zone:id,name',
                'substation:id,name',
                'feeder:id,code,name',
                'dtr:id,code,name',
            ]);

        return SurveyScope::apply($query, $user);
    }

    public static function exportQuery(User $user): Builder
    {
        $cols = [
            'dtr_surveys.id',
            'dtr_surveys.surveyor_id',
            'dtr_surveys.region_id',
            'dtr_surveys.circle_id',
            'dtr_surveys.division_id',
            'dtr_surveys.zone_id',
            'dtr_surveys.substation_id',
            'dtr_surveys.feeder_id',
            'dtr_surveys.dtr_id',
            'dtr_surveys.feeder_code',
            'dtr_surveys.feeder_name',
            'dtr_surveys.dtr_code',
            'dtr_surveys.dtr_name',
            'dtr_surveys.latitude',
            'dtr_surveys.longitude',
            'dtr_surveys.dtr_capacity_kva',
            'dtr_surveys.dtr_condition',
            'dtr_surveys.smart_meter_status',
            'dtr_surveys.new_msn',
            'dtr_surveys.observation',
            'dtr_surveys.status',
            'dtr_surveys.consumer_survey_completed_at',
            'dtr_surveys.surveyed_at',
            'dtr_surveys.created_at',
        ];
        // Prod may not have run Aug-13 migration yet — avoid SELECT crash on Download.
        if (Schema::hasColumn('dtr_surveys', 'lt_line_type')) {
            $cols[] = 'dtr_surveys.lt_line_type';
        }

        $query = DtrSurvey::query()
            ->select($cols)
            ->with([
                'surveyor:id,name',
                'region:id,name',
                'circle:id,name',
                'division:id,name',
                'zone:id,name',
                'substation:id,name',
            ]);

        return SurveyScope::apply($query, $user);
    }

    public static function applyFilters(Builder $query, Request $request): Builder
    {
        $status = trim((string) $request->input('status', ''));
        if ($status !== '' && $status !== 'all') {
            if ($status === 'consumer_completed') {
                $query->whereNotNull('dtr_surveys.consumer_survey_completed_at');
            } else {
                $query->where('dtr_surveys.status', $status)
                    ->when($status === 'approved', fn (Builder $q) => $q->whereNull('dtr_surveys.consumer_survey_completed_at'));
            }
        }

        if ($dtrCode = trim((string) $request->input('dtr_code', ''))) {
            $query->where(function (Builder $q) use ($dtrCode) {
                $q->where('dtr_surveys.dtr_code', 'like', '%'.$dtrCode.'%')
                    ->orWhere('dtr_surveys.dtr_name', 'like', '%'.$dtrCode.'%');
            });
        }

        if ($feederCode = trim((string) $request->input('feeder_code', ''))) {
            $query->where(function (Builder $q) use ($feederCode) {
                $q->where('dtr_surveys.feeder_code', 'like', '%'.$feederCode.'%')
                    ->orWhere('dtr_surveys.feeder_name', 'like', '%'.$feederCode.'%');
            });
        }

        if ($from = $request->input('from')) {
            $query->whereDate('dtr_surveys.surveyed_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('dtr_surveys.surveyed_at', '<=', $to);
        }

        if ($regionId = (int) $request->input('region_id')) {
            $query->where('dtr_surveys.region_id', $regionId);
        }
        if ($circleId = (int) $request->input('circle_id')) {
            $query->where('dtr_surveys.circle_id', $circleId);
        }
        if ($divisionId = (int) $request->input('division_id')) {
            $query->where('dtr_surveys.division_id', $divisionId);
        }
        if ($zoneId = (int) $request->input('zone_id')) {
            $query->where('dtr_surveys.zone_id', $zoneId);
        }
        if ($surveyorId = (int) $request->input('surveyor_id')) {
            $query->where('dtr_surveys.surveyor_id', $surveyorId);
        }

        return $query;
    }

    /** @return list<scalar|null> */
    public static function excelRow(DtrSurvey $row): array
    {
        return [
            $row->dtr_code ?? '',
            $row->dtr_name ?? '',
            $row->feeder_code ?? '',
            $row->feeder_name ?? '',
            $row->region?->name ?? '',
            $row->circle?->name ?? '',
            $row->division?->name ?? '',
            $row->zone?->name ?? '',
            $row->substation?->name ?? '',
            $row->surveyor?->name ?? '',
            $row->displayStatusLabel(),
            $row->smart_meter_status ?? '',
            $row->new_msn ?? '',
            $row->dtr_capacity_kva ?? '',
            $row->dtr_condition ?? '',
            \App\Support\LtLineType::display($row->lt_line_type),
            $row->latitude ?? '',
            $row->longitude ?? '',
            $row->observation ?? '',
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
