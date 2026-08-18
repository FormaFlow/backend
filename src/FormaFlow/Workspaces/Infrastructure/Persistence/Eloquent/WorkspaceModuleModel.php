<?php

declare(strict_types=1);

namespace FormaFlow\Workspaces\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class WorkspaceModuleModel extends Model
{
    protected $table = 'workspace_modules';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'workspace_id', 'module_key', 'enabled', 'config'];
    protected $casts = ['enabled' => 'boolean', 'config' => 'array'];
}
