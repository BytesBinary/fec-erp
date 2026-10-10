<?php

namespace Database\Seeders;

use App\Models\ProfileRequiredField;
use Illuminate\Database\Seeder;

/**
 * Adds a row for every known profile field. Existing rows (admin choices)
 * are left untouched, so new fields appear without overwriting settings.
 */
class ProfileRequiredFieldSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('profile.fields') as $key => $definition) {
            ProfileRequiredField::query()->firstOrCreate(['field_key' => $key], ['required' => $definition['required'], 'active' => true]);
        }
    }
}
