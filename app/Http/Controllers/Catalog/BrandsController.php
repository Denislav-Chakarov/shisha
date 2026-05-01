<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Services\Catalog\BrandService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class BrandsController extends Controller
{
    public function __construct(private readonly BrandService $brands)
    {
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:brands,name'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ]);

        try {
            $this->brands->create($validated);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('status', 'Брандът е добавен успешно.');
    }

    public function update(Request $request, int $brandId): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
        ]);

        try {
            $this->brands->update($brandId, $validated);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('status', 'Брандът е обновен успешно.');
    }

    public function destroy(int $brandId): RedirectResponse
    {
        try {
            $this->brands->delete($brandId);
        } catch (ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Брандът е изтрит успешно.');
    }
}

