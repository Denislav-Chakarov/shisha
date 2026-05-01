<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TobaccoPackPurchase extends Model
{
    use HasFactory;

    protected $table = 'tobacco_pack_purchases';

    protected $fillable = [
        'product_id',
        'restocked_at',
        'pack_grams',
        'boxes_count',
        'purchase_price_per_box',
    ];

    protected function casts(): array
    {
        return [
            'restocked_at' => 'date',
            'purchase_price_per_box' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

