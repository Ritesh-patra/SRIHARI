<?php

namespace App\Support;

/**
 * Human-readable status labels for manager mobile / reports.
 */
class SurveyStatusLabel
{
    public static function label(?string $status, string $type = 'dtr'): string
    {
        $status = trim((string) $status);
        if ($status === '') {
            return '—';
        }

        if ($type === 'feeder') {
            return match ($status) {
                'draft' => 'Pending DTR Survey',
                'sld_pending' => 'SLD Verification Pending',
                'pending_approval' => 'Pending Approval',
                'approved', 'completed' => 'Approved',
                'rejected' => 'Rejected',
                default => self::fallback($status),
            };
        }

        if ($type === 'consumer') {
            return match ($status) {
                'pending_approval' => 'Pending Approval',
                'approved' => 'Approved',
                'rejected' => 'Rejected',
                'saved' => 'Saved',
                'not_accessible' => 'Not Accessible',
                default => self::fallback($status),
            };
        }

        return match ($status) {
            'draft' => 'Draft',
            'pending_approval' => 'Pending Approval',
            'approved' => 'DTR Already Surveyed',
            'rejected' => 'Rejected',
            'completed' => 'Completed',
            default => self::fallback($status),
        };
    }

    private static function fallback(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }
}
