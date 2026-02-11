<?php

namespace App\Http\Requests\Api\Ta;

use Illuminate\Foundation\Http\FormRequest;

class IndexUnitMeasurementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'count' => ['nullable', 'integer', 'min:1', 'max:200'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function getCount(): int
    {
        return (int) $this->input('count', 100);
    }

    public function getOffset(): int
    {
        return (int) $this->input('offset', 0);
    }
}
