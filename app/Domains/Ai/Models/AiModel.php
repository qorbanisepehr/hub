<?php

namespace App\Domains\Ai\Models;

use App\Casts\AiCapabilityListCast;
use Database\Factories\Ai\AiModelFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'capabilities' => AiCapabilityListCast::class,
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Demote this provider's other defaults when this row becomes the default.
     *
     * The partial index `ai_models_one_default_per_provider` is what actually
     * guarantees the invariant; this hook exists so that "set as default" also
     * *demotes* the previous one, which an index cannot do. Mirrors
     * {@see AiProvider}'s own hook, and two details are load-bearing: it runs on
     * `saving` rather than `saved`, because the sibling has to be released
     * *before* the database will accept this row, and it is scoped to
     * `provider_id` so one gateway's choice never touches another's.
     */
    protected static function booted(): void
    {
        self::saving(function (self $model): void {
            if (! $model->is_default) {
                return;
            }

            self::query()
                ->where('provider_id', $model->provider_id)
                ->whereKeyNot($model->getKey())
                ->where('is_default', true)
                ->update(['is_default' => false]);
        });
    }

    /**
     * @return BelongsTo<AiProvider, $this>
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(AiProvider::class);
    }

    public function displayLabel(): string
    {
        return $this->label ?? $this->model_name;
    }
}
