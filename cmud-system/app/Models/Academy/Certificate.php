<?php

namespace App\Models\Academy;

use App\Enums\Academy\CertificateStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Certificate extends Model
{
    protected $fillable = [
        'enrollment_id',
        'certificate_template_id',
        'certificate_number',
        'recipient_name',
        'course_title',
        'issued_on',
        'certificate_file_path',
        'printed_at',
        'delivered_at',
        'received_by',
        'delivery_method',
        'delivery_reference',
        'status',
        'void_reason',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'printed_at' => 'datetime',
            'delivered_at' => 'datetime',
            'status' => CertificateStatus::class,
        ];
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /** @return BelongsTo<CertificateTemplate, $this> */
    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class);
    }

    /** @return HasMany<CertificateReissueRequest, $this> */
    public function reissueRequests(): HasMany
    {
        return $this->hasMany(CertificateReissueRequest::class, 'original_certificate_id');
    }

    /** Reissue history entries where this certificate is the replacement. */
    /** @return HasMany<CertificateReissueRequest, $this> */
    public function replacementRequests(): HasMany
    {
        return $this->hasMany(CertificateReissueRequest::class, 'replacement_certificate_id');
    }
}
