<?php

declare(strict_types=1);

namespace FormaFlow\Reminders\Infrastructure\Persistence\Eloquent;

use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class QuizAssignmentModel extends Model
{
    protected $table = 'quiz_assignments';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'form_id',
        'assigner_user_id',
        'recipient_user_id',
        'last_notified_at',
        'next_reminder_at',
        'completed_at',
    ];

    protected $casts = [
        'last_notified_at' => 'datetime',
        'next_reminder_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(FormModel::class, 'form_id', 'id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'recipient_user_id', 'id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'assigner_user_id', 'id');
    }
}
