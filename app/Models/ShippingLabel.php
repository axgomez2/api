<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ShippingLabel extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'order_id',
        'shipping_quote_id',
        'status',
        'tracking_code',
        'label_url',
        'melhor_envio_response'
    ];

    protected $casts = [
        'melhor_envio_response' => 'array'
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function shippingQuote()
    {
        return $this->belongsTo(ShippingQuote::class);
    }
}
