<?php

namespace App\Enums;

use Filament\Support\Colors\Color;

enum ThemePreset: string
{
    case ForestOchre = 'forest-ochre';
    case OceanBlue = 'ocean-blue';
    case RoyalPlum = 'royal-plum';
    case CrimsonClay = 'crimson-clay';
    case MidnightIndigo = 'midnight-indigo';
    case Emerald = 'emerald';

    public function label(): string
    {
        return match ($this) {
            self::ForestOchre => 'Forest & Ochre',
            self::OceanBlue => 'Ocean Blue',
            self::RoyalPlum => 'Royal Plum',
            self::CrimsonClay => 'Crimson Clay',
            self::MidnightIndigo => 'Midnight Indigo',
            self::Emerald => 'Emerald',
        };
    }

    /** Default accent color for this preset, overridable per-user. */
    public function primaryColor(): string
    {
        return match ($this) {
            self::ForestOchre => '#C1652F',
            self::OceanBlue => '#2F7FC1',
            self::RoyalPlum => '#8B5CF6',
            self::CrimsonClay => '#C0392B',
            self::MidnightIndigo => '#6366F1',
            self::Emerald => '#10B981',
        };
    }

    /** @return array<int, string>|string */
    public function grayColor(): array|string
    {
        return match ($this) {
            self::ForestOchre => Color::Stone,
            self::OceanBlue => Color::Slate,
            self::RoyalPlum => Color::Zinc,
            self::CrimsonClay => Color::Neutral,
            self::MidnightIndigo => Color::Slate,
            self::Emerald => Color::Zinc,
        };
    }

    /** Dark surface shared by the sidebar and topbar. */
    public function navColor(): string
    {
        return match ($this) {
            self::ForestOchre => '#1f3d2e',
            self::OceanBlue => '#0f2a3d',
            self::RoyalPlum => '#241530',
            self::CrimsonClay => '#241a17',
            self::MidnightIndigo => '#1c1f2e',
            self::Emerald => '#0b2e23',
        };
    }

    /** A deeper variant of {@see navColor()}, used for the login page's dark-mode gradient. */
    public function navColorDeep(): string
    {
        return match ($this) {
            self::ForestOchre => '#16281d',
            self::OceanBlue => '#0a1d2b',
            self::RoyalPlum => '#190e21',
            self::CrimsonClay => '#19120f',
            self::MidnightIndigo => '#121420',
            self::Emerald => '#072018',
        };
    }

    /** Page background in light mode. */
    public function contentColor(): string
    {
        return match ($this) {
            self::ForestOchre => '#f6f1e7',
            self::OceanBlue => '#eef5fb',
            self::RoyalPlum => '#f6f1fb',
            self::CrimsonClay => '#faf2f0',
            self::MidnightIndigo => '#f1f2f8',
            self::Emerald => '#eefaf5',
        };
    }

    /** Page background in dark mode. */
    public function contentColorDark(): string
    {
        return match ($this) {
            self::ForestOchre => '#1a2420',
            self::OceanBlue => '#0b1e2b',
            self::RoyalPlum => '#190f21',
            self::CrimsonClay => '#1c1310',
            self::MidnightIndigo => '#14161f',
            self::Emerald => '#081f18',
        };
    }

    /** @return array<int, array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(
            fn (self $preset): array => ['value' => $preset->value, 'label' => $preset->label()],
            self::cases(),
        );
    }
}
