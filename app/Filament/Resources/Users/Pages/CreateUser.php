<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Concerns\SavesThroughDomainService;
use App\Filament\Resources\Users\UserResource;
use App\Services\Users\UserService;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    use SavesThroughDomainService;

    protected static string $resource = UserResource::class;

    protected static function domainService(): string
    {
        return UserService::class;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
