<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TobaccoPackInventory extends Model
{
    use HasFactory;

    protected $table = 'tobacco_pack_inventory';

    protected $fillable = [
        'product_id',
        'pack_grams',
        'boxes_on_hand',
    ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

