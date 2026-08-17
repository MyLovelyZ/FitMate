<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'has_sizes',
        'default_size_type',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'has_sizes' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    // relationships

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(Size::class);
    }
}
