<?php

namespace Database\Seeders\Locations;

class CitySeeder extends AbstractLocationSeeder
{
    protected function group(): string
    {
        return 'city';
    }

    protected function dataFile(): string
    {
        return 'cities.json';
    }
}
