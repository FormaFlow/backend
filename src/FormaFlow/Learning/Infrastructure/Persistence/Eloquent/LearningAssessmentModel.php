<?php

declare(strict_types=1);

namespace FormaFlow\Learning\Infrastructure\Persistence\Eloquent;

use FormaFlow\Forms\Infrastructure\Persistence\Eloquent\FormModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class LearningAssessmentModel extends Model
{
    protected $table = 'learning_assessments';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'id', 'workspace_id', 'form_id', 'source_id', 'created_by_user_id', 'external_id',
        'subject_code', 'purpose', 'target_grade', 'coverage_from_grade', 'coverage_to_grade',
        'status', 'current_version',
    ];
    protected $casts = [
        'target_grade' => 'integer', 'coverage_from_grade' => 'integer',
        'coverage_to_grade' => 'integer', 'current_version' => 'integer',
    ];

    public function form(): BelongsTo
    {
        return $this->belongsTo(FormModel::class, 'form_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(LearningQuestionMetadataModel::class, 'assessment_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(LearningAssessmentVersionModel::class, 'assessment_id');
    }
}
