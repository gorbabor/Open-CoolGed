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

    /** Profondeur du dossier dans l'espace (1 = racine de l'espace). */
    public function depth(): int
    {
        return count($this->ancestors()) + 1;
    }

    /** Noms des dossiers depuis la racine jusqu'à ce dossier. */
    public function pathNames(): array
    {
        $names = [$this->name];
        $current = $this->parent;
        while ($current) {
            array_unshift($names, $current->name);
            $current = $current->parent;
        }

        return $names;
    }

    public function pathLabel(string $separator = ' › '): string
    {
        return implode($separator, $this->pathNames());
    }
}
