<?php

namespace App\Filament\Resources\NotificationRules\Pages;

use App\Filament\Resources\NotificationRules\NotificationRuleResource;
use Filament\Resources\Pages\ListRecords;

class ListNotificationRules extends ListRecords
{
    protected static string $resource = NotificationRuleResource::class;

    public function getSubheading(): ?string
    {
        return 'Choose which events send an email, to whom, and with which text. Changes apply immediately; nothing needs a deployment.';
    }
}
