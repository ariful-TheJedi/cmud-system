<?php

namespace App\Models\Academy;

use App\Enums\Academy\CertificateReissueStatus;
use App\Models\Security\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CertificateReissueRequest extends Model
{
    protected $fillable = [
        'original_certificate_id',
        'replacement_certificate_id',
        'requested_by',
        'decided_by',
        'reason',
        'requested_at',
        'decision_at',
        'status',
        'decision_note',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'decision_at' => 'datetime',
            'status' => CertificateReissueStatus::class,
        ];
    }

    public function originalCertificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class, 'original_certificate_id');
    }

    public function replacementCertificate(): BelongsTo
    {
        return $this->belongsTo(Certificate::class, 'replacement_certificate_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
