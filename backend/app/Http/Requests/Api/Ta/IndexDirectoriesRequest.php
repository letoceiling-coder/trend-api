<?php

namespace App\Http\Requests\Api\Ta;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexDirectoriesRequest extends FormRequest
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
            'type' => ['required', 'string', 'max:64'],
            'city_id' => ['nullable', 'string', 'max:64'],
            'lang' => ['nullable', 'string', 'max:8', Rule::in(['ru', 'en', 'uk', 'kz', 'by'])],
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
}
