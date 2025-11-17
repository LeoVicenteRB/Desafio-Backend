<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Withdraw extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'subadquirer',
        'external_withdraw_id',
        'transaction_id',
        'amount',
        'status',
        'bank_info',
        'metadata',
        'requested_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'bank_info' => 'array',
        'metadata' => 'array',
        'requested_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the user that owns the withdraw.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if withdraw is completed.
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['SUCCESS', 'DONE']);
    }
}

