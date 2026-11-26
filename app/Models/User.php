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
        'phone_country_code',
        'company_name',
        'cargo',
        'import_experience',
        'import_volume',
        'decision_role',
        'client_id',
        'tenant_id',
        'allowed_pages',
        'last_login_at',
        'last_activity_at',
        'email_notified_at',
        'email_clicked_at',
        'registered_via_external_form',
        'form_source',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'gclid',
        'fbclid',
        'msclkid',
        'terms_accepted_at',
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
            'terms_accepted_at' => 'datetime',
            'password' => 'hashed',
            'is_master' => 'boolean',
            'is_active' => 'boolean',
            'allowed_pages' => 'array',
            'last_login_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'email_notified_at' => 'datetime',
            'email_clicked_at' => 'datetime',
            'registered_via_external_form' => 'boolean',
        ];
    }

    /**
     * Check if the user is allowed to access a specific dashboard page (by slug).
     */
    public function canAccessPage(string $slug): bool
    {
        if ($this->is_master) {
            return true;
        }

        $allowed = $this->allowed_pages;

        // Se a lista individual estiver vazia, utiliza os padrões do sistema
        if (empty($allowed) || ! is_array($allowed)) {
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

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Meus Produtos só aparece quando o usuário está vinculado a uma empresa
     * que tenha ao menos um contrato (exportador ou importador). Vale para
     * masters também: sem vínculo, não há o que ver no portal.
     */
    public function canSeeMyProducts(): bool
    {
        if ($this->client_id === null) {
            return false;
        }

        return Contract::query()
            ->where(function ($q) {
                $q->where('exporter_id', $this->client_id)
                    ->orWhere('importer_id', $this->client_id);
            })
            ->exists();
    }

    public function canAccessContract(Contract $contract): bool
    {
        if ($this->is_master) {
            return true;
        }

        if ($this->client_id === null) {
            return false;
        }

        return $contract->exporter_id === $this->client_id
            || $contract->importer_id === $this->client_id;
    }

    public static function getImportExperienceOptions(): array
    {
        return [
            'Sim, importamos diretamente com o fornecedor',
            'Sim, através de trading ou broker',
            'Não, compramos no mercado interno',
            'Estamos avaliando começar',
        ];
    }

    public static function getImportVolumeOptions(): array
    {
        return [
            'Contêiner cheio, com frequência',
            'Contêiner cheio, esporadicamente',
            'Menos de um contêiner',
            'Ainda não sei',
        ];
    }

    public static function getDecisionRoleOptions(): array
    {
        return [
            'Decido a compra',
            'Participo da decisão',
            'Pesquiso e levo para aprovação',
            'Outro',
        ];
    }
}
