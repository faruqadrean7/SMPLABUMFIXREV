<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionImportJob extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'question_import_jobs';

    protected $fillable = [
        'bank_id',
        'uploaded_by',
        'source_file_id',
        'status',
        'parsed_payload',
        'parse_errors',
        'imported_count',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'parsed_payload' => 'array',
            'parse_errors' => 'array',
            'imported_count' => 'integer',
        ];
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'bank_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function sourceFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'source_file_id');
    }
}
