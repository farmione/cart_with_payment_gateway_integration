<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shoppingcart extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    const STATUS = [
        'pending' => 0,
        'in_progress' => 1,
        'success' => 2,
        'error' => 3
    ];
}
