<?php

namespace App\Models\Academy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function certificateTemplate(): BelongsTo
    {
        return $this->belongsTo(CertificateTemplate::class);
    }
}
