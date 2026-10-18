<?php

use App\Support\Exports\Templates\WordTemplate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Drop `has_placeholders` from the stored Word templates.
 *
 * The flag classified a template at upload as one that REPLACES the generated
 * document or one that merely frames it, and choosing the wrong path produced a
 * letterhead with no meeting on it rather than an error. Nothing asks for the
 * distinction any more, so the stored rows are saying something untrue about
 * these files.
 *
 * A SETTINGS VALUE, NOT A TABLE. The key does not change and the value is plain
 * JSON in one column, so this rewrites a key inside a document rather than
 * reshaping a schema — and it reads and writes the column directly instead of
 * going through the repository, because the class that owns this value no longer
 * knows the key exists and would only have preserved it by accident.
 *
 * Rows that never had it are left alone, and the loop is scoped to the `word/`
 * namespace so a section template sharing the `exports` group is never touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rows = DB::table('system_settings')
            ->where('group', WordTemplate::GROUP)
            ->where('key', 'like', WordTemplate::PREFIX.'%')
            ->get(['key', 'value']);

        foreach ($rows as $row) {
            $value = is_string($row->value) ? json_decode($row->value, true) : $row->value;

            if (! is_array($value) || ! array_key_exists('has_placeholders', $value)) {
                continue;
            }

            unset($value['has_placeholders']);

            DB::table('system_settings')
                ->where('key', $row->key)
                ->update(['value' => json_encode($value)]);
        }
    }

    /**
     * The flag is not restorable: nothing reads it, so putting it back would
     * restore a lie about what these files contain rather than any behaviour.
     */
    public function down(): void
    {
        //
    }
};
