<?php

declare(strict_types=1);

namespace FormaFlow\Payments\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class PaymentCategoryModel extends Model
{
    use SoftDeletes;

    protected $table = 'payment_categories';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'user_id', 'name', 'color'];

    public function plans(): HasMany
    {
        return $this->hasMany(PaymentPlanModel::class, 'category_id');
    }

    protected static function booted(): void
    {
        self::creating(static function (self $model): void {
            $model->id ??= (string)Str::uuid();
        });
    }
}
