<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id'
    ];

    // Relacionamento com o usuário
    public function user()
    {
        return $this->belongsTo(ClientUser::class, 'user_id');
    }

    // Relacionamento com o produto
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
