<?php

namespace App\Models\UserAccess;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Session extends Model
{
    protected $table = 'sessions';
    protected $primaryKey = 'id';
    protected $keyType = 'string';
    public $incrementing = false;
    protected $fillable = ['id', 'user_id', 'ip_address', 'user_agent', 'payload', 'last_activity'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
