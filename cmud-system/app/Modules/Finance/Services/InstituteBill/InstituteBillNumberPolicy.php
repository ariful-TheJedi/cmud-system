<?php

namespace App\Modules\Finance\Services\InstituteBill;

use Illuminate\Support\Str;

/** Owns the replaceable policy for assigning public identifiers to institute bills. */
class InstituteBillNumberPolicy
{
    /** Generate the current year-prefixed ULID invoice number. */
    public function generate(): string
    {
        return 'IB-'.now()->format('Y').'-'.Str::upper((string) Str::ulid());
    }
}
