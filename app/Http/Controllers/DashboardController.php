<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        return view('dashboard', $this->buildPageData($request));
    }

    public function workersPresence(Request $request): JsonResponse
    {
        return response()->json($this->getWorkersPresenceData($request));
    }

    public function products(Request $request): View
    {
        $data = $this->buildPageData($request);

        $search = trim((string) $request->query('q', ''));
        $category = (string) $request->query('category', 'all');
        $sort = (string) $request->query('sort', 'name_asc');
        $view = (string) $request->query('view', 'list');
        if (! in_array($view, ['list', 'grid'], true)) {
            $view = 'list';
        }
        $query = DB::table('products')
            ->join('brands', 'products.brand_id', '=', 'brands.id')
            ->select(
                'products.id',
                'products.name',
                'products.category',
                'products.flavor',
                'products.price',
                'products.purchase_price',
                'products.stock_quantity',
                'products.unit',
                'products.image_path',
                'products.is_active',
                'products.brand_id',
                'brands.name as brand_name'
            );

        if ($category !== 'all') {
            $query->where('products.category', $category);
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
        } else {
            $tobaccoPackInventoryByProduct = collect();
        }

        $data['filteredProducts'] = $filteredProducts;
        $data['tobaccoPackPurchasesByProduct'] = $tobaccoPackPurchasesByProduct;
        $data['tobaccoPackInventoryByProduct'] = $tobaccoPackInventoryByProduct;
        $data['filters'] = [
            'q' => $search,
            'category' => $category,
            'sort' => $sort,
            'view' => $view,
        ];
        return view('products', $data);
    }

    public function tables(Request $request): View
    {
        return view('tables', $this->buildPageData($request));
    }

    public function reports(Request $request): View
    {
        $dateFromInput = trim((string) $request->query('date_from', now()->format('Y-m-d')));
        $dateToInput = trim((string) $request->query('date_to', now()->format('Y-m-d')));
        $tableId = $request->filled('table_id') ? (int) $request->query('table_id') : null;

        try {
            $startAt = Carbon::parse($dateFromInput)->startOfDay();
        } catch (\Throwable) {
            $startAt = now()->startOfDay();
            $dateFromInput = $startAt->format('Y-m-d');
        }

        try {
            $endAt = Carbon::parse($dateToInput)->endOfDay();
        } catch (\Throwable) {
            $endAt = now()->endOfDay();
            $dateToInput = $endAt->format('Y-m-d');
        }

        if ($endAt->lessThan($startAt)) {
            [$startAt, $endAt] = [$endAt->copy()->startOfDay(), $startAt->copy()->endOfDay()];
            $dateFromInput = $startAt->format('Y-m-d');
            $dateToInput = $endAt->format('Y-m-d');
        }

        $paidOrdersBaseQuery = DB::table('orders')
            ->join('store_tables', 'orders.store_table_id', '=', 'store_tables.id')
            ->where('orders.status', 'paid')
            ->whereBetween('orders.closed_at', [$startAt, $endAt]);

        if ($tableId !== null) {
            $paidOrdersBaseQuery->where('orders.store_table_id', $tableId);
        }

        $summary = [
            'orders_count' => (clone $paidOrdersBaseQuery)->count('orders.id'),
            'revenue_total' => (float) (clone $paidOrdersBaseQuery)->sum('orders.total_amount'),
        ];

        $byDay = DB::table('orders')
            ->where('status', 'paid')
            ->whereBetween('closed_at', [$startAt, $endAt])
            ->when($tableId !== null, fn ($q) => $q->where('store_table_id', $tableId))
            ->selectRaw('DATE(closed_at) as report_day, COUNT(*) as orders_count, SUM(total_amount) as revenue_total')
            ->groupByRaw('DATE(closed_at)')
            ->orderBy('report_day')
            ->get();

        $byTable = DB::table('orders')
            ->join('store_tables', 'orders.store_table_id', '=', 'store_tables.id')
            ->where('orders.status', 'paid')
            ->whereBetween('orders.closed_at', [$startAt, $endAt])
            ->when($tableId !== null, fn ($q) => $q->where('orders.store_table_id', $tableId))
            ->selectRaw('store_tables.id as table_id, store_tables.table_number, COUNT(orders.id) as orders_count, SUM(orders.total_amount) as revenue_total')
            ->groupBy('store_tables.id', 'store_tables.table_number')
            ->orderBy('store_tables.table_number')
            ->get();

        $ordersDetailed = collect();
        if (DB::getDriverName() === 'mysql') {
            try {
                $rows = DB::select(
                    'CALL sp_paid_orders_report(?, ?, ?)',
                    [$startAt->format('Y-m-d H:i:s'), $endAt->format('Y-m-d H:i:s'), $tableId]
                );
                $ordersDetailed = collect($rows);
            } catch (\Throwable) {
                // fallback query below
            }
        }

        if ($ordersDetailed->isEmpty()) {
            $ordersDetailed = DB::table('orders')
                ->join('store_tables', 'orders.store_table_id', '=', 'store_tables.id')
                ->leftJoin('order_items', 'order_items.order_id', '=', 'orders.id')
                ->where('orders.status', 'paid')
                ->whereBetween('orders.closed_at', [$startAt, $endAt])
                ->when($tableId !== null, fn ($q) => $q->where('orders.store_table_id', $tableId))
                ->selectRaw('orders.id as order_id, store_tables.table_number, orders.total_amount, orders.closed_at, COUNT(order_items.id) as items_count')
                ->groupBy('orders.id', 'store_tables.table_number', 'orders.total_amount', 'orders.closed_at')
                ->orderByDesc('orders.closed_at')
                ->get();
        }

        $tables = DB::table('store_tables')->select('id', 'table_number')->orderBy('table_number')->get();

        return view('reports', [
            'filters' => [
                'date_from' => $dateFromInput,
                'date_to' => $dateToInput,
                'table_id' => $tableId,
            ],
            'tables' => $tables,
            'summary' => $summary,
            'byDay' => $byDay,
            'byTable' => $byTable,
            'ordersDetailed' => $ordersDetailed,
        ]);
    }

    private function buildPageData(Request $request): array
    {
        $currentUserId = $request->user()?->id;
        if ($currentUserId !== null) {
            DB::table('users')
                ->where('id', $currentUserId)
                ->update(['last_seen_at' => now(), 'updated_at' => now()]);
        }

        DB::transaction(function (): void {
            $existing = DB::table('store_tables')
                ->pluck('table_number')
                ->map(fn ($value) => (int) $value)
                ->all();

            $existingMap = array_fill_keys($existing, true);
            $rowsToInsert = [];

            for ($number = 1; $number <= 20; $number++) {
                if (! isset($existingMap[$number])) {
                    $rowsToInsert[] = [
                        'table_number' => $number,
                        'is_active' => true,
                        'status' => 'available',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if ($rowsToInsert !== []) {
                DB::table('store_tables')->insert($rowsToInsert);
            }
        });

        $brands = DB::table('brands')->orderBy('category')->orderBy('name')->get();

        $products = DB::table('products')
            ->join('brands', 'products.brand_id', '=', 'brands.id')
            ->select(
                'products.id',
                'products.name',
                'products.category',
                'products.flavor',
                'products.price',
                'products.purchase_price',
                'products.stock_quantity',
                'products.unit',
                'products.image_path',
                'products.is_active',
                'brands.name as brand_name'
            )
            ->orderBy('brands.name')
            ->orderBy('products.name')
            ->get();

        $openOrdersSubquery = DB::table('orders')
            ->select('store_table_id', DB::raw('COUNT(*) as open_orders'), DB::raw('SUM(total_amount) as open_total'))
            ->where('status', 'open')
            ->groupBy('store_table_id');

        $reservationsSubquery = DB::table('table_reservations')
            ->select(
                'store_table_id',
                DB::raw('COUNT(*) as reservation_count'),
                DB::raw('COALESCE(SUM(people_count), 0) as reservation_people_total'),
                DB::raw('MIN(starts_at) as next_reservation_from')
            )
            ->where('status', 'active')
            ->where('ends_at', '>=', now())
            ->groupBy('store_table_id');

        $tables = DB::table('store_tables')
            ->leftJoinSub($openOrdersSubquery, 'open_orders_data', function ($join): void {
                $join->on('store_tables.id', '=', 'open_orders_data.store_table_id');
            })
            ->leftJoinSub($reservationsSubquery, 'reservation_data', function ($join): void {
                $join->on('store_tables.id', '=', 'reservation_data.store_table_id');
            })
            ->select(
                'store_tables.id',
                'store_tables.table_number',
                'store_tables.is_active',
                'store_tables.status',
                'store_tables.reserved_from',
                'store_tables.reserved_to',
                'store_tables.reserved_people',
                DB::raw('COALESCE(open_orders_data.open_orders, 0) as open_orders'),
                DB::raw('COALESCE(open_orders_data.open_total, 0) as open_total'),
                DB::raw('COALESCE(reservation_data.reservation_count, 0) as reservation_count'),
                DB::raw('COALESCE(reservation_data.reservation_people_total, 0) as reservation_people_total'),
                'reservation_data.next_reservation_from'
            )
            ->orderBy('store_tables.table_number')
            ->whereBetween('store_tables.table_number', [1, 20])
            ->get();

        $openOrders = DB::table('orders')
            ->join('store_tables', 'orders.store_table_id', '=', 'store_tables.id')
            ->where('orders.status', 'open')
            ->orderBy('store_tables.table_number')
            ->select('orders.id', 'orders.store_table_id', 'store_tables.table_number', 'orders.total_amount')
            ->get();

        $selectedTableId = (int) $request->integer('table');
        if ($selectedTableId === 0 && $tables->isNotEmpty()) {
            $selectedTableId = (int) $tables->first()->id;
        }

        $selectedOpenOrder = DB::table('orders')
            ->where('store_table_id', $selectedTableId)
            ->where('status', 'open')
            ->latest('id')
            ->first();

        $selectedOrderItems = collect();
        $selectedOrderTotal = 0.0;
        if ($selectedOpenOrder !== null) {
            $selectedOrderItems = DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->join('brands', 'products.brand_id', '=', 'brands.id')
                ->where('order_items.order_id', $selectedOpenOrder->id)
                ->select(
                    'order_items.quantity',
                    'order_items.unit_price',
                    'order_items.line_total',
                    'order_items.meta_note',
                    'products.name as product_name',
                    'brands.name as brand_name'
                )
                ->orderBy('order_items.id')
                ->get();
            $selectedOrderTotal = (float) $selectedOrderItems->sum('line_total');
        }

        $recentProducts = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('store_tables', 'orders.store_table_id', '=', 'store_tables.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.store_table_id', $selectedTableId)
            ->where('orders.status', 'open')
            ->orderByDesc('order_items.created_at')
            ->limit(8)
            ->select(
                'store_tables.table_number',
                'products.name as product_name',
                'order_items.quantity',
                'order_items.unit_price',
                'order_items.line_total',
                'order_items.meta_note',
                'orders.status',
                'order_items.created_at'
            )
            ->get();

        $selectedReservations = DB::table('table_reservations')
            ->where('store_table_id', $selectedTableId)
            ->where('status', 'active')
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(8)
            ->get();

        $userId = (int) ($request->user()?->id ?? 0);
        $aiDraftPrompt = $request->session()->get(
            $this->getAiPromptDraftSessionKey($userId, $selectedTableId),
            ''
        );
        $lastAiPrompt = $request->session()->get(
            $this->getAiPromptLastSessionKey($userId, $selectedTableId),
            ''
        );

        $freeTables = $tables->filter(function ($table): bool {
            return (bool) $table->is_active
                && ($table->status ?? 'available') === 'available'
                && (int) ($table->open_orders ?? 0) === 0
                && (int) ($table->reservation_count ?? 0) === 0;
        })->count();

        $stats = [
            'free_tables' => $freeTables,
            'today_orders' => DB::table('orders')->whereDate('created_at', now()->toDateString())->count(),
            'today_sales' => (float) DB::table('orders')
                ->where('status', 'paid')
                ->whereDate('closed_at', now()->toDateString())
                ->sum('total_amount'),
            'total_products' => $products->count(),
        ];

        return [
            'brands' => $brands,
            'products' => $products,
            'tables' => $tables,
            'openOrders' => $openOrders,
            'selectedTableId' => $selectedTableId,
            'selectedOpenOrder' => $selectedOpenOrder,
            'selectedOrderItems' => $selectedOrderItems,
            'selectedOrderTotal' => $selectedOrderTotal,
            'selectedReservations' => $selectedReservations,
            'recentProducts' => $recentProducts,
            'workersPresence' => $this->getWorkersPresenceData($request),
            'aiDraftPrompt' => $aiDraftPrompt,
            'lastAiPrompt' => $lastAiPrompt,
            'stats' => $stats,
        ];
    }

    /**
     * @return array<int, array{id:int, username:string, online:bool, is_current:bool}>
     */
    private function getWorkersPresenceData(Request $request): array
    {
        $now = now();
        $onlineThreshold = $now->copy()->subMinutes(2);
        $currentUserId = (int) ($request->user()?->id ?? 0);

        return DB::table('users')
            ->select('id', 'username', 'last_seen_at')
            ->orderBy('username')
            ->get()
            ->map(function ($user) use ($onlineThreshold, $currentUserId): array {
                $lastSeen = $user->last_seen_at ? Carbon::parse((string) $user->last_seen_at) : null;
                $isOnline = $lastSeen !== null && $lastSeen->greaterThanOrEqualTo($onlineThreshold);

                return [
                    'id' => (int) $user->id,
                    'username' => (string) $user->username,
                    'online' => $isOnline,
                    'is_current' => (int) $user->id === $currentUserId,
                ];
            })
            ->values()
            ->all();
    }

    public function addBrand(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:brands,name'],
            'category' => ['required', 'in:tobacco,drink,hookah'],
        ]);

        DB::table('brands')->insert([
            'name' => $validated['name'],
            'category' => $validated['category'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('status', 'Брандът е добавен успешно.');
    }

    public function addProduct(Request $request): RedirectResponse
    {
        try {
            $validated = $request->validate([
                'brand_id' => ['required', 'integer', 'exists:brands,id'],
                'category' => ['required', 'in:tobacco,drink,hookah'],
                'name' => ['nullable', 'string', 'max:255'],
                'purchase_price' => ['nullable', 'numeric', 'min:0'],
                'sale_price' => ['nullable', 'numeric', 'min:0'],
                'stock_quantity' => [
                    Rule::requiredIf(static fn () => $request->input('category') !== 'tobacco'),
                    'nullable',
                    'integer',
                    'min:0',
                ],
                'unit' => ['nullable', 'string', 'max:20'],
                'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
                'selected_image_path' => ['nullable', 'string', 'max:512'],
                'is_active' => ['nullable', 'in:1'],
            ]);

            $category = $validated['category'];
            $brand = DB::table('brands')->where('id', $validated['brand_id'])->first();
            if ($brand === null) {
                return back()->withInput()->with('error', 'Избраният бранд не е намерен.');
            }

            $productName = trim((string) ($validated['name'] ?? ''));
            if ($category === 'hookah') {
                $productName = trim((string) $brand->name);
            }

            if ($productName === '') {
                return back()->withInput()->with('error', 'Името на продукта е задължително.');
            }

            $duplicateExists = DB::table('products')
                ->where('brand_id', $validated['brand_id'])
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($productName)])
                ->exists();

            if ($duplicateExists) {
                return back()->withInput()->with('error', 'Този продукт вече съществува за избрания бранд. Използвайте "Редактирай", за да промените наличност/данни.');
            }

            $imagePath = $validated['selected_image_path'] ?? null;
            if ($request->hasFile('image')) {
                $stored = $request->file('image')->store('products', 'public');
                $imagePath = 'storage/' . $stored;
            }

            $purchase = (float) ($validated['purchase_price'] ?? 0);
            $saleRaw = $validated['sale_price'] ?? null;
            $sale = ($saleRaw === null || $saleRaw === '')
                ? ($category === 'tobacco' ? 0.0 : $purchase)
                : (float) $saleRaw;
            $unit = $category === 'tobacco'
                ? 'g'
                : trim((string) ($validated['unit'] ?? 'бр'));

            if ($category === 'tobacco') {
                $purchase = 0.0;
                $sale = 0.0;
            }

            $stockQty = $category === 'tobacco'
                ? 0
                : (int) $validated['stock_quantity'];

            DB::table('products')->insert([
                'brand_id' => $validated['brand_id'],
                'category' => $category,
                'name' => $productName,
                'flavor' => null,
                'price' => $sale,
                'purchase_price' => $purchase,
                'stock_quantity' => $stockQty,
                'unit' => $unit,
                'image_path' => $imagePath,
                'is_active' => array_key_exists('is_active', $validated) ? $request->boolean('is_active') : true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return back()->with('status', 'Продуктът е добавен успешно.');
        } catch (QueryException) {
            return back()->withInput()->with('error', 'Неуспешно добавяне на продукта. Възможно е вече да съществува със същия бранд.');
        } catch (\Throwable) {
            return back()->withInput()->with('error', 'Възникна неочаквана грешка при добавяне на продукта. Опитайте отново.');
        }
    }

    public function updateProduct(Request $request, int $productId): RedirectResponse
    {
        try {
            $product = DB::table('products')->where('id', $productId)->first();
            if ($product === null) {
                return back()->with('error', 'Продуктът не е намерен.');
            }

            $validated = $request->validate([
                'brand_id' => ['required', 'integer', 'exists:brands,id'],
                'category' => ['required', 'in:tobacco,drink,hookah'],
                'name' => ['nullable', 'string', 'max:255'],
                'purchase_price' => ['nullable', 'numeric', 'min:0'],
                'sale_price' => ['nullable', 'numeric', 'min:0'],
                'stock_quantity' => [
                    Rule::requiredIf(static fn () => $request->input('category') !== 'tobacco'),
                    'nullable',
                    'integer',
                    'min:0',
                ],
                'restock_add' => ['nullable', 'integer', 'min:0'],
                'unit' => ['nullable', 'string', 'max:20'],
                'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
                'selected_image_path' => ['nullable', 'string', 'max:512'],
                'is_active' => ['nullable', 'in:1'],
            ]);

            $category = $validated['category'];
            $brand = DB::table('brands')->where('id', $validated['brand_id'])->first();
            if ($brand === null) {
                return back()->withInput()->with('error', 'Избраният бранд не е намерен.');
            }

            $productName = trim((string) ($validated['name'] ?? ''));
            if ($category === 'hookah') {
                $productName = trim((string) $brand->name);
            }

            if ($productName === '') {
                return back()->withInput()->with('error', 'Името на продукта е задължително.');
            }

            $duplicateExists = DB::table('products')
                ->where('id', '!=', $productId)
                ->where('brand_id', $validated['brand_id'])
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($productName)])
                ->exists();

            if ($duplicateExists) {
                return back()->withInput()->with('error', 'Вече има друг продукт със същото име за този бранд.');
            }

            $imagePath = $validated['selected_image_path'] ?? $product->image_path;
            if ($request->hasFile('image')) {
                $stored = $request->file('image')->store('products', 'public');
                $imagePath = 'storage/' . $stored;
            }

            $purchase = (float) ($validated['purchase_price'] ?? 0);
            $saleRaw = $validated['sale_price'] ?? null;
            $sale = ($saleRaw === null || $saleRaw === '')
                ? (float) ($product->price ?? 0)
                : (float) $saleRaw;
            $unit = $category === 'tobacco'
                ? 'g'
                : trim((string) ($validated['unit'] ?? 'бр'));

            if ($category === 'tobacco') {
                $purchase = 0.0;
                $sale = 0.0;
            }

            if ($category !== 'tobacco') {
                DB::table('tobacco_pack_inventory')->where('product_id', $productId)->delete();
            }

            if ($category === 'tobacco') {
                $finalStock = $this->sumTobaccoInventoryGrams($productId);
            } else {
                $baseStock = (int) ($validated['stock_quantity'] ?? 0);
                $restockAdd = (int) ($validated['restock_add'] ?? 0);
                $finalStock = $baseStock + $restockAdd;
            }

            DB::table('products')->where('id', $productId)->update([
                'brand_id' => $validated['brand_id'],
                'category' => $category,
                'name' => $productName,
                'flavor' => null,
                'price' => $sale,
                'purchase_price' => $purchase,
                'stock_quantity' => $finalStock,
                'unit' => $unit,
                'image_path' => $imagePath,
                'is_active' => $request->boolean('is_active'),
                'updated_at' => now(),
            ]);

            return back()->with('status', 'Продуктът е редактиран успешно.');
        } catch (QueryException) {
            return back()->withInput()->with('error', 'Неуспешна редакция на продукта. Проверете дали няма дублиране.');
        } catch (\Throwable) {
            return back()->withInput()->with('error', 'Възникна неочаквана грешка при редакция на продукта.');
        }
    }

    public function deleteProduct(int $productId): RedirectResponse
    {
        $product = DB::table('products')->where('id', $productId)->first();
        if ($product === null) {
            return back()->with('error', 'Продуктът не е намерен.');
        }

        DB::table('products')->where('id', $productId)->delete();

        return back()->with('status', 'Продуктът е изтрит успешно.');
    }

    public function storeTobaccoPackPurchase(Request $request, int $productId): RedirectResponse
    {
        $product = DB::table('products')->where('id', $productId)->first();
        if ($product === null || $product->category !== 'tobacco') {
            return back()->with('error', 'Зареждания се записват само за продукти от категория „Тютюн“.');
        }

        $validated = $request->validate([
            'restocked_at' => ['required', 'date'],
            'pack_grams' => ['required', 'integer', 'min:1', 'max:500000'],
            'boxes_count' => ['required', 'integer', 'min:1', 'max:500000'],
            'purchase_price_per_box' => ['required', 'numeric', 'min:0'],
        ]);

        $boxes = (int) $validated['boxes_count'];
        $packGrams = (int) $validated['pack_grams'];

        DB::transaction(function () use ($productId, $validated, $boxes, $packGrams): void {
            DB::table('tobacco_pack_purchases')->insert([
                'product_id' => $productId,
                'restocked_at' => $validated['restocked_at'],
                'pack_grams' => $packGrams,
                'boxes_count' => $boxes,
                'purchase_price_per_box' => (float) $validated['purchase_price_per_box'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->adjustTobaccoInventoryBoxes($productId, $packGrams, $boxes);
            $this->recalculateTobaccoProductStockFromInventory($productId);
        });

        return back()->with('status', 'Зареждането е записано; наличните кутии и общият грамаж са обновени.');
    }

    public function updateTobaccoPackPurchase(Request $request, int $productId, int $purchaseId): RedirectResponse
    {
        $product = DB::table('products')->where('id', $productId)->first();
        if ($product === null || $product->category !== 'tobacco') {
            return back()->with('error', 'Невалиден продукт.');
        }

        $existing = DB::table('tobacco_pack_purchases')
            ->where('id', $purchaseId)
            ->where('product_id', $productId)
            ->first();

        if ($existing === null) {
            return back()->with('error', 'Записът за зареждане не е намерен.');
        }

        $validated = $request->validate([
            'restocked_at' => ['required', 'date'],
            'pack_grams' => ['required', 'integer', 'min:1', 'max:500000'],
            'boxes_count' => ['required', 'integer', 'min:1', 'max:500000'],
            'purchase_price_per_box' => ['required', 'numeric', 'min:0'],
        ]);

        $oldPack = (int) $existing->pack_grams;
        $oldBoxes = (int) $existing->boxes_count;
        $newPack = (int) $validated['pack_grams'];
        $newBoxes = (int) $validated['boxes_count'];

        DB::transaction(function () use ($productId, $purchaseId, $validated, $oldPack, $oldBoxes, $newPack, $newBoxes): void {
            $this->adjustTobaccoInventoryBoxes($productId, $oldPack, -$oldBoxes);
            $this->adjustTobaccoInventoryBoxes($productId, $newPack, $newBoxes);

            DB::table('tobacco_pack_purchases')
                ->where('id', $purchaseId)
                ->where('product_id', $productId)
                ->update([
                    'restocked_at' => $validated['restocked_at'],
                    'pack_grams' => $newPack,
                    'boxes_count' => $newBoxes,
                    'purchase_price_per_box' => (float) $validated['purchase_price_per_box'],
                    'updated_at' => now(),
                ]);

            $this->recalculateTobaccoProductStockFromInventory($productId);
        });

        return back()->with('status', 'Доставката е обновена; наличността е преизчислена.');
    }

    public function deleteTobaccoPackPurchase(int $productId, int $purchaseId): RedirectResponse
    {
        $product = DB::table('products')->where('id', $productId)->first();
        if ($product === null || $product->category !== 'tobacco') {
            return back()->with('error', 'Невалиден продукт.');
        }

        $row = DB::table('tobacco_pack_purchases')
            ->where('id', $purchaseId)
            ->where('product_id', $productId)
            ->first();

        if ($row === null) {
            return back()->with('error', 'Записът за зареждане не е намерен.');
        }

        DB::transaction(function () use ($productId, $purchaseId, $row): void {
            $this->adjustTobaccoInventoryBoxes($productId, (int) $row->pack_grams, -(int) $row->boxes_count);
            DB::table('tobacco_pack_purchases')
                ->where('id', $purchaseId)
                ->where('product_id', $productId)
                ->delete();
            $this->recalculateTobaccoProductStockFromInventory($productId);
        });

        return back()->with('status', 'Записът за зареждане е изтрит; наличността е преизчислена.');
    }

    public function updateTobaccoPackInventory(Request $request, int $productId, int $inventoryId): RedirectResponse
    {
        $product = DB::table('products')->where('id', $productId)->first();
        if ($product === null || $product->category !== 'tobacco') {
            return back()->with('error', 'Невалиден продукт.');
        }

        $validated = $request->validate([
            'boxes_on_hand' => ['required', 'integer', 'min:0', 'max:500000'],
        ]);

        $updated = DB::table('tobacco_pack_inventory')
            ->where('id', $inventoryId)
            ->where('product_id', $productId)
            ->update([
                'boxes_on_hand' => (int) $validated['boxes_on_hand'],
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            return back()->with('error', 'Редът за наличност не е намерен.');
        }

        $this->recalculateTobaccoProductStockFromInventory($productId);

        return back()->with('status', 'Наличните кутии са обновени; общият грамаж е преизчислен.');
    }

    private function adjustTobaccoInventoryBoxes(int $productId, int $packGrams, int $delta): void
    {
        if ($delta === 0) {
            return;
        }

        $existing = DB::table('tobacco_pack_inventory')
            ->where('product_id', $productId)
            ->where('pack_grams', $packGrams)
            ->first();

        if ($existing !== null) {
            $newBoxes = max(0, (int) $existing->boxes_on_hand + $delta);
            DB::table('tobacco_pack_inventory')
                ->where('id', $existing->id)
                ->update([
                    'boxes_on_hand' => $newBoxes,
                    'updated_at' => now(),
                ]);
        } elseif ($delta > 0) {
            DB::table('tobacco_pack_inventory')->insert([
                'product_id' => $productId,
                'pack_grams' => $packGrams,
                'boxes_on_hand' => $delta,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function sumTobaccoInventoryGrams(int $productId): int
    {
        return (int) DB::table('tobacco_pack_inventory')
            ->where('product_id', $productId)
            ->get()
            ->sum(fn ($r) => (int) $r->pack_grams * (int) $r->boxes_on_hand);
    }

    private function recalculateTobaccoProductStockFromInventory(int $productId): void
    {
        $grams = $this->sumTobaccoInventoryGrams($productId);
        DB::table('products')
            ->where('id', $productId)
            ->where('category', 'tobacco')
            ->update([
                'stock_quantity' => $grams,
                'updated_at' => now(),
            ]);
    }

    public function imageSuggestions(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json([]);
        }

        $normalizedQuery = Str::of($query)->lower()->replace(' ', '')->value();
        $results = [];
        $seenPaths = [];

        // 1) Prefer suggestions from already saved products (name -> image mapping).
        $dbSuggestions = DB::table('products')
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->where(function ($sub) use ($query): void {
                $like = '%' . $query . '%';
                $sub->where('name', 'like', $like)
                    ->orWhere('flavor', 'like', $like);
            })
            ->orderByRaw('CASE WHEN LOWER(name) = ? THEN 0 ELSE 1 END', [mb_strtolower($query)])
            ->orderBy('name')
            ->limit(20)
            ->get(['name', 'image_path']);

        foreach ($dbSuggestions as $item) {
            $path = (string) $item->image_path;
            if ($path === '' || isset($seenPaths[$path])) {
                continue;
            }

            $url = str_starts_with($path, 'http')
                ? $path
                : asset($path);

            $results[] = [
                'path' => $path,
                'url' => $url,
                'name' => $item->name,
                'source' => 'products',
            ];
            $seenPaths[$path] = true;
        }

        // 2) Also search by raw file names in project folders.
        $searchDirectories = [
            public_path('storage/products'),
            public_path('assets'),
        ];

        foreach ($searchDirectories as $directory) {
            if (! File::exists($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                $extension = strtolower($file->getExtension());
                if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    continue;
                }

                $nameNormalized = Str::of(pathinfo($file->getFilename(), PATHINFO_FILENAME))
                    ->lower()
                    ->replace([' ', '-', '_'], '')
                    ->value();

                if (! str_contains($nameNormalized, $normalizedQuery)) {
                    continue;
                }

                $relativePath = Str::of($file->getPathname())
                    ->replace('\\', '/')
                    ->after(str_replace('\\', '/', public_path()) . '/')
                    ->value();

                if (isset($seenPaths[$relativePath])) {
                    continue;
                }

                $results[] = [
                    'path' => $relativePath,
                    'url' => asset($relativePath),
                    'name' => $file->getFilename(),
                    'source' => 'files',
                ];
                $seenPaths[$relativePath] = true;

                if (count($results) >= 20) {
                    break 2;
                }
            }
        }

        return response()->json($results);
    }

    public function setTableCount(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'max_table_number' => ['required', 'integer', 'min:1', 'max:300'],
        ]);

        $maxTableNumber = (int) $validated['max_table_number'];

        DB::transaction(function () use ($maxTableNumber): void {
            $existing = DB::table('store_tables')
                ->pluck('table_number')
                ->map(fn ($value) => (int) $value)
                ->all();

            $existingMap = array_fill_keys($existing, true);
            $rowsToInsert = [];
            for ($number = 1; $number <= $maxTableNumber; $number++) {
                if (! isset($existingMap[$number])) {
                    $rowsToInsert[] = [
                        'table_number' => $number,
                        'is_active' => true,
                        'status' => 'available',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if ($rowsToInsert !== []) {
                DB::table('store_tables')->insert($rowsToInsert);
            }
        });

        return back()->with('status', 'Подредбата на масите е обновена.');
    }

    public function toggleTable(Request $request, int $tableId): RedirectResponse
    {
        $table = DB::table('store_tables')->where('id', $tableId)->first();
        if ($table === null) {
            return back()->with('error', 'Масата не е намерена.');
        }

        DB::table('store_tables')
            ->where('id', $tableId)
            ->update([
                'is_active' => ! (bool) $table->is_active,
                'updated_at' => now(),
            ]);

        return back()->with('status', "Статусът на маса {$table->table_number} е обновен.");
    }

    public function setTableStatus(Request $request, int $tableId): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:available,occupied,inactive'],
        ]);

        $table = DB::table('store_tables')->where('id', $tableId)->first();
        if ($table === null) {
            return back()->with('error', 'Масата не е намерена.');
        }

        $hasOpenOrder = DB::table('orders')
            ->where('store_table_id', $tableId)
            ->where('status', 'open')
            ->exists();
        if ($hasOpenOrder) {
            return back()->with('error', "Не може да смените статуса на маса {$table->table_number}, докато има активна поръчка.");
        }

        $status = $validated['status'];

        DB::table('store_tables')
            ->where('id', $tableId)
            ->update([
                'status' => $status,
                'is_active' => $status !== 'inactive',
                'updated_at' => now(),
            ]);

        $statusText = match ($status) {
            'available' => 'свободна',
            'reserved' => 'резервирана',
            'occupied' => 'заета',
            'inactive' => 'неактивна',
            default => $status,
        };

        return back()->with('status', "Маса {$table->table_number} е зададена като {$statusText}.");
    }

    public function createReservation(Request $request, int $tableId): RedirectResponse
    {
        $validated = $request->validate([
            'reserved_from' => ['required', 'date'],
            'reserved_to' => ['required', 'date', 'after:reserved_from'],
            'reserved_people' => ['required', 'integer', 'min:1', 'max:30'],
            'customer_name' => ['nullable', 'string', 'max:255'],
        ]);

        $table = DB::table('store_tables')->where('id', $tableId)->first();
        if ($table === null) {
            return back()->with('error', 'Масата не е намерена.');
        }

        $reservedFrom = Carbon::parse((string) $validated['reserved_from']);
        $reservedTo = Carbon::parse((string) $validated['reserved_to']);

        $hasOverlap = DB::table('table_reservations')
            ->where('store_table_id', $tableId)
            ->where('status', 'active')
            ->where('starts_at', '<', $reservedTo)
            ->where('ends_at', '>', $reservedFrom)
            ->exists();

        if ($hasOverlap) {
            return back()->with('error', 'Има друга резервация в същия часови диапазон.');
        }

        DB::table('table_reservations')->insert([
            'store_table_id' => $tableId,
            'people_count' => (int) $validated['reserved_people'],
            'starts_at' => $reservedFrom,
            'ends_at' => $reservedTo,
            'customer_name' => $validated['customer_name'] ?? null,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('store_tables')
            ->where('id', $tableId)
            ->update([
                'status' => 'reserved',
                'is_active' => true,
                'updated_at' => now(),
            ]);

        return back()->with('status', "Добавена е резервация за маса {$table->table_number}.");
    }

    public function addOrderItem(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store_table_id' => ['required', 'integer', 'exists:store_tables,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        DB::transaction(function () use ($validated, $request): void {
            $table = DB::table('store_tables')
                ->where('id', $validated['store_table_id'])
                ->lockForUpdate()
                ->first();

            if ($table === null || ! $table->is_active) {
                throw ValidationException::withMessages([
                    'store_table_id' => 'Избраната маса е неактивна.',
                ]);
            }

            $product = DB::table('products')
                ->where('id', $validated['product_id'])
                ->lockForUpdate()
                ->first();

            if ($product === null || ! $product->is_active) {
                throw ValidationException::withMessages([
                    'product_id' => 'Избраният продукт е неактивен.',
                ]);
            }

            if ((int) $product->stock_quantity < (int) $validated['quantity']) {
                throw ValidationException::withMessages([
                    'quantity' => 'Недостатъчна наличност за този продукт.',
                ]);
            }

            $this->appendOrderItem(
                (int) $validated['store_table_id'],
                (int) $validated['product_id'],
                (int) $validated['quantity'],
                $request->user()?->id
            );
        });

        return back()->with('status', 'Артикулът е добавен към поръчката на масата.');
    }

    public function addHookahRecipe(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'hookah_product_id' => ['required', 'integer', 'exists:products,id'],
            'tobacco_product_id' => ['required', 'integer', 'exists:products,id'],
            'grams_per_serving' => ['required', 'numeric', 'min:1', 'max:100'],
        ]);

        $hookah = DB::table('products')->where('id', $validated['hookah_product_id'])->first();
        $tobacco = DB::table('products')->where('id', $validated['tobacco_product_id'])->first();

        if ($hookah === null || $hookah->category !== 'hookah') {
            return back()->with('error', 'Избраният продукт за наргиле не е от категория "Наргиле".');
        }

        if ($tobacco === null || $tobacco->category !== 'tobacco') {
            return back()->with('error', 'Избраният тютюн не е от категория "Тютюн".');
        }

        $existingRecipe = DB::table('hookah_recipes')
            ->where('hookah_product_id', (int) $validated['hookah_product_id'])
            ->where('tobacco_product_id', (int) $validated['tobacco_product_id'])
            ->first();

        if ($existingRecipe !== null) {
            DB::table('hookah_recipes')
                ->where('id', $existingRecipe->id)
                ->update([
                    'grams_per_serving' => (float) $validated['grams_per_serving'],
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('hookah_recipes')->insert([
                'hookah_product_id' => (int) $validated['hookah_product_id'],
                'tobacco_product_id' => (int) $validated['tobacco_product_id'],
                'grams_per_serving' => (float) $validated['grams_per_serving'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('status', 'Рецептата за наргиле е запазена.');
    }

    public function aiAddOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store_table_id' => ['required', 'integer', 'exists:store_tables,id'],
            'order_text' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $userId = (int) ($request->user()?->id ?? 0);
        $tableId = (int) $validated['store_table_id'];
        $promptText = (string) $validated['order_text'];
        $request->session()->put($this->getAiPromptDraftSessionKey($userId, $tableId), $promptText);
        $request->session()->put($this->getAiPromptLastSessionKey($userId, $tableId), $promptText);

        $products = DB::table('products')
            ->join('brands', 'products.brand_id', '=', 'brands.id')
            ->where('products.is_active', true)
            ->select(
                'products.id',
                'products.name',
                'products.category',
                'products.flavor',
                'brands.name as brand_name'
            )
            ->get()
            ->map(function ($product) {
                $search = trim($product->brand_name . ' ' . $product->name . ' ' . ($product->flavor ?? ''));
                $product->search_text = $this->normalizeOrderToken($search);
                return $product;
            });

        $items = $this->parseAiOrderText((string) $validated['order_text']);
        $added = 0;
        $missed = [];

        DB::transaction(function () use ($items, $products, $validated, $request, &$added, &$missed): void {
            $planned = [];

            foreach ($items as $item) {
                $term = (string) ($item['term'] ?? '');
                $quantity = (int) ($item['quantity'] ?? 1);
                if ($term === '' || $quantity < 1) {
                    continue;
                }

                $termLower = $this->normalizeOrderToken($term);
                $match = $this->findBestProductMatch($termLower, $products);

                if ($match === null) {
                    if ($this->isHookahKeyword($termLower)) {
                        $match = $products
                            ->first(fn ($product) => $product->category === 'hookah');
                    }
                }

                if ($match === null) {
                    $missed[] = $term;
                    continue;
                }

                $metaNote = null;
                if (!empty($item['is_hookah'])) {
                    $hookahType = trim((string) ($item['hookah_type'] ?? ''));
                    $hookahFlavors = trim((string) ($item['hookah_flavors'] ?? ''));

                    $hookahProducts = $products->where('category', 'hookah');
                    if ($hookahType !== '') {
                        $hookahTypeNormalized = $this->normalizeHookahTypeToken($hookahType);
                        $typedHookahMatch = $hookahProducts
                            ->filter(function ($product) use ($hookahTypeNormalized): bool {
                                $productName = $this->normalizeOrderToken((string) $product->name);
                                $brandName = $this->normalizeOrderToken((string) $product->brand_name);
                                return str_contains($productName, $hookahTypeNormalized)
                                    || str_contains($hookahTypeNormalized, $productName)
                                    || str_contains($brandName, $hookahTypeNormalized)
                                    || str_contains($hookahTypeNormalized, $brandName);
                            })
                            ->sortByDesc(fn ($product) => mb_strlen((string) $product->name))
                            ->first();
                        if ($typedHookahMatch !== null) {
                            $match = $typedHookahMatch;
                            if ($hookahFlavors === '') {
                                $matchedBrandToken = $this->normalizeHookahTypeToken((string) $typedHookahMatch->brand_name);
                                $matchedNameToken = $this->normalizeHookahTypeToken((string) $typedHookahMatch->name);
                                $remainingFlavor = str_replace([$matchedBrandToken, $matchedNameToken], ' ', $hookahTypeNormalized);
                                $remainingFlavor = $this->normalizeOrderToken($remainingFlavor);
                                if ($remainingFlavor !== '' && $remainingFlavor !== 'наргиле') {
                                    $hookahFlavors = $remainingFlavor;
                                }
                            }
                        } elseif ($match === null || $match->category !== 'hookah') {
                            $looksLikeFlavor = DB::table('products')
                                ->where('category', 'tobacco')
                                ->where('is_active', true)
                                ->where(function ($q) use ($hookahTypeNormalized): void {
                                    $q->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($hookahTypeNormalized) . '%'])
                                        ->orWhereRaw('LOWER(COALESCE(flavor, "")) LIKE ?', ['%' . mb_strtolower($hookahTypeNormalized) . '%']);
                                })
                                ->exists();

                            if (! $looksLikeFlavor) {
                                $missed[] = "тип наргиле: {$hookahType}";
                                continue;
                            }

                            if ($hookahFlavors === '') {
                                $hookahFlavors = $this->normalizeOrderToken($hookahType);
                            }
                            $fallbackHookah = $hookahProducts->first();
                            if ($fallbackHookah !== null) {
                                $match = $fallbackHookah;
                            }
                        }
                    } elseif ($match->category !== 'hookah') {
                        $fallbackHookah = $hookahProducts->first();
                        if ($fallbackHookah !== null) {
                            $match = $fallbackHookah;
                        }
                    }

                    if ($hookahFlavors !== '') {
                        $resolvedFlavors = $this->resolveHookahFlavors($hookahFlavors, $products);
                        $hookahFlavors = $resolvedFlavors['value'];
                        if ($resolvedFlavors['unknown'] !== []) {
                            $missed[] = 'вкус: ' . implode(' + ', $resolvedFlavors['unknown']);
                            continue;
                        }
                    }

                    if ($match === null || $match->category !== 'hookah') {
                        $missed[] = $term;
                        continue;
                    }

                    if ($hookahFlavors !== '') {
                        $metaNote = mb_strtolower($hookahFlavors);
                    }
                }

                $productId = (int) $match->id;
                $plannedKey = $productId . '|' . ($metaNote ?? '');
                if (! isset($planned[$plannedKey])) {
                    $planned[$plannedKey] = [
                        'product_id' => $productId,
                        'product_name' => (string) $match->name,
                        'quantity' => 0,
                        'meta_note' => $metaNote,
                    ];
                }
                $planned[$plannedKey]['quantity'] += $quantity;
            }

            if ($missed !== []) {
                throw ValidationException::withMessages([
                    'order_text' => 'Неразпознати продукти: ' . implode('; ', array_slice($missed, 0, 6)),
                ]);
            }

            if ($planned !== []) {
                $insufficient = [];
                $validationByProduct = [];
                foreach ($planned as $productPlan) {
                    $productKey = (int) $productPlan['product_id'];
                    if (! isset($validationByProduct[$productKey])) {
                        $validationByProduct[$productKey] = [
                            'product_id' => $productKey,
                            'product_name' => (string) $productPlan['product_name'],
                            'quantity' => 0,
                        ];
                    }
                    $validationByProduct[$productKey]['quantity'] += (int) $productPlan['quantity'];
                }

                foreach ($validationByProduct as $productPlan) {
                    $product = DB::table('products')
                        ->where('id', $productPlan['product_id'])
                        ->lockForUpdate()
                        ->first();

                    if ($product === null || ! $product->is_active) {
                        $insufficient[] = "{$productPlan['product_name']} (неактивен продукт)";
                        continue;
                    }

                    $available = (int) $product->stock_quantity;
                    $requested = (int) $productPlan['quantity'];
                    if ($available < $requested) {
                        $unit = trim((string) ($product->unit ?? 'бр'));
                        if ($unit === '') {
                            $unit = 'бр';
                        }
                        $insufficient[] = "{$product->name} (заявени {$requested} {$unit}, налични {$available} {$unit})";
                    }
                }

                if ($insufficient !== []) {
                    throw ValidationException::withMessages([
                        'order_text' => 'Недостатъчна наличност: ' . implode('; ', $insufficient),
                    ]);
                }

                foreach ($planned as $productPlan) {
                    $this->appendOrderItem(
                        (int) $validated['store_table_id'],
                        (int) $productPlan['product_id'],
                        (int) $productPlan['quantity'],
                        $request->user()?->id,
                        $productPlan['meta_note']
                    );
                    $added++;
                }
            }
        });

        if ($added === 0) {
            return back()
                ->withInput()
                ->with('error', 'Не успях да разпозная продукти от текста.');
        }

        $message = "AI добави {$added} артикула към поръчката.";
        if ($missed !== []) {
            $message .= ' Неразпознати: ' . implode('; ', array_slice($missed, 0, 3));
        }

        $request->session()->forget($this->getAiPromptDraftSessionKey($userId, $tableId));

        return back()->with('status', $message);
    }

    private function getAiPromptDraftSessionKey(int $userId, int $tableId): string
    {
        return "ai_prompt_draft.user_{$userId}.table_{$tableId}";
    }

    private function getAiPromptLastSessionKey(int $userId, int $tableId): string
    {
        return "ai_prompt_last.user_{$userId}.table_{$tableId}";
    }

    private function appendOrderItem(int $storeTableId, int $productId, int $quantity, ?int $userId, ?string $metaNote = null): void
    {
        $order = DB::table('orders')
            ->where('store_table_id', $storeTableId)
            ->where('status', 'open')
            ->lockForUpdate()
            ->latest('id')
            ->first();

        if ($order === null) {
            $orderId = DB::table('orders')->insertGetId([
                'store_table_id' => $storeTableId,
                'user_id' => $userId,
                'status' => 'open',
                'total_amount' => 0,
                'opened_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $orderId = (int) $order->id;
        }

        $product = DB::table('products')->where('id', $productId)->lockForUpdate()->first();
        if ($product === null || ! $product->is_active) {
            throw ValidationException::withMessages([
                'product_id' => 'Избраният продукт е неактивен.',
            ]);
        }

        if ((int) $product->stock_quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => "Недостатъчна наличност за {$product->name}.",
            ]);
        }

        if ($product->category === 'hookah') {
            $this->consumeHookahTobacco((int) $product->id, $quantity);
        }

        $unitPrice = (float) $product->price;
        $lineTotal = $unitPrice * $quantity;

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'meta_note' => $metaNote,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('products')
            ->where('id', $productId)
            ->update([
                'stock_quantity' => DB::raw("stock_quantity - {$quantity}"),
                'updated_at' => now(),
            ]);

        $newTotal = (float) DB::table('order_items')
            ->where('order_id', $orderId)
            ->sum('line_total');

        DB::table('orders')
            ->where('id', $orderId)
            ->update([
                'total_amount' => $newTotal,
                'updated_at' => now(),
            ]);
    }

    /**
     * @return array<int, array{term: string, quantity: int, is_hookah?: bool, hookah_type?: string, hookah_flavors?: string}>
     */
    private function parseAiOrderText(string $orderText): array
    {
        $preparedOrderText = preg_replace(
            '/\s+\bи\b\s+(?=(?:\d+|[[:alpha:]\p{L}]+\s+(?:бр|броя|брой)|наргиле|наргилета|hookah|shisha))/ui',
            ', ',
            $orderText
        );
        $segments = $this->splitOrderSegments((string) $preparedOrderText);
        $expandedSegments = [];
        foreach ($segments as $segment) {
            foreach ($this->expandCompositeOrderSegment((string) $segment) as $part) {
                $cleanPart = trim((string) $part);
                if ($cleanPart !== '') {
                    $expandedSegments[] = $cleanPart;
                }
            }
        }
        $segments = $expandedSegments;
        $items = [];

        foreach ($segments as $segment) {
            $raw = trim((string) $segment);
            if ($raw === '') {
                continue;
            }

            $quantity = 1;
            $term = $raw;

            if (preg_match('/^\s*(\d+)\s*(?:x|х|бр|броя|брой|pcs|pc)?\s+(.+)$/ui', $raw, $matches) === 1) {
                $quantity = max(1, (int) $matches[1]);
                $term = trim((string) $matches[2]);
            } elseif (preg_match('/^(.+?)\s+(\d+)\s*(?:x|х|бр|броя|брой|pcs|pc)\s*(.*)$/ui', $raw, $matches) === 1) {
                $quantity = max(1, (int) $matches[2]);
                $term = trim(trim((string) $matches[1]) . ' ' . trim((string) $matches[3]));
            } elseif (preg_match('/^\s*([[:alpha:]\p{L}]+)\s*(?:x|х|бр|броя|брой|pcs|pc)?\s+(.+)$/ui', $raw, $matches) === 1) {
                $wordNumber = $this->parseQuantityWord((string) $matches[1]);
                if ($wordNumber !== null) {
                    $quantity = $wordNumber;
                    $term = trim((string) $matches[2]);
                }
            } elseif (preg_match('/^(.+?)\s+(\d+)\s*(?:x|х|бр|броя|брой|pcs|pc)?\s*$/ui', $raw, $matches) === 1) {
                $term = trim((string) $matches[1]);
                $quantity = max(1, (int) $matches[2]);
            } elseif (preg_match('/^(.+?)\s*[xх]\s*(\d+)$/ui', $raw, $matches) === 1) {
                $term = trim((string) $matches[1]);
                $quantity = max(1, (int) $matches[2]);
            } elseif (preg_match('/^(.+?)\s+([[:alpha:]\p{L}]+)\s*(?:x|х|бр|броя|брой|pcs|pc)?\s*$/ui', $raw, $matches) === 1) {
                $wordNumber = $this->parseQuantityWord((string) $matches[2]);
                if ($wordNumber !== null) {
                    $term = trim((string) $matches[1]);
                    $quantity = $wordNumber;
                }
            } elseif (preg_match('/^(.+?)\s+([[:alpha:]\p{L}]+)\s+(?:бр|броя|брой|pcs|pc)\s*$/ui', $raw, $matches) === 1) {
                $wordNumber = $this->parseQuantityWord((string) $matches[2]);
                if ($wordNumber !== null) {
                    $term = trim((string) $matches[1]);
                    $quantity = $wordNumber;
                }
            }

            $isHookahSegment = $this->isHookahKeyword($this->normalizeOrderToken($term));
            $hookahFlavors = '';
            if ($isHookahSegment && preg_match('/\(([^)]+)\)/u', $term, $flavorMatches) === 1) {
                $flavorParts = preg_split('/\s*\+\s*|\s*,\s*|\s+\bи\b\s+|\s+\band\b\s+/ui', (string) $flavorMatches[1]) ?: [];
                $normalizedFlavors = [];
                foreach ($flavorParts as $flavorPart) {
                    $flavorNormalized = $this->normalizeOrderToken((string) $flavorPart);
                    if ($flavorNormalized !== '') {
                        $normalizedFlavors[] = $flavorNormalized;
                    }
                }
                $hookahFlavors = implode(' + ', $normalizedFlavors);
            }

            $termWithoutMeta = (string) preg_replace('/\([^)]*\)/u', ' ', $term);
            $termWithoutMeta = (string) preg_replace('/\b(чашка|чаша|глава|глави|head|bowl)\b/ui', ' ', $termWithoutMeta);
            $hookahType = '';
            if ($isHookahSegment) {
                $hookahTypeRaw = (string) preg_replace('/\b(наргиле|hookah|shisha|с|със|with)\b/ui', ' ', $termWithoutMeta);
                $hookahType = $this->normalizeOrderToken($hookahTypeRaw);
                if ($hookahFlavors === '' && preg_match('/\b(?:с|със|with)\b\s+(.+)$/ui', $term, $flavorFromTextMatch) === 1) {
                    $flavorParts = preg_split('/\s*\+\s*|\s*,\s*|\s+\bи\b\s+|\s+\band\b\s+/ui', (string) $flavorFromTextMatch[1]) ?: [];
                    $normalizedFlavors = [];
                    foreach ($flavorParts as $flavorPart) {
                        $flavorNormalized = $this->normalizeOrderToken((string) $flavorPart);
                        if ($flavorNormalized !== '' && $flavorNormalized !== $hookahType && $flavorNormalized !== 'наргиле') {
                            $normalizedFlavors[] = $flavorNormalized;
                        }
                    }
                    $hookahFlavors = implode(' + ', array_values(array_unique($normalizedFlavors)));
                }
            }
            $normalized = $this->normalizeOrderToken($termWithoutMeta);
            if ($normalized === '') {
                continue;
            }

            $entry = [
                'term' => $normalized,
                'quantity' => $quantity,
            ];
            if ($isHookahSegment) {
                $entry['is_hookah'] = true;
                $entry['hookah_type'] = $hookahType;
                $entry['hookah_flavors'] = $hookahFlavors;
            }
            $items[] = $entry;
        }

        return $items;
    }

    /**
     * @return array<int, string>
     */
    private function expandCompositeOrderSegment(string $segment): array
    {
        $clean = trim($segment);
        if ($clean === '') {
            return [];
        }

        // Colloquial BG usage: "две коли с една фанта" => two distinct items.
        $parts = preg_split(
            '/\s+\bс\b\s+(?=(?:\d+|[[:alpha:]\p{L}]+)\s*(?:бр|броя|брой)?\s+)/ui',
            $clean
        ) ?: [];

        $result = [];
        foreach ($parts as $part) {
            $value = trim((string) $part);
            if ($value !== '') {
                $result[] = $value;
            }
        }

        return $result === [] ? [$clean] : $result;
    }

    private function parseQuantityWord(string $value): ?int
    {
        $normalized = $this->normalizeOrderToken($value);
        $map = [
            '1' => 1,
            'един' => 1,
            'една' => 1,
            'едно' => 1,
            'one' => 1,
            '2' => 2,
            'два' => 2,
            'две' => 2,
            'two' => 2,
            '3' => 3,
            'три' => 3,
            'three' => 3,
            '4' => 4,
            'четири' => 4,
            'four' => 4,
            '5' => 5,
            'пет' => 5,
            'five' => 5,
            '6' => 6,
            'шест' => 6,
            'six' => 6,
            '7' => 7,
            'седем' => 7,
            'seven' => 7,
            '8' => 8,
            'осем' => 8,
            'eight' => 8,
            '9' => 9,
            'девет' => 9,
            'nine' => 9,
            '10' => 10,
            'десет' => 10,
            'ten' => 10,
        ];

        return $map[$normalized] ?? null;
    }

    /**
     * @return array<int, string>
     */
    private function splitOrderSegments(string $text): array
    {
        $segments = [];
        $buffer = '';
        $depth = 0;

        $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($chars as $char) {
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')' && $depth > 0) {
                $depth--;
            }

            $isPlusSeparator = $char === '+' && $depth === 0;
            if ($isPlusSeparator) {
                $normalizedBuffer = $this->normalizeOrderToken($buffer);
                if ($this->isHookahKeyword($normalizedBuffer)) {
                    $buffer .= ' ' . $char . ' ';
                    continue;
                }
            }

            $isSeparator = ($char === '+' || $char === ',' || $char === ';' || $char === "\n" || $char === "\r") && $depth === 0;
            if ($isSeparator) {
                $trimmed = trim($buffer);
                if ($trimmed !== '') {
                    $segments[] = $trimmed;
                }
                $buffer = '';
                continue;
            }

            $buffer .= $char;
        }

        $trimmed = trim($buffer);
        if ($trimmed !== '') {
            $segments[] = $trimmed;
        }

        return $segments;
    }

    private function normalizeOrderToken(string $text): string
    {
        $value = mb_strtolower(trim($text));
        $value = str_replace(
            ['кока-кола', 'кока кола', 'coca cola', 'шиша', 'наргилета', 'фанта', 'мл'],
            ['coca cola', 'coca cola', 'coca cola', 'наргиле', 'наргиле', 'fanta', 'ml'],
            $value
        );
        $value = str_replace(
            ['рокетмен', 'блубери', 'блу бери'],
            ['rocketman', 'blueberry', 'blueberry'],
            $value
        );
        $value = (string) preg_replace('/\b(коли|кола|кока)\b/ui', 'coca cola', $value);
        $value = str_replace(
            [' със ', ' с ', ' with '],
            [' ', ' ', ' '],
            " {$value} "
        );
        $value = (string) preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value);
        $value = (string) preg_replace('/\b(вкус|аромат|flavor|taste)\b/ui', ' ', $value);
        $value = (string) preg_replace('/\s+/u', ' ', $value);
        return trim($value);
    }

    private function findBestProductMatch(string $termLower, $products): mixed
    {
        $direct = $products
            ->filter(function ($product) use ($termLower): bool {
                $productName = $this->normalizeOrderToken((string) $product->name);
                return str_contains($product->search_text, $termLower)
                    || str_contains($termLower, $productName)
                    || str_contains($productName, $termLower);
            })
            ->sortByDesc(fn ($product) => mb_strlen((string) $product->search_text))
            ->first();

        if ($direct !== null) {
            return $direct;
        }

        $queryTokens = $this->extractMatchTokens($termLower);
        if ($queryTokens === []) {
            return null;
        }

        $best = null;
        $bestScore = 0.0;

        foreach ($products as $product) {
            $productTokens = $this->extractMatchTokens((string) $product->search_text);
            if ($productTokens === []) {
                continue;
            }

            $common = array_intersect($queryTokens, $productTokens);
            if ($common === []) {
                continue;
            }

            $score = count($common) / max(1, count($queryTokens));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $product;
            }
        }

        return $bestScore >= 0.5 ? $best : null;
    }

    /**
     * @return array<int, string>
     */
    private function extractMatchTokens(string $text): array
    {
        $normalized = $this->normalizeOrderToken($text);
        $parts = preg_split('/\s+/u', $normalized) ?: [];
        $tokens = [];

        foreach ($parts as $part) {
            $token = trim((string) $part);
            if ($token === '') {
                continue;
            }

            if (in_array($token, ['бр', 'броя', 'брой', 'pc', 'pcs', 'x', 'х', 'и', 'and', 'с', 'със', 'with'], true)) {
                continue;
            }

            if (preg_match('/^\d+$/', $token) === 1) {
                continue;
            }

            $tokens[] = $token;
        }

        return array_values(array_unique($tokens));
    }

    private function isHookahKeyword(string $text): bool
    {
        return str_contains($text, 'наргиле')
            || str_contains($text, 'hookah')
            || str_contains($text, 'shisha');
    }

    private function normalizeHookahTypeToken(string $text): string
    {
        $value = $this->normalizeOrderToken($text);
        $value = str_replace(
            ['карма', 'aeon', 'аеон', 'маклауд', 'механика'],
            ['karm', 'aoen', 'aoen', 'maklaud', 'mechanica'],
            $value
        );
        return $value;
    }

    /**
     * @return array{value: string, unknown: array<int, string>}
     */
    private function resolveHookahFlavors(string $flavors, $products): array
    {
        $parts = preg_split('/\s*\+\s*/u', $flavors) ?: [];
        if ($parts === []) {
            return ['value' => '', 'unknown' => []];
        }

        $candidates = $products
            ->where('category', 'tobacco')
            ->map(fn ($product) => $this->normalizeOrderToken((string) $product->name))
            ->filter(fn ($name) => $name !== '')
            ->unique()
            ->values();

        if ($candidates->isEmpty()) {
            return [
                'value' => $this->normalizeOrderToken($flavors),
                'unknown' => array_values(array_filter(array_map(
                    fn ($part) => $this->normalizeOrderToken((string) $part),
                    $parts
                ))),
            ];
        }

        $corrected = [];
        $unknown = [];
        foreach ($parts as $part) {
            $token = $this->normalizeOrderToken((string) $part);
            if ($token === '') {
                continue;
            }

            $best = null;
            $bestDistance = PHP_INT_MAX;
            $tokenCompact = str_replace(' ', '', $token);

            foreach ($candidates as $candidate) {
                $candidateString = (string) $candidate;
                if ($candidateString === $token) {
                    $best = $candidateString;
                    $bestDistance = 0;
                    break;
                }

                if (str_contains($candidateString, $token) || str_contains($token, $candidateString)) {
                    $best = $candidateString;
                    $bestDistance = 0;
                    break;
                }

                $candidateCompact = str_replace(' ', '', $candidateString);
                $distance = levenshtein($tokenCompact, $candidateCompact);
                $threshold = max(1, (int) floor(strlen($candidateCompact) * 0.35));

                if ($distance <= $threshold && $distance < $bestDistance) {
                    $bestDistance = $distance;
                    $best = $candidateString;
                }
            }

            if ($best === null) {
                $unknown[] = $token;
                continue;
            }

            $corrected[] = $best;
        }

        return [
            'value' => implode(' + ', array_values(array_unique($corrected))),
            'unknown' => array_values(array_unique($unknown)),
        ];
    }

    private function consumeHookahTobacco(int $hookahProductId, int $quantity): void
    {
        $recipes = DB::table('hookah_recipes')
            ->where('hookah_product_id', $hookahProductId)
            ->lockForUpdate()
            ->get();

        foreach ($recipes as $recipe) {
            $gramsNeeded = (float) $recipe->grams_per_serving * $quantity;
            $gramsNeededInt = (int) ceil($gramsNeeded);
            if ($gramsNeededInt < 1) {
                continue;
            }

            $tobacco = DB::table('products')
                ->where('id', $recipe->tobacco_product_id)
                ->lockForUpdate()
                ->first();

            if ($tobacco === null || ! $tobacco->is_active) {
                throw ValidationException::withMessages([
                    'order_text' => 'Рецептата за наргиле съдържа неактивен тютюн.',
                ]);
            }

            if ((int) $tobacco->stock_quantity < $gramsNeededInt) {
                throw ValidationException::withMessages([
                    'order_text' => "Недостатъчен тютюн за наргиле: {$tobacco->name}.",
                ]);
            }

            DB::table('products')
                ->where('id', $tobacco->id)
                ->update([
                    'stock_quantity' => DB::raw("stock_quantity - {$gramsNeededInt}"),
                    'updated_at' => now(),
                ]);
        }
    }

    public function closeOrder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store_table_id' => ['required', 'integer', 'exists:store_tables,id'],
        ]);

        $order = DB::table('orders')
            ->where('store_table_id', $validated['store_table_id'])
            ->where('status', 'open')
            ->latest('id')
            ->first();

        if ($order === null) {
            return back()->with('error', 'Няма отворена поръчка за тази маса.');
        }

        DB::transaction(function () use ($order): void {
            $hookahItems = DB::table('order_items')
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('order_items.order_id', $order->id)
                ->where('products.category', 'hookah')
                ->select(
                    'order_items.product_id',
                    DB::raw('SUM(order_items.quantity) as total_quantity')
                )
                ->groupBy('order_items.product_id')
                ->get();

            foreach ($hookahItems as $hookahItem) {
                $qty = max(0, (int) $hookahItem->total_quantity);
                if ($qty > 0) {
                    DB::table('products')
                        ->where('id', $hookahItem->product_id)
                        ->lockForUpdate()
                        ->update([
                            'stock_quantity' => DB::raw("stock_quantity + {$qty}"),
                            'updated_at' => now(),
                        ]);
                }
            }

            DB::table('orders')
                ->where('id', $order->id)
                ->update([
                    'status' => 'paid',
                    'closed_at' => now(),
                    'updated_at' => now(),
                ]);
        });

        return back()->with('status', 'Сметката е маркирана и поръчката е затворена.');
    }
}
