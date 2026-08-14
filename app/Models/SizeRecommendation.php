<?php

namespace App\Models;

use App\Enums\FitStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SizeRecommendation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_body_profile_id',
        'size_chart_id',
        'size_chart_entry_id',
        'product_id',
        'fit_status',
        'fit_score',
        'matched_measurements',
        'total_measurements',
        'computed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fit_status' => FitStatus::class,
            'fit_score' => 'decimal:2',
            'computed_at' => 'datetime',
        ];
    }

    // Bagian Relationships

    public function bodyProfile(): BelongsTo
    {
        return $this->belongsTo(UserBodyProfile::class, 'user_body_profile_id');
    }

    public function sizeChart(): BelongsTo
    {
        return $this->belongsTo(SizeChart::class);
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(SizeChartEntry::class, 'size_chart_entry_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
