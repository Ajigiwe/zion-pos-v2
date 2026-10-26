<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /** Shops this staff member is allowed to operate in (multi-shop feature). */
    public function shops(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'user_shop')->withTimestamps();
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role_label(): string
    {
        $role = $this->getRoleNames()->first() ?? 'unassigned';

        return [
            'superadmin' => 'Superadmin',
            'admin' => 'Owner',
            'manager' => 'Manager',
            'inventory_manager' => 'Inventory Manager',
            'cashier' => 'Cashier',
        ][$role] ?? ucfirst($role);
    }

    /** Compact machine role name for JS (till banner / offline payloads). */
    public function role_key(): string
    {
        return $this->getRoleNames()->first() ?? 'cashier';
    }
}
