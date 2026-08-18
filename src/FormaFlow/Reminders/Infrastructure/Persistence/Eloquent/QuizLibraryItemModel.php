<?php

declare(strict_types=1);

namespace FormaFlow\Reminders\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class QuizLibraryItemModel extends Model
{
    protected $table = 'quiz_library_items';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'form_id',
        'user_id',
        'last_opened_at',
    ];

    protected $casts = [
        'last_opened_at' => 'datetime',
    ];
}
