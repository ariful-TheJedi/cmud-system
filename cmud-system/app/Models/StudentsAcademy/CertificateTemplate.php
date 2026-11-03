<?php

namespace App\Models\StudentsAcademy;

use App\Models\InstituteBranch\Institute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CertificateTemplate extends Model
{
    protected $fillable = [
        'institute_id',
        'course_id',
        'name',
        'version_number',
        'template_file_path',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }
}
