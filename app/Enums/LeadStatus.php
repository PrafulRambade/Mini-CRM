<?php

namespace App\Enums;

enum LeadStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::InProgress => 'In Progress',
            self::Won => 'Won',
            self::Lost => 'Lost',
        };
    }

    /**
     * Bootstrap contextual colour used by the UI badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::New => 'secondary',
            self::InProgress => 'primary',
            self::Won => 'success',
            self::Lost => 'danger',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
