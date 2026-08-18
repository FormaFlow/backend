<?php

declare(strict_types=1);

namespace FormaFlow\Learning\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class LearningAssessmentVersionModel extends Model
{
    protected $table = 'learning_assessment_versions';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'assessment_id', 'version_number', 'snapshot', 'max_points', 'published_by_user_id', 'published_at'];
    protected $casts = ['snapshot' => 'array', 'version_number' => 'integer', 'max_points' => 'integer', 'published_at' => 'datetime'];
}
