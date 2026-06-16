<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function robot(): HasOne
    {
        return $this->hasOne(Robot::class);
    }

    protected static function booted(): void
    {
        static::created(function (User $user) {
            $user->robot()->create([
                'name' => 'Zero-' . str_pad((string) $user->id, 3, '0', STR_PAD_LEFT),
                'version' => 1,
                'cpu' => 1,
                'ram' => 8,
                'ssd' => 32,
                'battery' => 100,
                'integrity' => 100,
            ]);
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}