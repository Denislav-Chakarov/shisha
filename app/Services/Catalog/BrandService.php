<?php

namespace App\Services\Catalog;

use App\Models\Brand;
use App\Models\Category;
use Illuminate\Validation\ValidationException;

class BrandService
{
    /**
     * @return array<int, Brand>
     */
    public function list(?int $categoryId = null): array
    {
        return Brand::query()
            ->when($categoryId !== null, fn ($q) => $q->where('category_id', $categoryId))
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Brand
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 255) {
            throw ValidationException::withMessages(['name' => 'Невалидно име.']);
        }

        $categoryId = (int) ($data['category_id'] ?? 0);
        if ($categoryId < 1 || ! Category::query()->whereKey($categoryId)->exists()) {
            throw ValidationException::withMessages(['category_id' => 'Невалидна категория.']);
        }

        if (Brand::query()->where('name', $name)->exists()) {
            throw ValidationException::withMessages(['name' => 'Бранд с това име вече съществува.']);
        }

        return Brand::query()->create([
            'name' => $name,
            'category_id' => $categoryId,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): Brand
    {
        $brand = Brand::query()->whereKey($id)->first();
        if ($brand === null) {
            throw ValidationException::withMessages(['brand' => 'Брандът не е намерен.']);
        }

        $name = trim((string) ($data['name'] ?? $brand->name));
        if ($name === '' || mb_strlen($name) > 255) {
            throw ValidationException::withMessages(['name' => 'Невалидно име.']);
        }

        $categoryId = (int) ($data['category_id'] ?? $brand->category_id);
        if ($categoryId < 1 || ! Category::query()->whereKey($categoryId)->exists()) {
            throw ValidationException::withMessages(['category_id' => 'Невалидна категория.']);
        }

        $duplicate = Brand::query()
            ->where('name', $name)
            ->where('id', '!=', $brand->id)
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['name' => 'Бранд с това име вече съществува.']);
        }

        $brand->update([
            'name' => $name,
            'category_id' => $categoryId,
        ]);

        return $brand->refresh();
    }

    public function delete(int $id): void
    {
        $brand = Brand::query()->whereKey($id)->first();
        if ($brand === null) {
            throw ValidationException::withMessages(['brand' => 'Брандът не е намерен.']);
        }

        if ($brand->products()->exists()) {
            throw ValidationException::withMessages(['brand' => 'Брандът не може да бъде изтрит, защото има продукти.']);
        }

        $brand->delete();
    }
}

