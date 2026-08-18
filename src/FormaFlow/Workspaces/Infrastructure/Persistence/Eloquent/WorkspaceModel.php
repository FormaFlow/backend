<?php

declare(strict_types=1);

namespace FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WorkspaceModel extends Model
{
    protected $table = 'workspaces';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'name', 'slug', 'type', 'timezone'];

    public function memberships(): HasMany
    {
        return $this->hasMany(WorkspaceMembershipModel::class, 'workspace_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(WorkspaceModuleModel::class, 'workspace_id');
    }
}
