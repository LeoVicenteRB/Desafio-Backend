<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'subadquirer',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the pix transactions for the user.
     */
    public function pix()
    {
        return $this->hasMany(Pix::class);
    }

    /**
     * Get the withdraws for the user.
     */
    public function withdraws()
    {
        return $this->hasMany(Withdraw::class);
    }

    /**
     * Get the user's subadquirers.
     */
    public function subadquirers()
    {
        return $this->hasMany(UserSubadquirer::class);
    }

    /**
     * Get the active subadquirer for the user.
     */
    public function getActiveSubadquirer(): ?string
    {
        // Primeiro tenta pegar da relação user_subadquirers
        $activeSubadquirer = $this->subadquirers()
            ->where('is_active', true)
            ->first();

        if ($activeSubadquirer) {
            return $activeSubadquirer->subadquirer;
        }

        // Fallback para o campo direto no users
        return $this->subadquirer;
    }
}

