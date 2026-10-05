<?php

namespace App\Domains\Settings\Models;

use App\Domains\Settings\Repositories\SystemSettingsRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One row of system configuration: the group it belongs to, the key that
 * identifies it, its public value, and its private value.
 *
 * The identity is the `(group, key)` pair and there is no id, which makes this
 * model deliberately not persistable through Eloquent. `save()` on a composite
 * primary key builds `where(['group', 'key'], '=', [...])`, and the query
 * builder reads that shape as key/value pairs rather than two columns, so it
 * dies with a `TypeError` instead of writing. Every write therefore goes
 * through {@see SystemSettingsRepositoryInterface},
 * which addresses rows by their key and upserts them. Reads through Eloquent
 * are fine and are what give this class its casts.
 *
 * `secrets` is encrypted at rest by the cast and hidden from serialization, so
 * a credential in this table cannot leave the server by accident — the same
 * guarantee a plain column gives, which is the reason it is a column and not
 * a key inside `value`.
 *
 * @property string $group
 * @property string $key
 * @property array<string, mixed> $value
 * @property array<string, string>|null $secrets
 * @property Carbon|null $updated_at
 * @property int|null $updated_by
 */
final class SystemSetting extends Model
{
    /** No surrogate key: the pair below is the identity. */
    public $incrementing = false;

    /** @var list<string> */
    protected $primaryKey = ['group', 'key'];

    protected $fillable = [
        'group',
        'key',
        'value',
        'secrets',
        'updated_by',
    ];

    /** @var list<string> */
    protected $hidden = [
        'secrets',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'array',
            // Encrypted, not plain: the column holds base64 ciphertext, which
            // is why it is `text` in the migration rather than jsonb.
            'secrets' => 'encrypted:array',
        ];
    }

    /** Whether a private value is stored. Never reveals it. */
    public function hasSecrets(): bool
    {
        return filled($this->secrets);
    }
}
