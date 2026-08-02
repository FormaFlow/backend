<?php

declare(strict_types=1);

namespace FormaFlow\Payments\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class PaymentOccurrenceModel extends Model
{
    protected $table = 'payment_occurrences';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $guarded = [];

    protected $casts = [
        'due_on' => 'date:Y-m-d',
        'paid_at' => 'datetime',
        'nominal_amount' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlanModel::class, 'plan_id');
    }

    protected static function booted(): void
    {
        self::creating(static function (self $model): void {
            $model->id ??= (string)Str::uuid();
        });
    }
}
