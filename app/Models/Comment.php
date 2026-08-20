<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'document_id', 'user_id', 'body'];

    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
