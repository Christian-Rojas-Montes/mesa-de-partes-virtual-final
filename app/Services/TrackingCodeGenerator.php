<?php

namespace App\Services;

use App\Models\TrackingSequence;
use Illuminate\Support\Facades\DB;

class TrackingCodeGenerator
{
    public function generate(): string
    {
        return DB::transaction(function () {
            $year = (int) now()->format('Y');

            TrackingSequence::query()->insertOrIgnore([
                'year' => $year,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = TrackingSequence::query()->whereKey($year)->lockForUpdate()->firstOrFail();
            $sequence->increment('last_number');

            return sprintf('MPV-%d-%06d', $year, $sequence->last_number);
        });
    }
}
