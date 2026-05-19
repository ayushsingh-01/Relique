<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auction extends Model
{
    protected $fillable = [
        'seller_id', 'category_id', 'title', 'description', 
        'starting_price', 'current_price', 'buy_it_now_price', 'end_time', 'status', 'payment_status', 'image_path', 'has_overtime_started'
    ];

    protected $casts = [
        'end_time' => 'datetime',
        'starting_price' => 'decimal:2',
        'current_price' => 'decimal:2',
        'buy_it_now_price' => 'decimal:2',
        'has_overtime_started' => 'boolean',
    ];

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function bids()
    {
        return $this->hasMany(Bid::class);
    }
}
