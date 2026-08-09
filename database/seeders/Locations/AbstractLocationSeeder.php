<?php

namespace Database\Seeders\Locations;

use App\Domains\FormOptions\Models\FormOption;
use App\Domains\FormOptions\Services\FormOptionService;
use Illuminate\Database\Seeder;

abstract class AbstractLocationSeeder extends Seeder
{
    abstract protected function group(): string;

    abstract protected function dataFile(): string;

    public function run(): void
    {
        $rows = json_decode(
            file_get_contents(database_path('seeders/data/locations/'.$this->dataFile())),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        foreach (array_chunk($rows, 1000) as $chunk) {
            FormOption::query()->upsert($this->withDefaults($chunk), ['group', 'value']);
        }

        app(FormOptionService::class)->flush($this->group());
    }

    /**
     * @param  array<int, array<string, mixed>>  $chunk
     * @return array<int, array<string, mixed>>
     */
    private function withDefaults(array $chunk): array
    {
        $now = now()->toDateTimeString();

        return array_map(fn (array $row): array => array_merge($row, [
            'group' => $this->group(),
            'is_active' => true,
            'meta' => isset($row['meta']) ? json_encode($row['meta'], JSON_THROW_ON_ERROR) : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]), $chunk);
    }
}
