<?php

namespace App\Services\Catalog;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDelivery;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductService
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, ?UploadedFile $imageFile = null): void
    {
        $brandId = (int) ($data['brand_id'] ?? 0);
        $categoryId = (int) ($data['category_id'] ?? 0);

        $brand = Brand::query()->whereKey($brandId)->first();
        if ($brand === null) {
            throw ValidationException::withMessages(['brand_id' => 'Избраният бранд не е намерен.']);
        }

        $category = Category::query()->whereKey($categoryId)->first();
        if ($category === null) {
            throw ValidationException::withMessages(['category_id' => 'Невалидна категория.']);
        }

        $productName = trim((string) ($data['name'] ?? ''));
        if ($category->isHookah()) {
            $productName = trim((string) $brand->name);
        }
        if ($productName === '') {
            throw ValidationException::withMessages(['name' => 'Името на продукта е задължително.']);
        }

        $duplicateExists = Product::query()
            ->where('brand_id', $brandId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($productName)])
            ->exists();
        if ($duplicateExists) {
            throw ValidationException::withMessages(['name' => 'Този продукт вече съществува за избрания бранд.']);
        }

        $imagePath = $data['selected_image_path'] ?? null;
        if ($imageFile instanceof UploadedFile) {
            $stored = $imageFile->store('products', 'public');
            $imagePath = 'storage/' . $stored;
        }

        $purchase = (float) ($data['purchase_price'] ?? 0);
        $saleRaw = $data['sale_price'] ?? null;
        $sale = ($saleRaw === null || $saleRaw === '')
            ? ($category->isTobacco() ? 0.0 : $purchase)
            : (float) $saleRaw;

        $unit = $category->isTobacco()
            ? 'g'
            : trim((string) ($data['unit'] ?? 'бр'));

        if ($category->isTobacco()) {
            $purchase = 0.0;
            $sale = 0.0;
        }

        $stockQty = $category->isTobacco()
            ? 0
            : (int) ($data['stock_quantity'] ?? 0);

        // Hookah products keep legacy behavior: stock is reserved immediately and restored on close,
        // and tobacco is consumed by recipe immediately. Do not allow configuring writeoff on hookah.
        $writeoffMode = $category->isHookah() ? 'recipe' : ($data['writeoff_mode'] ?? 'manual');

        try {
            Product::query()->create([
                'brand_id' => $brandId,
                'category_id' => $categoryId,
                'name' => $productName,
                'flavor' => null,
                'price' => $sale,
                'purchase_price' => $purchase,
                'stock_quantity' => $stockQty,
                'unit' => $unit,
                'image_path' => $imagePath,
                'is_active' => array_key_exists('is_active', $data) ? (bool) ($data['is_active'] ?? false) : true,
                'writeoff_mode' => in_array($writeoffMode, ['manual', 'recipe', 'auto'], true)
                    ? (string) $writeoffMode
                    : 'manual',
            ]);
        } catch (QueryException) {
            throw ValidationException::withMessages(['name' => 'Неуспешно добавяне на продукта. Възможно е вече да съществува със същия бранд.']);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $productId, array $data, ?UploadedFile $imageFile = null): void
    {
        $product = Product::query()->whereKey($productId)->first();
        if ($product === null) {
            throw ValidationException::withMessages(['product' => 'Продуктът не е намерен.']);
        }

        $brandId = (int) ($data['brand_id'] ?? $product->brand_id);
        $categoryId = (int) ($data['category_id'] ?? $product->category_id);

        $brand = Brand::query()->whereKey($brandId)->first();
        if ($brand === null) {
            throw ValidationException::withMessages(['brand_id' => 'Избраният бранд не е намерен.']);
        }

        $category = Category::query()->whereKey($categoryId)->first();
        if ($category === null) {
            throw ValidationException::withMessages(['category_id' => 'Невалидна категория.']);
        }

        $productName = trim((string) ($data['name'] ?? $product->name));
        if ($category->isHookah()) {
            $productName = trim((string) $brand->name);
        }
        if ($productName === '') {
            throw ValidationException::withMessages(['name' => 'Името на продукта е задължително.']);
        }

        $duplicateExists = Product::query()
            ->where('id', '!=', $productId)
            ->where('brand_id', $brandId)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($productName)])
            ->exists();
        if ($duplicateExists) {
            throw ValidationException::withMessages(['name' => 'Вече има друг продукт със същото име за този бранд.']);
        }

        $imagePath = $data['selected_image_path'] ?? $product->image_path;
        if ($imageFile instanceof UploadedFile) {
            $stored = $imageFile->store('products', 'public');
            $imagePath = 'storage/' . $stored;
        }

        $purchase = (float) ($data['purchase_price'] ?? ($product->purchase_price ?? 0));
        $saleRaw = $data['sale_price'] ?? null;
        $sale = ($saleRaw === null || $saleRaw === '') ? (float) ($product->price ?? 0) : (float) $saleRaw;
        $unit = $category->isTobacco()
            ? 'g'
            : trim((string) ($data['unit'] ?? ($product->unit ?? 'бр')));

        if ($category->isTobacco()) {
            $purchase = 0.0;
            $sale = 0.0;
        }

        if (! $category->isTobacco()) {
            DB::table('tobacco_pack_inventory')->where('product_id', $productId)->delete();
        }

        if ($category->isTobacco()) {
            $finalStock = $this->sumTobaccoInventoryGrams($productId);
        } else {
            $baseStock = (int) ($data['stock_quantity'] ?? $product->stock_quantity ?? 0);
            $restockAdd = (int) ($data['restock_add'] ?? 0);
            $finalStock = $baseStock + $restockAdd;
        }

        $writeoffMode = $category->isHookah()
            ? 'recipe'
            : ($data['writeoff_mode'] ?? $product->writeoff_mode ?? 'manual');

        try {
            $product->update([
                'brand_id' => $brandId,
                'category_id' => $categoryId,
                'name' => $productName,
                'flavor' => null,
                'price' => $sale,
                'purchase_price' => $purchase,
                'stock_quantity' => $finalStock,
                'unit' => $unit,
                'image_path' => $imagePath,
                'is_active' => (bool) ($data['is_active'] ?? false),
                'writeoff_mode' => in_array($writeoffMode, ['manual', 'recipe', 'auto'], true)
                    ? (string) $writeoffMode
                    : 'manual',
            ]);
        } catch (QueryException) {
            throw ValidationException::withMessages(['product' => 'Неуспешна редакция на продукта. Проверете дали няма дублиране.']);
        }
    }

    public function delete(int $productId): void
    {
        $product = Product::query()->whereKey($productId)->first();
        if ($product === null) {
            throw ValidationException::withMessages(['product' => 'Продуктът не е намерен.']);
        }

        $product->delete();
    }

    /**
     * @param array<string, mixed> $data
     */
    public function storeTobaccoPackPurchase(int $productId, array $data): void
    {
        $product = Product::query()->with('category')->whereKey($productId)->first();
        if ($product === null || ! $product->category?->isTobacco()) {
            throw ValidationException::withMessages(['product' => 'Зареждания се записват само за продукти от категория „Тютюн“.']);
        }

        $boxes = (int) ($data['boxes_count'] ?? 0);
        $packGrams = (int) ($data['pack_grams'] ?? 0);
        if ($boxes < 1 || $packGrams < 1) {
            throw ValidationException::withMessages(['boxes_count' => 'Невалидни данни за зареждане.']);
        }

        DB::transaction(function () use ($productId, $data, $boxes, $packGrams): void {
            DB::table('tobacco_pack_purchases')->insert([
                'product_id' => $productId,
                'restocked_at' => $data['restocked_at'],
                'pack_grams' => $packGrams,
                'boxes_count' => $boxes,
                'purchase_price_per_box' => (float) ($data['purchase_price_per_box'] ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->adjustTobaccoInventoryBoxes($productId, $packGrams, $boxes);
            $this->recalculateTobaccoProductStockFromInventory($productId);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateTobaccoPackPurchase(int $productId, int $purchaseId, array $data): void
    {
        $product = Product::query()->with('category')->whereKey($productId)->first();
        if ($product === null || ! $product->category?->isTobacco()) {
            throw ValidationException::withMessages(['product' => 'Невалиден продукт.']);
        }

        $existing = DB::table('tobacco_pack_purchases')
            ->where('id', $purchaseId)
            ->where('product_id', $productId)
            ->first();
        if ($existing === null) {
            throw ValidationException::withMessages(['purchase' => 'Записът за зареждане не е намерен.']);
        }

        $oldPack = (int) $existing->pack_grams;
        $oldBoxes = (int) $existing->boxes_count;
        $newPack = (int) ($data['pack_grams'] ?? 0);
        $newBoxes = (int) ($data['boxes_count'] ?? 0);
        if ($newPack < 1 || $newBoxes < 1) {
            throw ValidationException::withMessages(['pack_grams' => 'Невалидни данни за зареждане.']);
        }

        DB::transaction(function () use ($productId, $purchaseId, $data, $oldPack, $oldBoxes, $newPack, $newBoxes): void {
            $this->adjustTobaccoInventoryBoxes($productId, $oldPack, -$oldBoxes);
            $this->adjustTobaccoInventoryBoxes($productId, $newPack, $newBoxes);

            DB::table('tobacco_pack_purchases')
                ->where('id', $purchaseId)
                ->where('product_id', $productId)
                ->update([
                    'restocked_at' => $data['restocked_at'],
                    'pack_grams' => $newPack,
                    'boxes_count' => $newBoxes,
                    'purchase_price_per_box' => (float) ($data['purchase_price_per_box'] ?? 0),
                    'updated_at' => now(),
                ]);

            $this->recalculateTobaccoProductStockFromInventory($productId);
        });
    }

    public function deleteTobaccoPackPurchase(int $productId, int $purchaseId): void
    {
        $product = Product::query()->with('category')->whereKey($productId)->first();
        if ($product === null || ! $product->category?->isTobacco()) {
            throw ValidationException::withMessages(['product' => 'Невалиден продукт.']);
        }

        $row = DB::table('tobacco_pack_purchases')
            ->where('id', $purchaseId)
            ->where('product_id', $productId)
            ->first();
        if ($row === null) {
            throw ValidationException::withMessages(['purchase' => 'Записът за зареждане не е намерен.']);
        }

        DB::transaction(function () use ($productId, $purchaseId, $row): void {
            $this->adjustTobaccoInventoryBoxes($productId, (int) $row->pack_grams, -(int) $row->boxes_count);
            DB::table('tobacco_pack_purchases')
                ->where('id', $purchaseId)
                ->where('product_id', $productId)
                ->delete();
            $this->recalculateTobaccoProductStockFromInventory($productId);
        });
    }

    public function updateTobaccoPackInventory(int $productId, int $inventoryId, int $boxesOnHand): void
    {
        $product = Product::query()->with('category')->whereKey($productId)->first();
        if ($product === null || ! $product->category?->isTobacco()) {
            throw ValidationException::withMessages(['product' => 'Невалиден продукт.']);
        }

        $updated = DB::table('tobacco_pack_inventory')
            ->where('id', $inventoryId)
            ->where('product_id', $productId)
            ->update([
                'boxes_on_hand' => max(0, $boxesOnHand),
                'updated_at' => now(),
            ]);
        if ($updated === 0) {
            throw ValidationException::withMessages(['inventory' => 'Редът за наличност не е намерен.']);
        }

        $this->recalculateTobaccoProductStockFromInventory($productId);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function storeGenericDelivery(int $productId, array $data): void
    {
        $product = Product::query()->with('category')->whereKey($productId)->lockForUpdate()->first();
        if ($product === null) {
            throw ValidationException::withMessages(['product' => 'Невалиден продукт.']);
        }
        if ($product->category?->isTobacco()) {
            throw ValidationException::withMessages(['product' => 'За тютюн използвайте зарежданията по разфасовки.']);
        }

        $deliveredAt = (string) ($data['delivered_at'] ?? '');
        $qty = (int) ($data['quantity'] ?? 0);
        if ($deliveredAt === '' || $qty < 1) {
            throw ValidationException::withMessages(['quantity' => 'Невалидни данни за зареждане.']);
        }

        $unitCost = $data['unit_cost'] ?? null;
        $unitCost = ($unitCost === null || $unitCost === '') ? null : (float) $unitCost;
        $note = trim((string) ($data['note'] ?? ''));
        $note = $note === '' ? null : mb_substr($note, 0, 255);

        DB::transaction(function () use ($product, $deliveredAt, $qty, $unitCost, $note): void {
            ProductDelivery::query()->create([
                'product_id' => (int) $product->id,
                'delivered_at' => $deliveredAt,
                'quantity' => $qty,
                'unit_cost' => $unitCost,
                'note' => $note,
            ]);

            $product->update([
                'stock_quantity' => DB::raw('stock_quantity + ' . $qty),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * @param array<string, mixed> $data
     */
    public function updateGenericDelivery(int $productId, int $deliveryId, array $data): void
    {
        $product = Product::query()->with('category')->whereKey($productId)->lockForUpdate()->first();
        if ($product === null) {
            throw ValidationException::withMessages(['product' => 'Невалиден продукт.']);
        }
        if ($product->category?->isTobacco()) {
            throw ValidationException::withMessages(['product' => 'За тютюн използвайте зарежданията по разфасовки.']);
        }

        $delivery = ProductDelivery::query()
            ->whereKey($deliveryId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();
        if ($delivery === null) {
            throw ValidationException::withMessages(['delivery' => 'Зареждането не е намерено.']);
        }

        $deliveredAt = (string) ($data['delivered_at'] ?? $delivery->delivered_at?->format('Y-m-d'));
        $newQty = (int) ($data['quantity'] ?? 0);
        if ($deliveredAt === '' || $newQty < 1) {
            throw ValidationException::withMessages(['quantity' => 'Невалидни данни за зареждане.']);
        }

        $unitCost = $data['unit_cost'] ?? $delivery->unit_cost;
        $unitCost = ($unitCost === null || $unitCost === '') ? null : (float) $unitCost;
        $note = trim((string) ($data['note'] ?? ($delivery->note ?? '')));
        $note = $note === '' ? null : mb_substr($note, 0, 255);

        $oldQty = (int) $delivery->quantity;
        $delta = $newQty - $oldQty;

        DB::transaction(function () use ($product, $delivery, $deliveredAt, $newQty, $unitCost, $note, $delta): void {
            $delivery->update([
                'delivered_at' => $deliveredAt,
                'quantity' => $newQty,
                'unit_cost' => $unitCost,
                'note' => $note,
            ]);

            if ($delta !== 0) {
                $product->update([
                    'stock_quantity' => DB::raw('stock_quantity + ' . $delta),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    public function deleteGenericDelivery(int $productId, int $deliveryId): void
    {
        $product = Product::query()->with('category')->whereKey($productId)->lockForUpdate()->first();
        if ($product === null) {
            throw ValidationException::withMessages(['product' => 'Невалиден продукт.']);
        }
        if ($product->category?->isTobacco()) {
            throw ValidationException::withMessages(['product' => 'За тютюн използвайте зарежданията по разфасовки.']);
        }

        $delivery = ProductDelivery::query()
            ->whereKey($deliveryId)
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->first();
        if ($delivery === null) {
            throw ValidationException::withMessages(['delivery' => 'Зареждането не е намерено.']);
        }

        $qty = (int) $delivery->quantity;
        DB::transaction(function () use ($product, $delivery, $qty): void {
            $delivery->delete();
            $product->update([
                'stock_quantity' => DB::raw('stock_quantity - ' . $qty),
                'updated_at' => now(),
            ]);
        });
    }

    /**
     * @return array<int, array{path:string,url:string,name:string,source:string}>
     */
    public function imageSuggestions(string $query): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $normalizedQuery = Str::of($query)->lower()->replace(' ', '')->value();
        $results = [];
        $seenPaths = [];

        $dbSuggestions = Product::query()
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

            $url = str_starts_with($path, 'http') ? $path : asset($path);

            $results[] = [
                'path' => $path,
                'url' => $url,
                'name' => (string) $item->name,
                'source' => 'products',
            ];
            $seenPaths[$path] = true;
        }

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
            }
        }

        return array_slice($results, 0, 40);
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
        Product::query()
            ->whereKey($productId)
            ->update([
                'stock_quantity' => $grams,
                'updated_at' => now(),
            ]);
    }
}

