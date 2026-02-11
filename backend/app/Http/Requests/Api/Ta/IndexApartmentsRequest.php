<?php

namespace App\Http\Requests\Api\Ta;

class IndexApartmentsRequest extends IndexTaRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'block_id' => ['nullable', 'string', 'max:64'],
        ]);
    }

    public function getBlockId(): ?string
    {
        $v = $this->input('block_id');
        return $v === null || $v === '' ? null : (string) $v;
    }
}
