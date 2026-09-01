<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Space extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = ['tenant_id', 'name', 'description', 'color', 'is_personal', 'personal_user_id'];

    protected $casts = ['is_personal' => 'boolean'];

    public function personalUser()
    {
        return $this->belongsTo(User::class, 'personal_user_id');
    }

    public function folders()
    {
        return $this->hasMany(Folder::class);
    }

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
