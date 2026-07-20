<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Infrastructure\Persistence\Eloquent;

use Database\factories\FormModelFactory;
use FormaFlow\Entries\Infrastructure\Persistence\Eloquent\EntryModel;
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
        'is_quiz',
        'single_submission',
        'quick_entry_favorite',
        'reminder_interval_minutes',
    ];

    protected $casts = [
        'published' => 'boolean',
        'version' => 'integer',
        'is_quiz' => 'boolean',
        'single_submission' => 'boolean',
        'quick_entry_favorite' => 'boolean',
        'reminder_interval_minutes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function fields(): HasMany
    {
        return $this->hasMany(FormFieldModel::class, 'form_id', 'id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(EntryModel::class, 'form_id', 'id');
    }

    public static function newFactory(): FormModelFactory
    {
        return FormModelFactory::new();
    }
}
