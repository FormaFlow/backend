<?php

declare(strict_types=1);

namespace FormaFlow\Entries\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class EntryTagModel extends Model
{
    protected $table = 'entry_tags';
    protected $fillable = ['entry_id', 'tag'];
    public $incrementing = false;
}
