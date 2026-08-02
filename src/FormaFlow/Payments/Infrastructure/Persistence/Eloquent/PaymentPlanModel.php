<?php

declare(strict_types=1);

namespace FormaFlow\Payments\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class PaymentPlanModel extends Model
{
    use SoftDeletes;

    protected $table = 'payment_plans';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected $casts = [
        'starts_on' => 'date:Y-m-d',
        'ends_on' => 'date:Y-m-d',
        'closed_at' => 'datetime',
        'default_nominal_amount' => 'decimal:2',
        'default_expected_amount' => 'decimal:2',
        'fee_percent' => 'decimal:4',
        'fee_fixed' => 'decimal:2',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(PaymentCategoryModel::class, 'category_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(PaymentOccurrenceModel::class, 'plan_id');
    }

    protected static function booted(): void
    {
        self::creating(static function (self $model): void {
            $model->id ??= (string)Str::uuid();
        });
    }
}
