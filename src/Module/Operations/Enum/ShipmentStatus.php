<?php
namespace App\Module\Operations\Enum;

use Symfony\Contracts\Translation\TranslatorInterface;
enum ShipmentStatus: string {
    case Draft = 'DR'; // only owner see
    case Pending = 'PE';
    case Active = 'AC';
    case Completed = 'CO';
    case Cancelled = 'CA';

    // not used ?
    case Deleted = 'DE';
    case Archived = 'AR';

    public function trans(TranslatorInterface $translator, string $locale = 'en'): string
    {
        return match ($this) {
            self::Draft     => $translator->trans('Draft', locale: $locale),
            self::Pending   => $translator->trans('Pending', locale: $locale),
            self::Active => $translator->trans('Active', locale: $locale),
            self::Completed => $translator->trans('Completed', locale: $locale),
            self::Cancelled => $translator->trans('Cancelled', locale: $locale),
        };
    }
}