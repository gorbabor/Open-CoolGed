<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Tag extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'name'];

    public function documents()
    {
        return $this->belongsToMany(Document::class)->withPivot('tenant_id');
    }
}
