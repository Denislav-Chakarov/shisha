<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Catalog\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductsController extends Controller
{
    public function __construct(private readonly ProductService $products)
    {
    }

    public function index(Request $request): View
    {
        $defaultTab = $request->routeIs('inventory')
            ? 'inventory'
            : ($request->routeIs('deliveries') ? 'deliveries' : 'products');
        $tab = (string) $request->query('tab', $defaultTab);
        if (! in_array($tab, ['products', 'inventory', 'deliveries', 'brands', 'categories'], true)) {
            $tab = 'products';
        }

        if ($tab === 'categories') {
            $categoriesPage = Category::query()
                ->with('parent:id,name')
                ->orderBy('position')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString();

            return view('products', [
                'tab' => $tab,
                'categories' => Category::query()->orderBy('position')->orderBy('name')->get(),
                'categoriesPage' => $categoriesPage,
                'brands' => collect(),
            ]);
        }

        if ($tab === 'brands') {
            $categories = Category::query()
                ->orderBy('position')
                ->orderBy('name')
                ->get();

            $brandsPage = Brand::query()
                ->with('category:id,name,behavior_type')
                ->orderBy('name')
                ->paginate(25)
                ->withQueryString();

            return view('products', [
                'tab' => $tab,
                'categories' => $categories,
                'brandsPage' => $brandsPage,
                'brands' => collect(),
            ]);
        }

        $categories = Category::query()
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $rootCategories = Category::query()
            ->whereNull('parent_id')
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $childCategories = Category::query()
            ->whereNotNull('parent_id')
            ->orderBy('position')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'behavior_type', 'parent_id']);

        $brands = Brand::query()
            ->with('category:id,slug,behavior_type')
            ->orderBy('name')
            ->get();

        $data = [
            'tab' => $tab,
            'categories' => $categories,
            'rootCategories' => $rootCategories,
            'childCategories' => $childCategories,
            'brands' => $brands,
        ];

        if ($tab === 'inventory' || $tab === 'deliveries') {
            // reuse products listing logic, but render different partial
            $tab = 'products';
            $data['tab'] = $request->routeIs('deliveries') ? 'deliveries' : 'inventory';
        }

        $search = trim((string) $request->query('q', ''));
        $category = (string) $request->query('category', 'all'); // slug/behavior_type
        $categoryId = $request->filled('category_id') ? (int) $request->query('category_id') : null;
        $brandId = $request->filled('brand_id') ? (int) $request->query('brand_id') : null;
        $productId = $request->filled('product_id') ? (int) $request->query('product_id') : null;
        $sort = (string) $request->query('sort', 'name_asc');
        $view = (string) $request->query('view', 'list');
        if (! in_array($view, ['list', 'grid'], true)) {
            $view = 'list';
        }

        $filterBrands = Brand::query()
            ->when($categoryId !== null && $categoryId > 0, fn ($q) => $q->where('category_id', $categoryId))
            ->when(($categoryId === null || $categoryId <= 0) && $category !== 'all', function ($q) use ($category): void {
                $q->whereHas('category', fn ($cq) => $cq->where('slug', $category)->orWhere('behavior_type', $category));
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $filterProducts = Product::query()
            ->with(['brand:id,name', 'category:id,slug,behavior_type'])
            ->when($categoryId !== null && $categoryId > 0, fn ($q) => $q->where('category_id', $categoryId))
            ->when(($categoryId === null || $categoryId <= 0) && $category !== 'all', fn ($q) => $q->whereHas('category', fn ($cq) => $cq->where('slug', $category)->orWhere('behavior_type', $category)))
            ->when($brandId !== null && $brandId > 0, fn ($q) => $q->where('brand_id', $brandId))
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name', 'brand_id', 'category_id']);

        $query = DB::table('products')
            ->join('brands', 'products.brand_id', '=', 'brands.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->select(
                'products.id',
                'products.name',
                'products.category_id',
                'categories.slug as category_slug',
                'categories.behavior_type as category_behavior',
                'products.flavor',
                'products.price',
                'products.purchase_price',
                'products.stock_quantity',
                'products.unit',
                'products.image_path',
                'products.is_active',
                'products.writeoff_mode',
                'products.brand_id',
                'brands.name as brand_name'
            );

        if ($categoryId !== null && $categoryId > 0) {
            $query->where('products.category_id', $categoryId);
        } elseif ($category !== 'all') {
            $query->where(function ($q) use ($category): void {
                $q->where('categories.slug', $category)
                    ->orWhere('categories.behavior_type', $category);
            });
        }

        if ($brandId !== null && $brandId > 0) {
            $query->where('products.brand_id', $brandId);
        }

        if ($productId !== null && $productId > 0) {
            $query->where('products.id', $productId);
        }

        if ($search !== '') {
            $query->where(function ($sub) use ($search): void {
                $like = '%' . $search . '%';
                $sub->where('products.name', 'like', $like)
                    ->orWhere('products.flavor', 'like', $like)
                    ->orWhere('brands.name', 'like', $like);
            });
        }

        match ($sort) {
            'price_asc' => $query->orderBy('products.price'),
            'price_desc' => $query->orderByDesc('products.price'),
            'stock_asc' => $query->orderBy('products.stock_quantity'),
            'stock_desc' => $query->orderByDesc('products.stock_quantity'),
            default => $query->orderBy('products.name'),
        };

        $filteredProducts = $query->paginate(8)->withQueryString();

        $tobaccoPackPurchasesByProduct = collect();
        $genericDeliveriesByProduct = collect();
        $pageProductIds = $filteredProducts->pluck('id')->all();
        if ($pageProductIds !== []) {
            $tobaccoPackPurchasesByProduct = DB::table('tobacco_pack_purchases')
                ->whereIn('product_id', $pageProductIds)
                ->orderByDesc('restocked_at')
                ->orderByDesc('id')
                ->get()
                ->groupBy(fn ($row) => (int) $row->product_id);

            $tobaccoPackInventoryByProduct = DB::table('tobacco_pack_inventory')
                ->whereIn('product_id', $pageProductIds)
                ->orderBy('pack_grams')
                ->get()
                ->groupBy(fn ($row) => (int) $row->product_id);

            $genericDeliveriesByProduct = DB::table('product_deliveries')
                ->whereIn('product_id', $pageProductIds)
                ->orderByDesc('delivered_at')
                ->orderByDesc('id')
                ->get()
                ->groupBy(fn ($row) => (int) $row->product_id);
        } else {
            $tobaccoPackInventoryByProduct = collect();
        }

        $data['filteredProducts'] = $filteredProducts;
        $data['tobaccoPackPurchasesByProduct'] = $tobaccoPackPurchasesByProduct;
        $data['tobaccoPackInventoryByProduct'] = $tobaccoPackInventoryByProduct;
        $data['genericDeliveriesByProduct'] = $genericDeliveriesByProduct;
        $data['filterBrands'] = $filterBrands;
        $data['filterProducts'] = $filterProducts;
        $data['filters'] = [
            'q' => $search,
            'category' => $category,
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'product_id' => $productId,
            'sort' => $sort,
            'view' => $view,
        ];

        if ($request->routeIs('inventory')) {
            return view('products', array_merge($data, ['tab' => 'inventory']));
        }

        if ($request->routeIs('deliveries')) {
            return view('products', array_merge($data, ['tab' => 'deliveries']));
        }

        return view('products', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'selected_image_path' => ['nullable', 'string', 'max:512'],
            'is_active' => ['nullable', 'in:1'],
            'writeoff_mode' => ['nullable', 'in:manual,recipe,auto'],
        ]);

        try {
            $this->products->create($validated, $request->file('image'));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('status', 'Продуктът е добавен успешно.');
    }

    public function update(Request $request, int $productId): RedirectResponse
    {
        $validated = $request->validate([
            'brand_id' => ['required', 'integer', 'exists:brands,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'purchase_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'restock_add' => ['nullable', 'integer', 'min:0'],
            'unit' => ['nullable', 'string', 'max:20'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'selected_image_path' => ['nullable', 'string', 'max:512'],
            'is_active' => ['nullable', 'in:1'],
            'writeoff_mode' => ['nullable', 'in:manual,recipe,auto'],
        ]);

        try {
            $this->products->update($productId, $validated, $request->file('image'));
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('status', 'Продуктът е редактиран успешно.');
    }

    public function destroy(int $productId): RedirectResponse
    {
        try {
            $this->products->delete($productId);
        } catch (ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Продуктът е изтрит успешно.');
    }

    public function imageSuggestions(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        return response()->json($this->products->imageSuggestions($query));
    }

    public function storeTobaccoPackPurchase(Request $request, int $productId): RedirectResponse
    {
        $validated = $request->validate([
            'restocked_at' => ['required', 'date'],
            'pack_grams' => ['required', 'integer', 'min:1', 'max:500000'],
            'boxes_count' => ['required', 'integer', 'min:1', 'max:500000'],
            'purchase_price_per_box' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $this->products->storeTobaccoPackPurchase($productId, $validated);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('status', 'Зареждането е записано; наличните кутии и общият грамаж са обновени.');
    }

    public function updateTobaccoPackPurchase(Request $request, int $productId, int $purchaseId): RedirectResponse
    {
        $validated = $request->validate([
            'restocked_at' => ['required', 'date'],
            'pack_grams' => ['required', 'integer', 'min:1', 'max:500000'],
            'boxes_count' => ['required', 'integer', 'min:1', 'max:500000'],
            'purchase_price_per_box' => ['required', 'numeric', 'min:0'],
        ]);

        try {
            $this->products->updateTobaccoPackPurchase($productId, $purchaseId, $validated);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('status', 'Доставката е обновена; наличността е преизчислена.');
    }

    public function deleteTobaccoPackPurchase(int $productId, int $purchaseId): RedirectResponse
    {
        try {
            $this->products->deleteTobaccoPackPurchase($productId, $purchaseId);
        } catch (ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Записът за зареждане е изтрит; наличността е преизчислена.');
    }

    public function updateTobaccoPackInventory(Request $request, int $productId, int $inventoryId): RedirectResponse
    {
        $validated = $request->validate([
            'boxes_on_hand' => ['required', 'integer', 'min:0', 'max:500000'],
        ]);

        try {
            $this->products->updateTobaccoPackInventory($productId, $inventoryId, (int) $validated['boxes_on_hand']);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('status', 'Наличните кутии са обновени; общият грамаж е преизчислен.');
    }

    public function storeGenericDelivery(Request $request, int $productId): RedirectResponse
    {
        $validated = $request->validate([
            'delivered_at' => ['required', 'date'],
            'quantity' => ['required', 'integer', 'min:1', 'max:500000'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->products->storeGenericDelivery($productId, $validated);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('status', 'Зареждането е записано.');
    }

    public function updateGenericDelivery(Request $request, int $productId, int $deliveryId): RedirectResponse
    {
        $validated = $request->validate([
            'delivered_at' => ['required', 'date'],
            'quantity' => ['required', 'integer', 'min:1', 'max:500000'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->products->updateGenericDelivery($productId, $deliveryId, $validated);
        } catch (ValidationException $e) {
            return back()->withInput()->withErrors($e->errors());
        }

        return back()->with('status', 'Зареждането е обновено.');
    }

    public function deleteGenericDelivery(int $productId, int $deliveryId): RedirectResponse
    {
        try {
            $this->products->deleteGenericDelivery($productId, $deliveryId);
        } catch (ValidationException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Зареждането е изтрито.');
    }
}

