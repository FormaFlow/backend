<?php

declare(strict_types=1);

namespace FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent;

use FormaFlow\Users\Infrastructure\Persistence\Eloquent\UserModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WorkspaceMembershipModel extends Model
{
    protected $table = 'workspace_memberships';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'workspace_id', 'user_id', 'role', 'status', 'managed_by_user_id'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(WorkspaceModel::class, 'workspace_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }
}
