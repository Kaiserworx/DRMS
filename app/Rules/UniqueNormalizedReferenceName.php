<?php

namespace App\Rules;

use App\Services\ReferenceNameNormalizer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;

class UniqueNormalizedReferenceName implements ValidationRule
{
    /**
     * @param  class-string<Model>  $model
     * @param  array<string, mixed>  $scope
     */
    public function __construct(
        private readonly string $model,
        private readonly int|string|null $ignoreId = null,
        private readonly array $scope = [],
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $query = $this->model::query()
            ->where('normalized_name', ReferenceNameNormalizer::key($value));

        foreach ($this->scope as $column => $scopeValue) {
            $query->where($column, $scopeValue);
        }

        if ($this->ignoreId !== null) {
            $query->whereKeyNot($this->ignoreId);
        }

        if ($query->exists()) {
            $fail('An equivalent reference name already exists.');
        }
    }
}
