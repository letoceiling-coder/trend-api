<?php

namespace App\Http\Requests\Api\Ta;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexTaRequest extends FormRequest
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
            'city_id' => ['nullable', 'string', 'max:64'],
            'lang' => ['nullable', 'string', 'max:8', Rule::in(['ru', 'en', 'uk', 'kz', 'by'])],
            'count' => ['nullable', 'integer', 'min:1', 'max:200'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'sort' => ['nullable', 'string', 'max:64'],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
        ];
    }

    public function getCityId(): string
    {
        return $this->input('city_id') ?? (string) config('trendagent.default_city_id', '');
    }

    public function getLang(): string
    {
        return $this->input('lang') ?? (string) config('trendagent.default_lang', 'ru');
    }

    public function getCount(): int
    {
        return (int) $this->input('count', 20);
    }

    public function getOffset(): int
    {
        return (int) $this->input('offset', 0);
    }

    public function getSort(): ?string
    {
        $s = $this->input('sort');
        return $s === null || $s === '' ? null : (string) $s;
    }

    public function getSortOrder(): string
    {
        return $this->input('sort_order', 'asc') === 'desc' ? 'desc' : 'asc';
    }
}
