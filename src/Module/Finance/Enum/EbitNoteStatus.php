<?php
namespace App\Module\Finance\Enum;

use Symfony\Contracts\Translation\TranslatorInterface;
enum EbitNoteStatus: string {
    case Pending = 'P';
    case Sent = 'S';
    case Active = 'A';
    case Done = 'D';
    public function trans(TranslatorInterface $translator, string $locale = 'en'): string
    {
        return match ($this) {
            self::Pending   => $translator->trans('Pending', locale: $locale),
            self::Active => $translator->trans('Active', locale: $locale),
            self::Sent => $translator->trans('Sent', locale: $locale),
            self::Done => $translator->trans('Done', locale: $locale),
        };
    }
}