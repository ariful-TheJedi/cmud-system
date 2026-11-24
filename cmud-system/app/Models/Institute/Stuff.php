<?php

namespace App\Models\Institute;

use App\Models\Academy\Teacher;
use App\Models\Finance\InstituteExpense;
use App\Models\Security\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Stuff extends Model
{
    protected $table = 'stuff';

    /** Hide raw storage locations while exposing URLs generated from the configured media disk. */
    protected $hidden = ['nid_file_path', 'photo_path'];

    protected $appends = ['has_nid_file', 'nid_file_download_url', 'photo_url'];

    protected $fillable = [
        'institute_id',
        'user_id',
        'stuff_code',
        'full_name',
        'phone',
        'email',
        'nid_number',
        'nid_file_path',
        'address',
        'date_of_birth',
        'photo_path',
        'designation',
        'joining_date',
        'leaving_date',
        'employment_status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'joining_date' => 'date',
            'leaving_date' => 'date',
        ];
    }

    /** Report document presence without returning its private storage path. */
    public function getHasNidFileAttribute(): bool
    {
        return filled($this->nid_file_path);
    }

    /** Provide the API endpoint that streams the NID as a downloaded attachment. */
    public function getNidFileDownloadUrlAttribute(): ?string
    {
        return filled($this->nid_file_path)
            ? route('stuff.nid.download', $this->getKey())
            : null;
    }

    /** Resolve the stuff photo through the public disk and configured MEDIA_FILE_PREFIX. */
    public function getPhotoUrlAttribute(): ?string
    {
        return filled($this->photo_path)
            ? Storage::disk('public')->url($this->photo_path)
            : null;
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stuffAssignments(): HasMany
    {
        return $this->hasMany(StuffAssignment::class);
    }

    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    public function instituteExpenses(): HasMany
    {
        return $this->hasMany(InstituteExpense::class);
    }
}
