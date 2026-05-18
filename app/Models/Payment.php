<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'reservation_id',
        'user_id',
        'provider',
        'transaction_id',
        'amount',
        'status',
        'method',
        'paid_at',
        'transaction_reference',
    ];

    protected $dates = ['paid_at', 'created_at', 'updated_at'];

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
