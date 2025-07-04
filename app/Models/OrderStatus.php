<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatus extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'order_status_history';

    protected $fillable = [
        'order_id',
        'status_from',
        'status_to',
        'notes',
        'created_by',
        'change_type',
    ];

    /**
     * Get the order that owns the status.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
