<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductIndexRequest extends FormRequest
{
    public const MAX_PAGE = 10000;

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
            'page' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PAGE],
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

    /**
     * @return array<string, string|int>
     */
    public function appendedQuery(): array
    {
        $category = $this->validated('category');

        return array_filter([
            'category' => is_string($category) && $category !== '' ? $category : null,
            'q' => $this->search(),
            'sort' => $this->sort() !== 'newest' ? $this->sort() : null,
            'in_stock' => $this->onlyInStock() ? 1 : null,
        ], fn ($value) => $value !== null);
    }
}
