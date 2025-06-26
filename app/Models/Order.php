<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'payment_status',
        'payment_method',
        'payment_id',
        'preference_id',
        'payment_data',
        'subtotal',
        'shipping_cost',
        'discount',
        'total',
        'shipping_address',
        'billing_address',
        'shipping_quote_id',
        'tracking_code',
        'shipping_data',
        'notes',
        'customer_notes',
        'shipped_at',
        'delivered_at',
    ];

    protected $casts = [
        'payment_data' => 'array',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'shipping_data' => 'array',
        'subtotal' => 'decimal:2',
        'shipping_cost' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
    ];

    protected $hidden = [
        'notes', // Observações internas não devem ser expostas para o cliente
    ];

    // Relacionamentos
    public function user(): BelongsTo
    {
        return $this->belongsTo(ClientUser::class, 'user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->where('change_type', '!=', 'internal');
    }

    public function shippingLabel(): HasOne
    {
        return $this->hasOne(ShippingLabel::class);
    }

    public function paymentTransactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function coupons(): HasMany
    {
        return $this->hasMany(OrderCoupon::class);
    }

    public function shippingQuote(): BelongsTo
    {
        return $this->belongsTo(ShippingQuote::class);
    }

    // Scopes para o cliente
    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeRecent($query, $days = 90)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'delivered');
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['delivered', 'canceled', 'refunded']);
    }

    // Métodos para o front-end
    public function getStatusLabel(): string
    {
        return match($this->status) {
            'pending' => 'Aguardando Pagamento',
            'payment_approved' => 'Pagamento Aprovado',
            'preparing' => 'Preparando Envio',
            'shipped' => 'Enviado',
            'delivered' => 'Entregue',
            'canceled' => 'Cancelado',
            'refunded' => 'Reembolsado',
            default => 'Status Desconhecido',
        };
    }

    public function getPaymentStatusLabel(): string
    {
        return match($this->payment_status) {
            'pending' => 'Pendente',
            'approved' => 'Aprovado',
            'rejected' => 'Rejeitado',
            'cancelled' => 'Cancelado',
            'refunded' => 'Reembolsado',
            'in_process' => 'Processando',
            default => 'Status Desconhecido',
        };
    }

    public function getStatusColor(): string
    {
        return match($this->status) {
            'pending' => 'warning',
            'payment_approved' => 'success',
            'preparing' => 'info',
            'shipped' => 'primary',
            'delivered' => 'success',
            'canceled' => 'danger',
            'refunded' => 'secondary',
            default => 'secondary',
        };
    }

    public function getFormattedTotal(): string
    {
        return 'R$ ' . number_format($this->total, 2, ',', '.');
    }

    public function getFormattedSubtotal(): string
    {
        return 'R$ ' . number_format($this->subtotal, 2, ',', '.');
    }

    public function getFormattedShipping(): string
    {
        return 'R$ ' . number_format($this->shipping_cost, 2, ',', '.');
    }

    public function getFormattedDiscount(): string
    {
        return 'R$ ' . number_format($this->discount, 2, ',', '.');
    }

    public function canCancel(): bool
    {
        return in_array($this->status, ['pending', 'payment_approved'])
               && $this->payment_status !== 'approved';
    }

    public function canTrack(): bool
    {
        return !empty($this->tracking_code) && in_array($this->status, ['shipped', 'delivered']);
    }

    public function getTrackingUrl(): ?string
    {
        if (!$this->canTrack()) {
            return null;
        }

        $shippingData = $this->shipping_data;
        if (!$shippingData || !isset($shippingData['company_name'])) {
            return null;
        }

        return match($shippingData['company_name']) {
            'Correios' => "https://www.correios.com.br/rastreamento/{$this->tracking_code}",
            'Jadlog' => "https://www.jadlog.com.br/tracking/{$this->tracking_code}",
            default => null,
        };
    }

    public function getEstimatedDelivery(): ?string
    {
        if (!$this->shipped_at || !$this->shipping_data) {
            return null;
        }

        $shippingData = $this->shipping_data;
        if (!isset($shippingData['delivery_time'])) {
            return null;
        }

        $deliveryDate = $this->shipped_at->addDays($shippingData['delivery_time']);
        return $deliveryDate->format('d/m/Y');
    }

    public function hasActivePayment(): bool
    {
        return $this->paymentTransactions()
                   ->whereIn('status', ['pending', 'in_process', 'authorized'])
                   ->exists();
    }

    public function getLatestPaymentTransaction(): ?PaymentTransaction
    {
        return $this->paymentTransactions()->latest()->first();
    }

    // Serialização para API
    public function toArray()
    {
        $array = parent::toArray();

        // Adiciona campos calculados
        $array['status_label'] = $this->getStatusLabel();
        $array['payment_status_label'] = $this->getPaymentStatusLabel();
        $array['status_color'] = $this->getStatusColor();
        $array['formatted_total'] = $this->getFormattedTotal();
        $array['formatted_subtotal'] = $this->getFormattedSubtotal();
        $array['formatted_shipping'] = $this->getFormattedShipping();
        $array['formatted_discount'] = $this->getFormattedDiscount();
        $array['can_cancel'] = $this->canCancel();
        $array['can_track'] = $this->canTrack();
        $array['tracking_url'] = $this->getTrackingUrl();
        $array['estimated_delivery'] = $this->getEstimatedDelivery();

        return $array;
    }
}
