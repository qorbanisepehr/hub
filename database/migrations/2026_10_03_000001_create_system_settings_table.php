<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One table for system configuration.
 *
 * A row is one addressable thing, and its key *is* its identity: there is no
 * surrogate id, because the thing a caller names is the thing stored. That is
 * what lets the primary key do work a separate column would only duplicate —
 * a provider named `local` cannot also be stored as `Local`, because there is
 * one row per `(group, key)` and no second place to disagree with.
 *
 * `value` is the public document and `secrets` is the private one. They are
 * split rather than merged because that is what keeps a credential from
 * becoming "one more thing inside a bag": `secrets` is a real column, so the
 * encrypted cast applies to it and `$hidden` keeps it out of every
 * serialization, which is a guarantee the model enforces rather than a
 * convention each reader has to remember.
 *
 * `secrets` is `text`, not `jsonb`, and that is deliberate: an encrypted value
 * is bare base64, which is not valid JSON, so a jsonb column rejects it. The
 * column is text and the cast rehydrates the array on read.
 *
 * There is no `type` column. The type of a setting is a property of its key,
 * and the keys are enumerated in PHP by the registry that owns them; a second
 * list in the schema could disagree with the first, and "the row claims secret
 * but the registry does not treat it as secret" is precisely the failure that
 * would leak a key.
 *
 * There is no CHECK constraint on `group`/`key` for the same reason: listing
 * the legal keys twice is how they fall out of step. One list, in one place.
 *
 * Nothing here is a foreign key because nothing here is an entity. `updated_by`
 * is the only reference, and it is `SET NULL` on delete so removing a user
 * leaves the setting alone and just loses the attribution.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->string('group');
            $table->string('key');
            $table->jsonb('value')->default('{}');
            $table->text('secrets')->nullable();
            $table->timestamps();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->primary(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
