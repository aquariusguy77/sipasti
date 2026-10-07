<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    /**
     * Kemampuan peran dibaca dari matriks pada config/sipasti.php (NFR2).
     */
    public function hasAbility(string $ability): bool
    {
        return $this->active
            // Nama kemampuan memuat titik, sehingga matriks dibaca utuh lalu
            // diindeks, bukan melalui notasi titik config().
            && in_array($this->role, config('sipasti.abilities')[$ability] ?? [], true);
    }

    public function roleLabel(): string
    {
        return config("sipasti.roles.{$this->role}", $this->role);
    }

    /**
     * Identitas pelaku pada log audit.
     */
    public function auditActor(): string
    {
        return "{$this->role}:{$this->email}";
    }
}
