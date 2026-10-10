<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileRequiredField extends Model
{
    protected $fillable = [
        'field_key',
        'required',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'active' => 'boolean',
        ];
    }
}
