<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HookahRecipe extends Model
{
    use HasFactory;

    protected $fillable = [
        'hookah_product_id',
        'tobacco_product_id',
        'grams_per_serving',
    ];

    protected function casts(): array
    {
        return [
            'grams_per_serving' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function hookahProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'hookah_product_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function tobaccoProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'tobacco_product_id');
    }
}

