<?php

namespace App\Models\UserAccess;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PasswordResetToken extends Model
{
    protected $table = 'password_reset_tokens';
    protected $primaryKey = 'email';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['email', 'token'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'email', 'email');
    }
}
