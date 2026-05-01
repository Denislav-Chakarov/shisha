<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreTable extends Model
{
    use HasFactory;

    protected $table = 'store_tables';

    protected $fillable = [
        'table_number',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'bool',
        ];
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<TableReservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(TableReservation::class);
    }

    /**
     * @param Builder<StoreTable> $query
     * @return Builder<StoreTable>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

