<?php

namespace App\Support;

use App\Models\ReadingUpload;

/**
 * Maps the wildly inconsistent header spellings that come out of billing / MDM
 * exports onto the canonical reading columns. Matching is case-insensitive and
 * ignores spaces, underscores, dots and brackets, so "Feeder Code", "FEEDER_CODE"
 * and "Fdr.Code" all land on feeder_code. Anything unmatched is kept in raw_json.
 */
class ReadingHeaderMap
{
    /** @var array<string, list<string>> */
    private const ALIASES = [
        'feeder_code' => ['feedercode', 'fdrcode', 'feederno', 'fdrno', 'feedernumber', 'feeder', 'fdr', 'feedercd', 'feederid11kv', 'feedercodeno'],
        'feeder_name' => ['feedername', 'fdrname', 'nameoffeeder', 'feederdescription', 'feederdesc'],
        'dtr_code' => ['dtrcode', 'dtrno', 'dtrnumber', 'dtrcd', 'dtcode', 'dtno', 'transformercode', 'dtrid', 'dtrserialno'],
        'dtr_name' => ['dtrname', 'dtname', 'transformername', 'nameofdtr', 'dtrdescription'],
        'ivrs' => ['ivrs', 'ivrsno', 'ivrsnumber', 'ivrsnum', 'ivrscode', 'ivrsid'],
        'msn' => ['msn', 'msnno', 'meterserial', 'meterserialno', 'meterserialnumber', 'meterno', 'meternumber', 'meterserialnum'],
        'account_no' => ['accountno', 'account', 'accountnumber', 'acctno', 'consumerno', 'consumernumber', 'consumerid', 'scno', 'serviceno', 'servicenumber', 'kno', 'knumber', 'cano'],
        'consumer_name' => ['consumername', 'customername', 'nameofconsumer', 'name'],
        'reading_date' => ['readingdate', 'readdate', 'date', 'billdate', 'billingdate', 'meterreadingdate', 'consumptiondate', 'mrdate'],
        'period_label' => ['period', 'periodlabel', 'month', 'billmonth', 'billingmonth', 'monthyear', 'mmyyyy'],
        'kwh_import' => ['kwh', 'kwhimport', 'kwhimp', 'importkwh', 'units', 'unit', 'unitsconsumed', 'consumption', 'consumptionkwh', 'consumptioninkwh', 'energykwh', 'totalkwh', 'kwhconsumption', 'kwhconsumed'],
        'kwh_export' => ['kwhexport', 'kwhexp', 'exportkwh', 'exportunits', 'unitsexported'],
        'kvah' => ['kvah', 'kvahimport', 'kvahimp', 'totalkvah', 'kvahconsumption', 'kvahconsumed'],
        'md_kw' => ['mdkw', 'md', 'maxdemand', 'maximumdemand', 'maxdemandkw', 'demandkw', 'mdinkw'],
    ];

    /** @var array<string, list<string>> */
    private const FIELDS_BY_TYPE = [
        ReadingUpload::TYPE_FEEDER => ['feeder_code', 'feeder_name', 'reading_date', 'period_label', 'kwh_import', 'kwh_export', 'kvah', 'md_kw'],
        ReadingUpload::TYPE_DTR => ['dtr_code', 'dtr_name', 'feeder_code', 'reading_date', 'period_label', 'kwh_import', 'kwh_export', 'kvah', 'md_kw'],
        ReadingUpload::TYPE_CONSUMER => ['ivrs', 'msn', 'account_no', 'consumer_name', 'dtr_code', 'feeder_code', 'reading_date', 'period_label', 'kwh_import', 'kwh_export', 'kvah', 'md_kw'],
    ];

    /** Columns that must resolve to a value for the row to be importable. */
    private const REQUIRED_BY_TYPE = [
        ReadingUpload::TYPE_FEEDER => ['feeder_code'],
        ReadingUpload::TYPE_DTR => ['dtr_code'],
        ReadingUpload::TYPE_CONSUMER => ['ivrs', 'msn', 'account_no'],
    ];

    /** @return list<string> */
    public static function fieldsFor(string $type): array
    {
        return self::FIELDS_BY_TYPE[$type] ?? [];
    }

    /** @return list<string> At least one of these must be present on a row. */
    public static function requiredFor(string $type): array
    {
        return self::REQUIRED_BY_TYPE[$type] ?? [];
    }

    /**
     * @param  list<string>  $headers
     * @return array{fields: array<string, int>, extras: array<int, string>}
     */
    public static function map(string $type, array $headers): array
    {
        $wanted = self::fieldsFor($type);
        $fields = [];
        $extras = [];

        foreach ($headers as $index => $header) {
            $label = trim((string) $header);
            $key = self::normalize($label);

            if ($key === '') {
                continue;
            }

            $field = self::matchField($key, $wanted);
            if ($field !== null && ! isset($fields[$field])) {
                $fields[$field] = (int) $index;

                continue;
            }

            $extras[(int) $index] = $label !== '' ? $label : 'column_'.($index + 1);
        }

        return ['fields' => $fields, 'extras' => $extras];
    }

    /** @param  list<string>  $wanted */
    private static function matchField(string $normalizedHeader, array $wanted): ?string
    {
        foreach ($wanted as $field) {
            if ($normalizedHeader === self::normalize($field)) {
                return $field;
            }
            foreach (self::ALIASES[$field] ?? [] as $alias) {
                if ($normalizedHeader === $alias) {
                    return $field;
                }
            }
        }

        return null;
    }

    public static function normalize(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/[^a-z0-9]/', '', $value) ?? '';

        return $value;
    }

    /** Strip thousands separators / units and return a decimal string or null. */
    public static function toDecimal(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(str_replace([',', ' '], '', $value));
        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return $value;
    }

    /** Accepts the common Indian utility date spellings plus ISO / Excel output. */
    public static function toDate(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d', 'Y-m-d H:i:s', 'd-m-Y', 'd/m/Y', 'd.m.Y', 'd-M-Y', 'd-M-y', 'm/d/Y', 'Y/m/d', 'Ymd'];
        foreach ($formats as $format) {
            $parsed = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($parsed instanceof \DateTimeImmutable && empty($errors['warning_count']) && empty($errors['error_count'])) {
                return $parsed->format('Y-m-d');
            }
        }

        return null;
    }
}
