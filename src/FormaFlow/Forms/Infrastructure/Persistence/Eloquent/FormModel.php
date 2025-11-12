<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Infrastructure\Persistence\Eloquent;

use Database\Factories\FormModelFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class FormModel extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $table = 'forms';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'name',
        'description',
        'published',
        'version',
    ];

    protected $casts = [
        'published' => 'boolean',
        'version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(FormFieldModel::class, 'form_id', 'id');
    }

    public static function newFactory(): Factory
    {
        return FormModelFactory::new();
    }
}
