<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Section;
use Filament\Schemas\Schema;

class EditProfile extends BaseEditProfile
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Personal Information')
                    ->columns(2)
                    ->schema([
                        $this->getNameFormComponent()
                            ->columnSpan(1),
                        $this->getEmailFormComponent()
                            ->columnSpan(1),
                    ]),

                Section::make('Change Password')
                    ->description('Leave blank to keep current password.')
                    ->columns(2)
                    ->schema([
                        $this->getPasswordFormComponent()
                            ->columnSpan(1),
                        $this->getPasswordConfirmationFormComponent()
                            ->columnSpan(1),
                    ]),
            ]);
    }
}
