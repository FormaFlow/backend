<?php

declare(strict_types=1);

namespace FormaFlow\Forms\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class FormFieldModel extends Model
{
    protected $table = 'form_fields';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id',
        'form_id',
        'label',
        'type',
        'required',
        'options',
        'unit',
        'category',
        'order',
    ];
    protected $casts = [
        'required' => 'boolean',
        'options' => 'array',
        'order' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(FormModel::class, 'form_id', 'id');
    }
}
