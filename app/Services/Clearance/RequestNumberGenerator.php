<?php

namespace App\Services\Clearance;

use App\Models\ClearanceRequest;
use Illuminate\Support\Str;

class RequestNumberGenerator
{
    public function next(): string
    {
        $prefix = 'CLR-'.now()->year.'-';
        $last = ClearanceRequest::query()->where('request_no', 'like', $prefix.'%')->orderByDesc('request_no')->value('request_no');
        $sequence = $last === null ? 1 : ((int) substr($last, -6)) + 1;

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    public function verifyCode(): string
    {
        return Str::lower(Str::random(32));
    }
}
