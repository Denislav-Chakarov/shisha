<?php

namespace App\Services\Catalog;

use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryService
{
    /**
     * @return array<int, Category>
     */
    public function list(): array
    {
        return Category::query()
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Category
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 60) {
            throw ValidationException::withMessages(['name' => 'Невалидно име.']);
        }

        $parentId = null;
        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null && $data['parent_id'] !== '') {
            $parentId = (int) $data['parent_id'];
            if ($parentId < 1 || ! Category::query()->whereKey($parentId)->exists()) {
                throw ValidationException::withMessages(['parent_id' => 'Невалидна родителска категория.']);
            }
        }

        $slug = trim((string) ($data['slug'] ?? ''));
        $slug = $slug !== '' ? $slug : Str::slug($name);
        $slug = (string) Str::of($slug)->lower();
        if ($slug === '' || mb_strlen($slug) > 40) {
            throw ValidationException::withMessages(['slug' => 'Невалиден slug.']);
        }
        if (Category::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['slug' => 'Slug вече съществува.']);
        }

        $behavior = trim((string) ($data['behavior_type'] ?? 'generic'));
        if (! in_array($behavior, ['hookah', 'tobacco', 'drink', 'generic'], true)) {
            throw ValidationException::withMessages(['behavior_type' => 'Невалиден тип поведение.']);
        }

        $position = (int) ($data['position'] ?? 0);
        if ($position < 0 || $position > 32767) {
            throw ValidationException::withMessages(['position' => 'Невалидна позиция.']);
        }

        return Category::query()->create([
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'behavior_type' => $behavior,
            'position' => $position,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data): Category
    {
        $category = Category::query()->whereKey($id)->first();
        if ($category === null) {
            throw ValidationException::withMessages(['category' => 'Категорията не е намерена.']);
        }

        $parentId = null;
        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== null && $data['parent_id'] !== '') {
            $parentId = (int) $data['parent_id'];
            if ($parentId < 1 || ! Category::query()->whereKey($parentId)->exists()) {
                throw ValidationException::withMessages(['parent_id' => 'Невалидна родителска категория.']);
            }
            if ($parentId === (int) $category->id) {
                throw ValidationException::withMessages(['parent_id' => 'Категорията не може да е родител на себе си.']);
            }

            // prevent cycles (walk up the parent chain)
            $cursor = Category::query()->whereKey($parentId)->first();
            $seen = [];
            while ($cursor !== null && $cursor->parent_id !== null) {
                $cid = (int) $cursor->id;
                if (isset($seen[$cid])) {
                    break;
                }
                $seen[$cid] = true;
                if ((int) $cursor->parent_id === (int) $category->id) {
                    throw ValidationException::withMessages(['parent_id' => 'Невалидна йерархия (цикъл).']);
                }
                $cursor = Category::query()->whereKey((int) $cursor->parent_id)->first();
            }
        }

        $name = trim((string) ($data['name'] ?? $category->name));
        if ($name === '' || mb_strlen($name) > 60) {
            throw ValidationException::withMessages(['name' => 'Невалидно име.']);
        }

        $slug = trim((string) ($data['slug'] ?? $category->slug));
        $slug = $slug !== '' ? $slug : Str::slug($name);
        $slug = (string) Str::of($slug)->lower();
        if ($slug === '' || mb_strlen($slug) > 40) {
            throw ValidationException::withMessages(['slug' => 'Невалиден slug.']);
        }
        $duplicateSlug = Category::query()
            ->where('slug', $slug)
            ->where('id', '!=', $category->id)
            ->exists();
        if ($duplicateSlug) {
            throw ValidationException::withMessages(['slug' => 'Slug вече съществува.']);
        }

        $behavior = trim((string) ($data['behavior_type'] ?? $category->behavior_type));
        if (! in_array($behavior, ['hookah', 'tobacco', 'drink', 'generic'], true)) {
            throw ValidationException::withMessages(['behavior_type' => 'Невалиден тип поведение.']);
        }

        if (in_array((string) $category->slug, ['hookah', 'tobacco', 'drink'], true)
            && (string) $category->behavior_type !== $behavior
        ) {
            throw ValidationException::withMessages(['behavior_type' => 'Не може да се променя поведението на системните категории.']);
        }

        $position = (int) ($data['position'] ?? $category->position);
        if ($position < 0 || $position > 32767) {
            throw ValidationException::withMessages(['position' => 'Невалидна позиция.']);
        }

        $category->update([
            'parent_id' => $parentId,
            'name' => $name,
            'slug' => $slug,
            'behavior_type' => $behavior,
            'position' => $position,
        ]);

        return $category->refresh();
    }

    public function delete(int $id): void
    {
        $category = Category::query()->whereKey($id)->first();
        if ($category === null) {
            throw ValidationException::withMessages(['category' => 'Категорията не е намерена.']);
        }

        if ($category->brands()->exists() || $category->products()->exists()) {
            throw ValidationException::withMessages(['category' => 'Категорията не може да бъде изтрита, защото има брандове/продукти.']);
        }

        $category->delete();
    }
}

