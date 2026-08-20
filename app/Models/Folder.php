<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Folder extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = ['tenant_id', 'space_id', 'parent_id', 'name', 'inherit_permissions'];

    protected $casts = ['inherit_permissions' => 'boolean'];

    public function space()
    {
        return $this->belongsTo(Space::class);
    }

    public function parent()
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Folder::class, 'parent_id');
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }

    public function ancestors(): array
    {
        $ids = [];
        $current = $this->parent;
        while ($current) {
            $ids[] = $current->id;
            $current = $current->parent;
        }

        return $ids;
    }
}
