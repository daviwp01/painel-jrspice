<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'phone',
        'company_name',
        'tenant_id',
        'allowed_pages',
        'last_login_at',
        'last_activity_at',
        'email_notified_at',
        'email_clicked_at',
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
            'is_master' => 'boolean',
            'is_active' => 'boolean',
            'allowed_pages' => 'array',
            'last_login_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'email_notified_at' => 'datetime',
            'email_clicked_at' => 'datetime',
        ];
    }
    /**
     * Check if the user is allowed to access a specific dashboard page (by slug).
     *
     * @param string $slug
     * @return bool
     */
    public function canAccessPage(string $slug): bool
    {
        if ($this->is_master) {
            return true;
        }

        $allowed = $this->allowed_pages;
        
        // Se a lista individual estiver vazia, utiliza os padrões do sistema
        if (empty($allowed) || !is_array($allowed)) {
            $allowed = \App\Models\Setting::get('default_allowed_pages', []);
        }
        
        return in_array($slug, $allowed);
    }

    /**
     * Get the user's tracked sessions.
     */
    public function sessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\UserSession::class);
    }
}
