<?php

namespace App\Http\Requests\Api\Ta;

use Illuminate\Validation\Rule;

class IndexBlocksRequest extends IndexTaRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'show_type' => ['nullable', 'string', Rule::in(['list', 'map', 'plans'])],
        ]);
    }
}
