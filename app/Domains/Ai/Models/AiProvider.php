<?php

namespace App\Domains\Ai\Models;

use App\Domains\Ai\Enums\AiDriver;
use Database\Factories\Ai\AiProviderFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bring-your-own-key credential (D19). The hub owns credentials because
 * they carry policy — which driver, which model, who may see them — and
 * that policy is business state, not deployment config.
 *
 * `api_key` is encrypted at rest by the cast, so it is write-only in
 * practice: `hasApiKey()` is what a reader may know, never the key itself.
 * That is the whole reason `ai.provider.view` and `ai.provider.manage`
 * are separate permissions (R4).
 *
 * At most one row is the default, enforced in {@see booted()} rather than
 * by a partial unique index: SQLite — which the test suite runs on — drops
 * the `where` clause of an index, so the constraint would hold in tests and
 * not in Postgres. A default is what an agent inherits when it names no
 * provider (A2), so promoting a row demotes the previous default, and
 * having no default is a supported state (D24: nothing configured breaks
 * nothing).
 */
#[UseFactory(AiProviderFactory::class)]
final class AiProvider extends Model
{
    /** @use HasFactory<AiProviderFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'driver',
        'base_url',
        'api_key',
        'headers',
        'is_active',
        'is_default',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'driver' => AiDriver::class,
            'api_key' => 'encrypted',
            'headers' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Promoting a provider demotes the previous default, so "the default"
     * is a single row without needing a partial unique index. The update is
     * skipped when nothing else claims the flag, which is the common case
     * and keeps a normal save to one query.
     */
    protected static function booted(): void
    {
        self::saving(function (self $provider): void {
            if (! $provider->is_default) {
                return;
            }

            self::query()
                ->whereKeyNot($provider->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }

    /**
     * @return HasMany<AiModel, $this>
     */
    public function models(): HasMany
    {
        return $this->hasMany(AiModel::class, 'provider_id');
    }

    /**
     * @return HasMany<AiAgent, $this>
     */
    public function agents(): HasMany
    {
        return $this->hasMany(AiAgent::class, 'provider_id');
    }

    /** Whether a credential is stored. Never reveals the key. */
    public function hasApiKey(): bool
    {
        return filled($this->api_key);
    }
}
