<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'category_id',
        'name',
        'flavor',
        'price',
        'purchase_price',
        'stock_quantity',
        'unit',
        'image_path',
        'is_active',
        'writeoff_mode',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'is_active' => 'bool',
        ];
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<TobaccoPackPurchase, $this>
     */
    public function tobaccoPackPurchases(): HasMany
    {
        return $this->hasMany(TobaccoPackPurchase::class);
    }

    /**
     * @return HasMany<TobaccoPackInventory, $this>
     */
    public function tobaccoPackInventory(): HasMany
    {
        return $this->hasMany(TobaccoPackInventory::class);
    }

    /**
     * @return HasMany<ProductDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(ProductDelivery::class);
    }

    /**
     * @param Builder<Product> $query
     * @return Builder<Product>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param Builder<Product> $query
     * @return Builder<Product>
     */
    public function scopeCategory(Builder $query, string $category): Builder
    {
        return $query->whereHas('category', function (Builder $q) use ($category): void {
            $q->where('slug', $category)->orWhere('behavior_type', $category);
        });
    }
}

