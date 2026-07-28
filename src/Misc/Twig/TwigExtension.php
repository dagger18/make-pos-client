<?php

namespace App\Misc\Twig;

use App\Module\Core\Entity\Port;
use App\Module\Finance\Repository\CurrencyRepository;
use App\Module\Finance\Repository\ExchangeRateGroupRepository;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension
{
    public function __construct(
        protected Environment $twig,
        protected ExchangeRateGroupRepository $exchangeRateGroupRepository,
        protected NormalizerInterface $serializer,
        protected CurrencyRepository $currencyRepository,
    ) {
    }
    public function getFilters()
    {
        return [
            new TwigFilter('date', [$this, 'dateFilter']),
            new TwigFilter('removeContentInsideRoundBrackets', [$this, 'removeContentInsideRoundBrackets']),
            new TwigFilter('format_money', [$this, 'formatMoney']),
            new TwigFilter('sellingExchangeRates', [$this, 'sellingExchangeRates']),
        ];
    }
    public function getFunctions(): array
    {
        return [
            new TwigFunction('printPort', [$this, 'printPort']),
            new TwigFunction('printDoorCode', [$this, 'printDoorCode']),
            new TwigFunction('printFullname', [$this, 'printFullname']),
            new TwigFunction('summarizeContainers', [$this, 'summarizeContainers']),
            new TwigFunction('enum', [$this, 'createProxy']),
            new TwigFunction('currentExchangeRateGroup', [$this, 'getCurrentExchangeRateGroup']),
            new TwigFunction('currencyFractionDigits', [$this, 'currencyFractionDigits']),
            new TwigFunction('convertMoneyUsingRate', [$this, 'convertMoneyUsingRate']),
        ];
    }

    public function convertMoneyUsingRate(float|int|null $amount,float $fromRate, float $toRate): float|int|null
    {
        if ($amount === null) return null;
        return ($amount / $fromRate) * $toRate;
    }
    public function getCurrentExchangeRateGroup(): ?array
    {
        return $this->serializer->normalize(
            $this->exchangeRateGroupRepository->findOneBy([], ['id' => 'DESC']) ?? null,
            'json',
            ['groups' => ['currentExchangeRates']]
        );
    }

    /** @param array<string,string|float> $exchangeRates @param array<string,array{selling:mixed,buying:mixed}> $subTotal */
    public function sellingExchangeRates(array $exchangeRates, array $subTotal): array
    {
        return array_filter(
            $exchangeRates,
            static fn($rate, $code) => ($subTotal[$code]['selling'] ?? 0) > 0,
            ARRAY_FILTER_USE_BOTH
        );
    }

    public function formatMoney(float|int|null $amount, string $currencyCode): string
    {
        if ($amount === null) return '';
        $currency = $this->currencyRepository->findOneBy(['code' => $currencyCode]);
        $decimalPlaces = $currency?->getDecimalPlaces() ?? $this->currencyFractionDigits($currencyCode);
        $decimalSeparator = $currency?->getDecimalSeparator() ?? '.';
        $thousandSeparator = $currency?->getThousandSeparator() ?? ',';
        return number_format((float) $amount, $decimalPlaces, $decimalSeparator, $thousandSeparator);
    }

    public function currencyFractionDigits(string $currencyCode): int
    {
        $zeroCurrencies = ['JPY','KRW','IDR','UZS','MNT','KHR','VND','BIF','RWF','GNF','DJF','MGA','PYG','VUV','ISK'];
        return in_array(strtoupper($currencyCode), $zeroCurrencies, true) ? 0 : 2;
    }

    public function summarizeContainers($containers) {
      return ['packages' => 0, 'weight' => 0, 'volume' => 0];
    }
    public function removeContentInsideRoundBrackets($string): string
    {
        return preg_replace("/\s?\([a-zA-Z0-9]+\)\s?/",'', $string);
    }
    public function dateFilter($timestamp, string $format = 'd/m/Y'): string
    {
        if ($timestamp !== null) {
            return twig_date_format_filter($this->twig, $timestamp, $format);
        }

        return '';
    }
    public function printPort(?Port $port) {
        if(is_null($port)) return '';
        return implode(', ', [$port->getName(),$port->getSubdiv() . ' (' . $port->getCode() . ')']);
    }
    public function printDoorCode($door) {
        $parts = explode('(', $door);
        if(isset($parts[1])) {
            return str_replace(')', '', $parts[1]);
        }

        return preg_replace('~\S\K\S*\s*~u', '', strtoupper($door));
    }
    public function printFullname($person) {
        return implode(' ', [$person->getFirstName(),$person->getLastName()]);
    }
    private static array $enumMap = [
        'AddressType'          => 'App\Module\Core\Enum\AddressType',
        'ComponentType'        => 'App\Module\Core\Enum\ComponentType',
        'Country'              => 'App\Module\Core\Enum\Country',
        'DateRange'            => 'App\Module\Core\Enum\DateRange',
        'DateSegment'          => 'App\Module\Core\Enum\DateSegment',
        'EntityType'           => 'App\Module\Core\Enum\EntityType',
        'MediaCategory'        => 'App\Module\Core\Enum\MediaCategory',
        'PageType'             => 'App\Module\Core\Enum\PageType',
        'Permission'           => 'App\Module\Core\Enum\Permission',
        'PortType'             => 'App\Module\Core\Enum\PortType',
        'RequestMethod'        => 'App\Module\Core\Enum\RequestMethod',
        'ServiceType'          => 'App\Module\Core\Enum\ServiceType',
        'TransportType'        => 'App\Module\Core\Enum\TransportType',
        'UserStatus'           => 'App\Module\Core\Enum\UserStatus',
        'VisibleTo'            => 'App\Module\Core\Enum\VisibleTo',
        'VolumeType'           => 'App\Module\Core\Enum\VolumeType',
        'WeekDay'              => 'App\Module\Core\Enum\WeekDay',
        'Magnum'               => 'App\Module\Core\Enum\Magnum',
        'ChargeType'           => 'App\Module\Finance\Enum\ChargeType',
        'CreditNoteReason'     => 'App\Module\Finance\Enum\CreditNoteReason',
        'CreditStatus'         => 'App\Module\Finance\Enum\CreditStatus',
        'EbitNoteStatus'       => 'App\Module\Finance\Enum\EbitNoteStatus',
        'EbitNoteType'         => 'App\Module\Finance\Enum\EbitNoteType',
        'LocalChargeType'      => 'App\Module\Finance\Enum\LocalChargeType',
        'PayableAt'            => 'App\Module\Finance\Enum\PayableAt',
        'PaymentMethodType'    => 'App\Module\Finance\Enum\PaymentMethodType',
        'VarianceStatus'       => 'App\Module\Finance\Enum\VarianceStatus',
        'ClientCustomInfoMode' => 'App\Module\Crm\Enum\ClientCustomInfoMode',
        'ClientResidenceType'  => 'App\Module\Crm\Enum\ClientResidenceType',
        'ClientTier'           => 'App\Module\Crm\Enum\ClientTier',
        'ClientType'           => 'App\Module\Crm\Enum\ClientType',
        'MailStatus'           => 'App\Module\Notification\Enum\MailStatus',
        'DatasetGroupColumn'   => 'App\Module\Reporting\Enum\DatasetGroupColumn',
        'DatasetRowType'       => 'App\Module\Reporting\Enum\DatasetRowType',
    ];

    public function createProxy(string $enumFQN): object
    {
        $resolved = self::$enumMap[$enumFQN] ?? throw new \InvalidArgumentException("Unknown enum: $enumFQN");
        return new class($resolved) {
            public function __construct(private readonly string $enum)
            {
                if (!enum_exists($this->enum)) {
                    throw new \InvalidArgumentException("$this->enum is not an Enum type and cannot be used in this function");
                }
            }

            public function __call(string $name, array $arguments)
            {
                $enumFQN = sprintf('%s::%s', $this->enum, $name);

                if (defined($enumFQN)) {
                    return constant($enumFQN);
                }

                if (method_exists($this->enum, $name)) {
                    return $this->enum::$name(...$arguments);
                }

                throw new \BadMethodCallException("Neither \"{$enumFQN}\" nor \"{$enumFQN}::{$name}()\" exist in this runtime.");
            }
        };
    }
}
