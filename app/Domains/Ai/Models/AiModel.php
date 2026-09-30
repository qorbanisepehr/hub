<?php

namespace App\Domains\Ai\Models;

use App\Casts\AiCapabilityListCast;
use Database\Factories\Ai\AiModelFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A model reachable through one provider. `model_name` is the name the
 * provider itself knows the model by (`gpt-4o-mini`), not this row's id,
 * which is why it is unique per provider rather than globally: the same
 * string can mean different things behind two gateways. `label` is the
 * optional presentation name for it.
 *
 * `capabilities` is a typed list rather than free text so that binding an
 * agent to a model can be checked (A2) instead of being discovered on the
 * first paid request.
 */
#[UseFactory(AiModelFactory::class)]
final class AiModel extends Model
{
    /** @use HasFactory<AiModelFactory> */
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'model_name',
        'label',
        'capabilities',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => AiCapabilityListCast::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<AiProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }

    /**
     * @return HasMany<AiAgent, $this>
     */
    public function agents(): HasMany
    {
        return $this->hasMany(AiAgent::class);
    }

    public function displayLabel(): string
    {
        return $this->label ?? $this->model_name;
    }
}
