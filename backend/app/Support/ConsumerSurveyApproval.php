<?php

namespace App\Support;

use App\Models\ConsumerSurvey;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Shared query / row mapping for Consumer Survey Approval (web + API).
 */
class ConsumerSurveyApproval
{
    /** @return list<string> */
    public static function excelHeaders(): array
    {
        // Order aligned to Sample Consumer Survey.xlsx (row 2), including yellow columns.
        return [
            'Sl. No.',
            'Pole Sl. NO',
            'Source Pole',
            'Pole No',
            'Number of consumer on Pole',
            'Pole Longitude',
            'Pole Latitude',
            'New MSN',
            'IVRS',
            'Circle',
            'Division',
            'Zone',
            'Sub station',
            'Substation Code',
            'Feeder',
            'Feeder Code',
            'Dtr',
            'Dtr code',
            'LT Line Type',
            'Consumer name',
            'Longitude',
            'Latitude',
            'Make',
            'Phase',
            'Remark',
            'Date',
            'Survey Time',
            'Data Fetch Type',
            'Executive Name',
            'Executive ID',
            'Approval Status',
            'Approved by',
        ];
    }

    public static function baseQuery(User $user): Builder
    {
        $query = ConsumerSurvey::query()
            ->with([
                'surveyor:id,name,email',
                'reviewer:id,name,email',
                'pole' => self::poleEagerLoad(false),
                'dtr:id,feeder_id,code,name',
                'dtr.feeder:id,substation_id,code,name',
                'dtr.feeder.substation:id,zone_id,name',
                'dtr.feeder.substation.zone:id,division_id,name',
                'dtr.feeder.substation.zone.division:id,circle_id,name',
                'dtr.feeder.substation.zone.division.circle:id,region_id,name',
                'dtr.feeder.substation.zone.division.circle.region:id,name',
                'dtrSurvey' => self::dtrSurveyEagerLoad(true),
            ]);

        return self::applyScope($query, $user);
    }

    /**
     * Lean query for Excel/CSV download — no photo columns, no unused relations.
     * Column selects are Schema-guarded so missing prod columns (e.g. lt_line_type) do not 500.
     */
    public static function exportQuery(User $user): Builder
    {
        $query = ConsumerSurvey::query()
            ->select(self::consumerExportColumns())
            ->with([
                'surveyor:id,name,email',
                'reviewer:id,name,email',
                'pole' => self::poleEagerLoad(true),
                'dtr:id,feeder_id,code,name',
                'dtr.feeder:id,substation_id,code,name',
                'dtr.feeder.substation:id,zone_id,name',
                'dtr.feeder.substation.zone:id,division_id,name',
                'dtr.feeder.substation.zone.division:id,circle_id,name',
                'dtr.feeder.substation.zone.division.circle:id,region_id,name',
                'dtrSurvey' => self::dtrSurveyEagerLoad(false),
            ]);

        return self::applyScope($query, $user);
    }

    /** @return list<string> */
    private static function consumerExportColumns(): array
    {
        $cols = [
            'consumer_surveys.id',
            'consumer_surveys.dtr_survey_id',
            'consumer_surveys.surveyor_id',
            'consumer_surveys.reviewed_by',
            'consumer_surveys.pole_id',
            'consumer_surveys.dtr_id',
            'consumer_surveys.consumer_id',
            'consumer_surveys.msn',
            'consumer_surveys.ivrs',
            'consumer_surveys.consumer_name',
            'consumer_surveys.longitude',
            'consumer_surveys.latitude',
            'consumer_surveys.phase',
            'consumer_surveys.observation',
            'consumer_surveys.status',
            'consumer_surveys.surveyed_at',
            'consumer_surveys.created_at',
        ];

        foreach (['meter_make', 'review_remarks', 'verification_status'] as $col) {
            if (Schema::hasColumn('consumer_surveys', $col)) {
                $cols[] = 'consumer_surveys.'.$col;
            }
        }

        return $cols;
    }

    /**
     * @return \Closure
     */
    private static function poleEagerLoad(bool $withConsumerCount): \Closure
    {
        return function ($q) use ($withConsumerCount) {
            $cols = ['id', 'pole_no', 'latitude', 'longitude'];
            foreach (['source_type', 'previous_pole_id', 'houses_connected'] as $col) {
                if (Schema::hasColumn('poles', $col)) {
                    $cols[] = $col;
                }
            }
            $q->select($cols);
            if ($withConsumerCount) {
                $q->withCount('consumers');
            }
            if (Schema::hasColumn('poles', 'previous_pole_id')) {
                $q->with('previousPole:id,pole_no');
            }
        };
    }

    /**
     * Eager-load dtr_surveys columns only when they exist (prod may lag migrations).
     *
     * @return \Closure
     */
    private static function dtrSurveyEagerLoad(bool $withScopeIds): \Closure
    {
        return function ($q) use ($withScopeIds) {
            $cols = ['id'];
            if ($withScopeIds) {
                $cols = array_merge($cols, [
                    'surveyor_id', 'supervisor_id', 'region_id', 'circle_id',
                    'division_id', 'zone_id', 'substation_id', 'feeder_id', 'dtr_id',
                ]);
            }
            foreach (['lt_line_type', 'entry_source'] as $col) {
                if (Schema::hasColumn('dtr_surveys', $col)) {
                    $cols[] = $col;
                }
            }
            $q->select($cols);
        };
    }

    public static function applyScope(Builder $query, User $user): Builder
    {
        if ($user->isSuperAdmin() || $user->isAdmin()) {
            return $query;
        }

        if ($user->isFieldExecutive()) {
            return $query->where('surveyor_id', $user->id);
        }

        // Manager / PM: visibility via parent DTR survey scope.
        return $query->whereHas('dtrSurvey', function (Builder $q) use ($user) {
            SurveyScope::apply($q, $user);
        });
    }

    public static function applyFilters(Builder $query, Request $request): Builder
    {
        $status = trim((string) $request->input('status', ''));
        if ($status !== '' && $status !== 'all') {
            $query->where('consumer_surveys.status', $status);
        }

        if ($ivrs = trim((string) $request->input('ivrs', ''))) {
            $query->where('consumer_surveys.ivrs', 'like', '%'.$ivrs.'%');
        }

        if ($dtrCode = trim((string) $request->input('dtr_code', ''))) {
            $query->whereHas('dtr', fn (Builder $q) => $q->where('code', 'like', '%'.$dtrCode.'%'));
        }

        if ($phase = trim((string) $request->input('phase', ''))) {
            if ($phase !== 'all') {
                $query->where('consumer_surveys.phase', 'like', '%'.$phase.'%');
            }
        }

        if ($from = $request->input('from')) {
            $query->whereDate('consumer_surveys.surveyed_at', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('consumer_surveys.surveyed_at', '<=', $to);
        }

        if ($regionId = (int) $request->input('region_id')) {
            $query->whereHas('dtrSurvey', fn (Builder $q) => $q->where('region_id', $regionId));
        }
        if ($circleId = (int) $request->input('circle_id')) {
            $query->whereHas('dtrSurvey', fn (Builder $q) => $q->where('circle_id', $circleId));
        }
        if ($divisionId = (int) $request->input('division_id')) {
            $query->whereHas('dtrSurvey', fn (Builder $q) => $q->where('division_id', $divisionId));
        }
        if ($zoneId = (int) $request->input('zone_id')) {
            $query->whereHas('dtrSurvey', fn (Builder $q) => $q->where('zone_id', $zoneId));
        }
        if ($surveyorId = (int) $request->input('surveyor_id')) {
            $query->where('consumer_surveys.surveyor_id', $surveyorId);
        }

        return $query;
    }

    /**
     * Stable export order: DTR → Pole → surveyed_at → id.
     */
    public static function applyExportOrder(Builder $query): Builder
    {
        return $query
            ->orderBy('consumer_surveys.dtr_id')
            ->orderByRaw('CASE WHEN consumer_surveys.pole_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('consumer_surveys.pole_id')
            ->orderBy('consumer_surveys.surveyed_at')
            ->orderBy('consumer_surveys.id');
    }

    /**
     * Pole grouping key for Pole Sl. NO / consumer-on-pole counts.
     * Prefer pole_id; if missing, same dtr_id + pole number.
     */
    public static function poleGroupKey(ConsumerSurvey $row): string
    {
        $poleId = (int) ($row->pole_id ?? 0);
        if ($poleId > 0) {
            return 'id:'.$poleId;
        }

        $dtrId = (int) ($row->dtr_id ?? 0);
        $poleNo = trim((string) ($row->pole?->pole_no ?? ''));

        return 'dtr:'.$dtrId.'|no:'.$poleNo;
    }

    /**
     * Build Excel rows with Sl. No. / Pole Sl. NO sequencing.
     *
     * - Sl. No. = 1..N within each dtr_id (resets when DTR changes)
     * - Pole Sl. NO = 1..M among consumers on the same pole in this result set
     * - Number of consumer on Pole = M (count on that pole in this result set)
     *
     * @param  Collection<int, ConsumerSurvey>|iterable<ConsumerSurvey>  $rows
     * @return list<list<scalar|null>>
     */
    public static function excelRows(iterable $rows): array
    {
        $sorted = collect($rows)->sort(function (ConsumerSurvey $a, ConsumerSurvey $b): int {
            $dtrCmp = ((int) ($a->dtr_id ?? 0)) <=> ((int) ($b->dtr_id ?? 0));
            if ($dtrCmp !== 0) {
                return $dtrCmp;
            }

            $poleIdA = (int) ($a->pole_id ?? 0);
            $poleIdB = (int) ($b->pole_id ?? 0);
            if ($poleIdA !== $poleIdB) {
                // Null/0 poles last within the DTR, then by pole_id.
                if ($poleIdA === 0 || $poleIdB === 0) {
                    return $poleIdA === 0 ? 1 : -1;
                }

                return $poleIdA <=> $poleIdB;
            }

            // Same pole_id (incl. both null): fall back to pole_no then time.
            $poleNoCmp = strcmp(
                trim((string) ($a->pole?->pole_no ?? '')),
                trim((string) ($b->pole?->pole_no ?? ''))
            );
            if ($poleNoCmp !== 0) {
                return $poleNoCmp;
            }

            $timeA = optional($a->surveyed_at ?? $a->created_at)->getTimestamp() ?? 0;
            $timeB = optional($b->surveyed_at ?? $b->created_at)->getTimestamp() ?? 0;
            if ($timeA !== $timeB) {
                return $timeA <=> $timeB;
            }

            return ((int) $a->id) <=> ((int) $b->id);
        })->values();

        $poleCounts = [];
        foreach ($sorted as $row) {
            $key = self::poleGroupKey($row);
            $poleCounts[$key] = ($poleCounts[$key] ?? 0) + 1;
        }

        $slByDtr = [];
        $poleSlByKey = [];
        $out = [];

        foreach ($sorted as $row) {
            $dtrId = (int) ($row->dtr_id ?? 0);
            $poleKey = self::poleGroupKey($row);

            $slByDtr[$dtrId] = ($slByDtr[$dtrId] ?? 0) + 1;
            $poleSlByKey[$poleKey] = ($poleSlByKey[$poleKey] ?? 0) + 1;

            $out[] = self::excelRow(
                $row,
                $slByDtr[$dtrId],
                $poleSlByKey[$poleKey],
                $poleCounts[$poleKey]
            );
        }

        return $out;
    }

    /** @return list<scalar|null> */
    public static function excelRow(
        ConsumerSurvey $row,
        int|string $slNo = '',
        int|string $poleSlNo = '',
        int|string|null $consumersOnPoleOverride = null
    ): array {
        $pole = $row->pole;
        $dtr = $row->dtr;
        $feeder = $dtr?->feeder;
        $sub = $feeder?->substation;
        $zone = $sub?->zone;
        $division = $zone?->division;
        $circle = $division?->circle;
        $dtrSurvey = $row->relationLoaded('dtrSurvey') ? $row->dtrSurvey : null;

        $sourcePole = '';
        if ($pole) {
            $sourcePole = $pole->source_type === 'previous_pole'
                ? (string) ($pole->previousPole?->pole_no ?? '')
                : 'DTR';
        }

        if ($consumersOnPoleOverride !== null && $consumersOnPoleOverride !== '') {
            $consumersOnPole = (int) $consumersOnPoleOverride;
        } else {
            $consumersOnPole = '';
            if ($pole) {
                if (isset($pole->consumers_count)) {
                    $consumersOnPole = (int) $pole->consumers_count;
                } elseif ($pole->houses_connected !== null) {
                    $consumersOnPole = (int) $pole->houses_connected;
                }
            }
        }

        $surveyedAt = $row->surveyed_at ?? $row->created_at;
        $make = $row->meter_make ?: MeterMakeFromMsn::fromMsn($row->msn);

        return [
            $slNo,
            $poleSlNo,
            $sourcePole,
            $pole?->pole_no ?? '',
            $consumersOnPole,
            $pole?->longitude ?? '',
            $pole?->latitude ?? '',
            $row->msn ?? '',
            $row->ivrs ?? '',
            $circle?->name ?? '',
            $division?->name ?? '',
            $zone?->name ?? '',
            $sub?->name ?? '',
            '', // Substation has no code column in hierarchy yet
            $feeder?->name ?? '',
            $feeder?->code ?? '',
            $dtr?->name ?? '',
            $dtr?->code ?? '',
            \App\Support\LtLineType::display($dtrSurvey?->lt_line_type),
            $row->consumer_name ?? '',
            $row->longitude ?? '',
            $row->latitude ?? '',
            $make ?? '',
            $row->phase ?? '',
            $row->observation ?? $row->review_remarks ?? '',
            self::formatExcelDate($surveyedAt),
            self::formatExcelTime($surveyedAt),
            self::dataFetchType($row),
            $row->surveyor?->name ?? '',
            $row->surveyor?->email ?? '',
            self::approvalStatusLabel($row->status),
            $row->reviewer?->name ?? $row->reviewer?->email ?? '',
        ];
    }

    private static function dataFetchType(ConsumerSurvey $row): string
    {
        if ($row->verification_status === 'New Consumer' || empty($row->consumer_id)) {
            return 'Manual';
        }

        return 'Auto';
    }

    private static function approvalStatusLabel(?string $status): string
    {
        return match ($status) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'pending_approval' => 'Pending',
            'not_accessible' => 'Not Accessible',
            'saved' => 'Saved',
            default => $status ? ucfirst(str_replace('_', ' ', $status)) : '',
        };
    }

    private static function formatExcelDate(mixed $value): string
    {
        // Date column only — time belongs in "Survey Time".
        if ($value instanceof \DateTimeInterface) {
            return $value->format('d M Y');
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return \Carbon\Carbon::parse($value)->format('d M Y');
            } catch (\Throwable) {
                return $value;
            }
        }

        return '';
    }

    private static function formatExcelTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i:s');
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return \Carbon\Carbon::parse($value)->format('H:i:s');
            } catch (\Throwable) {
                return '';
            }
        }

        return '';
    }

    /** @return array<string, mixed> */
    public static function apiRow(ConsumerSurvey $row): array
    {
        $dtr = $row->dtr;
        $feeder = $dtr?->feeder;
        $sub = $feeder?->substation;
        $zone = $sub?->zone;
        $division = $zone?->division;
        $circle = $division?->circle;

        return [
            'id' => $row->id,
            'status' => $row->status,
            'verification_status' => $row->verification_status,
            'survey_flag' => $row->survey_flag,
            'consumer_name' => $row->consumer_name,
            'phone' => $row->phone,
            'ivrs' => $row->ivrs,
            'msn' => $row->msn,
            'meter_make' => $row->meter_make,
            'phase' => $row->phase,
            'address' => $row->address,
            'latitude' => $row->latitude,
            'longitude' => $row->longitude,
            'observation' => $row->observation,
            'review_remarks' => $row->review_remarks,
            'reviewed_at' => optional($row->reviewed_at)?->toDateTimeString(),
            'surveyed_at' => optional($row->surveyed_at)?->toDateTimeString(),
            'meter_photo' => $row->meter_photo,
            'meter_photo_url' => SurveyPhotoStorage::url($row->meter_photo),
            'premise_photo_url' => SurveyPhotoStorage::url($row->premise_photo),
            'surveyor' => $row->surveyor ? [
                'id' => $row->surveyor->id,
                'name' => $row->surveyor->name,
            ] : null,
            'reviewer' => $row->reviewer ? [
                'id' => $row->reviewer->id,
                'name' => $row->reviewer->name,
            ] : null,
            'pole_no' => $row->pole?->pole_no,
            'source_pole' => $row->pole?->source_type === 'previous_pole'
                ? ($row->pole->previousPole?->pole_no ?? '')
                : 'DTR',
            'dtr_name' => $dtr?->name,
            'dtr_code' => $dtr?->code,
            'feeder_name' => $feeder?->name,
            'feeder_code' => $feeder?->code,
            'substation_name' => $sub?->name,
            'zone_name' => $zone?->name,
            'division_name' => $division?->name,
            'circle_name' => $circle?->name,
            'region_name' => $circle?->region?->name,
            'lt_line_type' => \App\Support\LtLineType::display($row->dtrSurvey?->lt_line_type),
        ];
    }
}
