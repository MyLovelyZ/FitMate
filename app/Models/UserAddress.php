<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAddress extends Model
{
    use HasFactory;


    protected $fillable = [
        'user_id',
        'label',
        'recipient_name',
        'recipient_phone',
        'street',
        'city',
        'state',
        'postal_code',
        'country',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'latitude'   => 'float',
        'longitude'  => 'float',
    ];

    // Bagian Relationships

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}