<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBodyProfile extends Model
{
    use HasFactory;

    protected $table = 'user_body_profiles';

    protected $fillable = [
        'user_id',
        'profile_name',
        'gender',
        'is_default',
        'height',
        'weight',
        'chest',
        'waist',
        'hip',
        'foot_length',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'height' => 'decimal:2',
            'weight' => 'decimal:2',
            'chest' => 'decimal:2',
            'waist' => 'decimal:2',
            'hip' => 'decimal:2',
            'foot_length' => 'decimal:2',
        ];
    }

    // bagian relationship dengan model yang lain

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
