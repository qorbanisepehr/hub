<?php

namespace Database\Seeders\Locations;

class ProvinceSeeder extends AbstractLocationSeeder
{
    protected function group(): string
    {
        return 'province';
    }

    protected function dataFile(): string
    {
        return 'provinces.json';
    }
}
