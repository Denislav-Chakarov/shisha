<?php

namespace App\Services\Orders;

use App\Models\HookahRecipe;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StoreTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderService
{
    public function appendItem(int $storeTableId, int $productId, int $quantity, ?int $userId, ?string $metaNote = null): OrderItem
    {
        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Невалидно количество.',
            ]);
        }

        return DB::transaction(function () use ($storeTableId, $productId, $quantity, $userId, $metaNote): OrderItem {
            $table = StoreTable::query()
                ->whereKey($storeTableId)
                ->lockForUpdate()
                ->first();

            if ($table === null || ! $table->is_active) {
                throw ValidationException::withMessages([
                    'store_table_id' => 'Избраната маса е неактивна.',
                ]);
            }

            $order = Order::query()
                ->where('store_table_id', $storeTableId)
                ->where('status', 'open')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($order === null) {
                $order = Order::query()->create([
                    'store_table_id' => $storeTableId,
                    'user_id' => $userId,
                    'status' => 'open',
                    'total_amount' => 0,
                    'opened_at' => now(),
                ]);
            }

            $product = Product::query()
                ->whereKey($productId)
                ->lockForUpdate()
                ->first();

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

            $item = OrderItem::query()->create([
                'order_id' => (int) $order->id,
                'product_id' => (int) $product->id,
                'quantity' => $quantity,
                'item_status' => 'ordered',
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'meta_note' => $metaNote,
            ]);

            $product->update([
                'stock_quantity' => DB::raw("stock_quantity - {$quantity}"),
            ]);

            $this->recalculateOrderTotal((int) $order->id);

            return $item;
        });
    }

    public function updateItemQuantity(int $itemId, int $newQuantity): void
    {
        if ($newQuantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => 'Невалидно количество.',
            ]);
        }

        DB::transaction(function () use ($itemId, $newQuantity): void {
            $item = OrderItem::query()
                ->whereKey($itemId)
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                throw ValidationException::withMessages([
                    'quantity' => 'Артикулът не е намерен.',
                ]);
            }

            $order = Order::query()
                ->whereKey((int) $item->order_id)
                ->lockForUpdate()
                ->first();

            if ($order === null || $order->status !== 'open') {
                throw ValidationException::withMessages([
                    'quantity' => 'Поръчката вече е приключена.',
                ]);
            }

            $product = Product::query()
                ->whereKey((int) $item->product_id)
                ->lockForUpdate()
                ->first();

            if ($product === null) {
                throw ValidationException::withMessages([
                    'quantity' => 'Продуктът не е намерен.',
                ]);
            }

            $oldQty = (int) $item->quantity;
            $delta = $newQuantity - $oldQty;

            if ($delta > 0 && (int) $product->stock_quantity < $delta) {
                throw ValidationException::withMessages([
                    'quantity' => "Недостатъчна наличност за {$product->name}.",
                ]);
            }

            if ($delta !== 0) {
                $product->update([
                    'stock_quantity' => DB::raw('stock_quantity ' . ($delta > 0 ? '-' : '+') . ' ' . abs($delta)),
                ]);

                if ($product->category === 'hookah') {
                    if ($delta > 0) {
                        $this->consumeHookahTobacco((int) $product->id, $delta);
                    } else {
                        $this->restoreHookahTobacco((int) $product->id, abs($delta));
                    }
                }
            }

            $item->update([
                'quantity' => $newQuantity,
                'line_total' => (float) $item->unit_price * $newQuantity,
            ]);

            $this->recalculateOrderTotal((int) $item->order_id);
        });
    }

    public function updateItemStatus(int $itemId, string $itemStatus): void
    {
        if (! in_array($itemStatus, ['ordered', 'served'], true)) {
            throw ValidationException::withMessages([
                'item_status' => 'Невалиден статус.',
            ]);
        }

        $updated = OrderItem::query()
            ->whereKey($itemId)
            ->update([
                'item_status' => $itemStatus,
                'updated_at' => now(),
            ]);

        if ($updated === 0) {
            throw ValidationException::withMessages([
                'item_status' => 'Артикулът не е намерен.',
            ]);
        }
    }

    public function deleteItem(int $itemId): void
    {
        DB::transaction(function () use ($itemId): void {
            $item = OrderItem::query()
                ->whereKey($itemId)
                ->lockForUpdate()
                ->first();

            if ($item === null) {
                throw ValidationException::withMessages([
                    'order_item' => 'Артикулът не е намерен.',
                ]);
            }

            $order = Order::query()
                ->whereKey((int) $item->order_id)
                ->lockForUpdate()
                ->first();

            if ($order === null || $order->status !== 'open') {
                throw ValidationException::withMessages([
                    'order_item' => 'Поръчката вече е приключена.',
                ]);
            }

            $product = Product::query()
                ->whereKey((int) $item->product_id)
                ->lockForUpdate()
                ->first();

            if ($product !== null) {
                $qty = (int) $item->quantity;
                $product->update([
                    'stock_quantity' => DB::raw("stock_quantity + {$qty}"),
                ]);

                if ($product->category === 'hookah') {
                    $this->restoreHookahTobacco((int) $product->id, $qty);
                }
            }

            $orderId = (int) $item->order_id;
            $item->delete();

            $this->recalculateOrderTotal($orderId);
        });
    }

    public function closeOrderForTable(int $storeTableId): void
    {
        DB::transaction(function () use ($storeTableId): void {
            $order = Order::query()
                ->where('store_table_id', $storeTableId)
                ->where('status', 'open')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($order === null) {
                throw ValidationException::withMessages([
                    'store_table_id' => 'Няма отворена поръчка за тази маса.',
                ]);
            }

            $hookahItems = OrderItem::query()
                ->join('products', 'order_items.product_id', '=', 'products.id')
                ->where('order_items.order_id', (int) $order->id)
                ->where('products.category', 'hookah')
                ->select('order_items.product_id', DB::raw('SUM(order_items.quantity) as total_quantity'))
                ->groupBy('order_items.product_id')
                ->get();

            foreach ($hookahItems as $hookahItem) {
                $qty = max(0, (int) $hookahItem->total_quantity);
                if ($qty <= 0) {
                    continue;
                }

                Product::query()
                    ->whereKey((int) $hookahItem->product_id)
                    ->lockForUpdate()
                    ->update([
                        'stock_quantity' => DB::raw("stock_quantity + {$qty}"),
                        'updated_at' => now(),
                    ]);
            }

            $order->update([
                'status' => 'paid',
                'closed_at' => now(),
            ]);
        });
    }

    private function consumeHookahTobacco(int $hookahProductId, int $quantity): void
    {
        $recipes = HookahRecipe::query()
            ->where('hookah_product_id', $hookahProductId)
            ->lockForUpdate()
            ->get();

        foreach ($recipes as $recipe) {
            $gramsNeededInt = (int) ceil(((float) $recipe->grams_per_serving) * $quantity);
            if ($gramsNeededInt < 1) {
                continue;
            }

            $tobacco = Product::query()
                ->whereKey((int) $recipe->tobacco_product_id)
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

            $tobacco->update([
                'stock_quantity' => DB::raw("stock_quantity - {$gramsNeededInt}"),
            ]);
        }
    }

    private function restoreHookahTobacco(int $hookahProductId, int $quantity): void
    {
        if ($quantity < 1) {
            return;
        }

        $recipes = HookahRecipe::query()
            ->where('hookah_product_id', $hookahProductId)
            ->lockForUpdate()
            ->get();

        foreach ($recipes as $recipe) {
            $gramsNeededInt = (int) ceil(((float) $recipe->grams_per_serving) * $quantity);
            if ($gramsNeededInt < 1) {
                continue;
            }

            Product::query()
                ->whereKey((int) $recipe->tobacco_product_id)
                ->lockForUpdate()
                ->update([
                    'stock_quantity' => DB::raw("stock_quantity + {$gramsNeededInt}"),
                    'updated_at' => now(),
                ]);
        }
    }

    private function recalculateOrderTotal(int $orderId): void
    {
        $newTotal = (float) OrderItem::query()
            ->where('order_id', $orderId)
            ->sum('line_total');

        Order::query()
            ->whereKey($orderId)
            ->update([
                'total_amount' => $newTotal,
                'updated_at' => now(),
            ]);
    }
}

