<?php

namespace App\Services;

use App\Models\DeploymentSetting;
use App\Models\TrackingSequence;
use RuntimeException;

class TrackingNumberGenerator
{
    public function next(): string
    {
        $settings = DeploymentSetting::current();

        if (! $settings) {
            throw new RuntimeException('Deployment settings must be configured before registering documents.');
        }

        $prefix = mb_strtoupper(trim($settings->tracking_prefix));
        $officeCode = mb_strtoupper(trim($settings->managing_office_code));
        $year = (int) now()->format('Y');

        TrackingSequence::query()->insertOrIgnore([
            'prefix' => $prefix,
            'office_code' => $officeCode,
            'year' => $year,
            'last_number' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var TrackingSequence $sequence */
        $sequence = TrackingSequence::query()
            ->where('prefix', $prefix)
            ->where('office_code', $officeCode)
            ->where('year', $year)
            ->lockForUpdate()
            ->firstOrFail();

        $sequence->increment('last_number');
        $sequence->refresh();

        return sprintf(
            '%s-%s-%04d-%06d',
            $prefix,
            $officeCode,
            $year,
            $sequence->last_number,
        );
    }
}
