<?php

declare(strict_types=1);

namespace FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent;

use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LearnerProfileModel extends Model
{
    protected $table = 'learner_profiles';
    public $incrementing = false;
    protected $primaryKey = 'user_id';
    protected $keyType = 'string';
    protected $fillable = ['user_id', 'target_grade', 'timezone'];
    protected $casts = ['target_grade' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
