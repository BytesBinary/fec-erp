<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstitutionSetting extends Model
{
    protected $fillable = [
        'institution_name',
        'short_name',
        'logo_path',
        'address',
        'phone',
        'email',
        'website',
        'principal_name',
        'principal_title',
        'principal_signature_path',
    ];

    public static function current(): self
    {
        return static::firstOrCreate([], ['institution_name' => 'My Institution']);
    }

    public function logoAbsPath(): ?string
    {
        return $this->logo_path ? storage_path('app/public/'.$this->logo_path) : null;
    }

    public function signatureAbsPath(): ?string
    {
        return $this->principal_signature_path ? storage_path('app/public/'.$this->principal_signature_path) : null;
    }
}
