<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'payment_id',
        'preference_id',
        'collection_id',
        'external_reference',
        'payment_type',
        'payment_method',
        'payment_method_id',
        'status',
        'status_detail',
        'transaction_amount',
        'net_received_amount',
        'total_paid_amount',
        'currency_id',
        'mercadopago_fee',
        'discount_amount',
        'fee_details',
        'payer_data',
        'payment_method_data',
        'installments',
        'installment_amount',
        'date_approved',
        'date_created',
        'date_last_updated',
        'money_release_date',
        'pix_qr_code',
        'pix_qr_code_base64',
        'pix_transaction_id',
        'mercadopago_response',
        'webhook_notifications',
        'last_webhook_received',
        'notes'
    ];

    protected $casts = [
        'transaction_amount' => 'decimal:2',
        'net_received_amount' => 'decimal:2',
        'total_paid_amount' => 'decimal:2',
        'mercadopago_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'installment_amount' => 'decimal:2',
        'fee_details' => 'array',
        'payer_data' => 'array',
        'payment_method_data' => 'array',
        'mercadopago_response' => 'array',
        'webhook_notifications' => 'array',
        'installments' => 'integer',
        'date_approved' => 'datetime',
        'date_created' => 'datetime',
        'date_last_updated' => 'datetime',
        'money_release_date' => 'datetime',
        'last_webhook_received' => 'datetime'
    ];

    /**
     * Relacionamento com pedido
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
