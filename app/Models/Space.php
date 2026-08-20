<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Space extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = ['tenant_id', 'name', 'description', 'color'];

    public function folders()
    {
        return $this->hasMany(Folder::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
