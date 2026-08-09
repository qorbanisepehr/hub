<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            Locations\ProvinceSeeder::class,
            Locations\CitySeeder::class,
        ]);
    }
}
