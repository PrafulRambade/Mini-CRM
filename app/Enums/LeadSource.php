<?php

namespace App\Enums;

enum LeadSource: string
{
    case Web = 'web';
    case Ads = 'ads';
    case Referral = 'referral';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web',
            self::Ads => 'Ads',
            self::Referral => 'Referral',
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
