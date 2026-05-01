<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Services\Catalog\CategoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CategoriesController extends Controller
{
    public function __construct(private readonly CategoryService $categories)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['nullable', 'string', 'max:40'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'behavior_type' => ['required', 'in:hookah,tobacco,drink,generic'],
            'position' => ['nullable', 'integer', 'min:0', 'max:32767'],
        ]);

        try {
            $this->categories->create($validated);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('status', 'Категорията е добавена успешно.');
    }

    public function update(Request $request, int $categoryId): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'slug' => ['required', 'string', 'max:40'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'behavior_type' => ['required', 'in:hookah,tobacco,drink,generic'],
            'position' => ['nullable', 'integer', 'min:0', 'max:32767'],
        ]);

        try {
            $this->categories->update($categoryId, $validated);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('status', 'Категорията е обновена успешно.');
    }

    public function destroy(int $categoryId): RedirectResponse
    {
        try {
            $this->categories->delete($categoryId);
        } catch (ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Категорията е изтрита успешно.');
    }
}

