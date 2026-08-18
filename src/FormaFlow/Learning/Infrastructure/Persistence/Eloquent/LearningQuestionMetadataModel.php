<?php

declare(strict_types=1);

namespace FormaFlow\Learning\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class LearningQuestionMetadataModel extends Model
{
    protected $table = 'learning_question_metadata';
    protected $primaryKey = 'form_field_id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'form_field_id', 'assessment_id', 'external_id', 'answer_type', 'answer_config',
        'explanation', 'topic', 'difficulty', 'prompt_media_id', 'explanation_media_id',
    ];
    protected $casts = ['answer_config' => 'array'];
}
