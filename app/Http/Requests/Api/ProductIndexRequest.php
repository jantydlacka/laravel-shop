<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductIndexRequest extends FormRequest
{
    public const SORTS = ['newest', 'name', 'price_asc', 'price_desc'];

    protected function prepareForValidation(): void
    {
        $q = $this->query('q');

        if (is_string($q)) {
            $this->merge(['q' => trim($q)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'category' => ['nullable', 'string'],
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'in_stock' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function search(): ?string
    {
        $q = $this->validated('q');

        return is_string($q) && $q !== '' ? $q : null;
    }

    public function sort(): string
    {
        return (string) ($this->validated('sort') ?? 'newest');
    }

    public function onlyInStock(): bool
    {
        return $this->boolean('in_stock');
    }
}
