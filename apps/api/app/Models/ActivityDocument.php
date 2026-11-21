<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityDocument extends Model
{
    use HasUuids;

    protected $table = 'activity_documents';

    protected $fillable = [
        'extracurricular_id',
        'file_id',
        'doc_type',
        'title',
        'description',
        'uploaded_by',
    ];

    public function extracurricular(): BelongsTo
    {
        return $this->belongsTo(Extracurricular::class, 'extracurricular_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
