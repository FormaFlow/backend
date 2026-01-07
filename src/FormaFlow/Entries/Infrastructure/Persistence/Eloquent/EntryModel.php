<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Infrastructure\Persistence\Eloquent;

use Database\factories\EntryModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class EntryModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'entries';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'form_id',
        'user_id',
        'data',
        'created_at',
        'score',
        'duration',
    ];

    protected $casts = [
        'data' => 'array',
    ];

    public function tags(): HasMany
    {
        return $this
            ->hasMany(EntryTagModel::class, 'entry_id', 'id');
    }

    public static function boot(): void
    {
        parent::boot();

        self::creating(static function ($model) {
            if (empty($model->id)) {
                $model->id = (string)Str::uuid();
            }
        });
    }

    public static function newFactory(): Factory
    {
        return EntryModelFactory::new();
    }
}
