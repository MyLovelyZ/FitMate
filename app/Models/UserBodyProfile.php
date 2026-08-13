<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserBodyProfile extends Model
{
    protected $table = 'user_body_profiles';

    protected $fillable = [
        'user_id',
        'height',
        'weight',
        'body_fat_percentage',
        'muscle_mass',
        'bmi',
    ];

    // bagian relationship dengan model yang lain

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
