<?php

namespace Database\Seeders;

use App\Models\InstitutionSetting;
use Illuminate\Database\Seeder;

class InstitutionSettingSeeder extends Seeder
{
    public function run(): void
    {
        InstitutionSetting::firstOrCreate([], [
            'institution_name' => 'My Institution',
            'short_name' => 'MI',
        ]);
    }
}
