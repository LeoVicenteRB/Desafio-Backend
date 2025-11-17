<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Pix extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'pix';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'subadquirer',
        'external_pix_id',
        'amount',
        'status',
        'payer_name',
        'payer_document',
        'reference',
        'metadata',
        'payment_date',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'payment_date' => 'datetime',
    ];

    /**
     * Get the user that owns the pix.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if pix is confirmed/paid.
     */
    public function isConfirmed(): bool
    {
        return in_array($this->status, ['CONFIRMED', 'PAID']);
    }
}

