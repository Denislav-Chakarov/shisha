<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductDelivery extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'delivered_at',
        'quantity',
        'unit_cost',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'date',
            'unit_cost' => 'decimal:2',
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

