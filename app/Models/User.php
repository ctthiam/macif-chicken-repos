<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * Fichier : app/Models/User.php
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'role',
        'avatar', 'adresse', 'ville',
        'is_verified', 'is_active',
        'email_verification_token', 'email_verified_at',
        'password_reset_token', 'password_reset_expires_at', // AUTH-08
    ];

    protected $hidden = [
        'password', 'remember_token',
        'email_verification_token',
        'password_reset_token',
    ];

    protected $casts = [
        'is_verified'               => 'boolean',
        'is_active'                 => 'boolean',
        'email_verified_at'         => 'datetime',
        'password_reset_expires_at' => 'datetime',
    ];

    // ─── Relations ───────────────────────────────────────────

    public function eleveurProfile()
    {
        return $this->hasOne(EleveurProfile::class, 'user_id');
    }

    public function acheteurProfile()
    {
        return $this->hasOne(AcheteurProfile::class, 'user_id');
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class, 'eleveur_id');
    }

    public function commandesAcheteur()
    {
        return $this->hasMany(Commande::class, 'acheteur_id');
    }

    public function commandesEleveur()
    {
        return $this->hasMany(Commande::class, 'eleveur_id');
    }

    public function abonnements()
    {
        return $this->hasMany(Abonnement::class, 'eleveur_id');
    }

    public function abonnementActif()
    {
        return $this->hasOne(Abonnement::class, 'eleveur_id')
            ->where('statut', 'actif')
            ->where('date_fin', '>=', now()->toDateString())
            ->latest();
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function avisRecus()
    {
        return $this->hasMany(Avis::class, 'cible_id');
    }

    public function favoris()
    {
        return $this->hasMany(Favori::class, 'user_id');
    }

    // ─── Helpers ─────────────────────────────────────────────

    public function isAdmin(): bool    { return $this->role === 'admin'; }
    public function isEleveur(): bool  { return $this->role === 'eleveur'; }
    public function isAcheteur(): bool { return $this->role === 'acheteur'; }

    public function hasAbonnementActif(): bool
    {
        return $this->abonnementActif()->exists();
    }

    /**
     * Vérifie si le token de reset est encore valide (non expiré).
     */
    public function isPasswordResetTokenValid(): bool
    {
        return $this->password_reset_token !== null
            && $this->password_reset_expires_at !== null
            && $this->password_reset_expires_at->isFuture();
    }
}