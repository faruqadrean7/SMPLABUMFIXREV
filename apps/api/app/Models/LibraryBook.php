<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LibraryBook extends Model
{
    use HasUuids;

    protected $fillable = [
        'school_id',
        'title',
        'author',
        'publisher',
        'publish_year',
        'isbn',
        'category',
        'book_type',
        'stock',
        'cover_file_id',
        'file_id',
        'location_shelf',
    ];

    protected $casts = [
        'publish_year' => 'integer',
        'stock' => 'integer',
    ];

    protected $appends = [
        'available_stock',
    ];

    public function coverFile(): BelongsTo
    {
        return $this->belongsTo(File::class, 'cover_file_id');
    }

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class, 'book_id');
    }

    /**
     * Jumlah eksemplar buku yang sedang dipinjam aktif.
     */
    public function getActiveLoansCountAttribute(): int
    {
        return $this->loans()->where('status', 'dipinjam')->count();
    }

    /**
     * Jumlah eksemplar yang tersedia untuk dipinjam.
     */
    public function getAvailableStockAttribute(): int
    {
        if ($this->book_type === 'digital') {
            return 999;
        }

        return max(0, $this->stock - $this->active_loans_count);
    }
}
