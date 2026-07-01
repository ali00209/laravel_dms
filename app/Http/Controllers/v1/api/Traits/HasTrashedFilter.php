<?php

namespace App\Http\Controllers\v1\api\Traits;

use Illuminate\Http\Request;

trait HasTrashedFilter
{
    protected function extractTrashedFilter(Request $request): ?string
    {
        $value = $request->input('trashed');

        return in_array($value, ['only', 'with'], true) ? $value : null;
    }
}