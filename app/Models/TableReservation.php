<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_table_id',
        'people_count',
        'starts_at',
        'ends_at',
        'customer_name',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<StoreTable, $this>
     */
    public function table(): BelongsTo
    {
        return $this->belongsTo(StoreTable::class, 'store_table_id');
    }

    /**
     * @param Builder<TableReservation> $query
     * @return Builder<TableReservation>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Overlap check helper: intervals overlap iff starts_at < $end AND ends_at > $start.
     *
     * @param Builder<TableReservation> $query
     * @return Builder<TableReservation>
     */
    public function scopeOverlapping(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $query
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start);
    }
}

