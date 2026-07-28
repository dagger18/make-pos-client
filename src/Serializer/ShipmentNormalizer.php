<?php
namespace App\Serializer;
use NumberFormatter;
use App\Module\Core\Entity\Media;
use App\Module\Operations\Entity\Shipment;
use App\Module\Core\Entity\UserGroup;
use App\Module\Core\Enum\Magnum;
use App\Module\Core\Enum\MediaCategory;
use Psr\Log\LoggerInterface;
use App\Module\Core\Service\CommonService;
use App\Module\Core\Enum\ServiceType;
use App\Module\Core\Enum\TransportType;
use App\Module\Operations\Enum\ShipmentStatus;
use App\Module\Finance\Service\ExchangeRateGroupService;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\Lazy;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

class ShipmentNormalizer implements NormalizerInterface
{
  public function __construct(
    #[Lazy]
    protected ExchangeRateGroupService $exchangeRateGroupService,
    #[Autowire(service: 'serializer.normalizer.object')]
    protected ObjectNormalizer $normalizer,
    protected LoggerInterface $logger,
    protected TranslatorInterface $translator,
    protected RequestStack $requestStack,
    #[Lazy]
    protected CommonService $commonService
  ) {
  }
  /** @param Shipment $object */
  public function normalize($object, string $format = null, array $context = []): array
  {
      $exchangeRateGroupService = $this->exchangeRateGroupService;
      // header('Access-Control-Allow-Origin: *');dd($context);
      $data = $this->normalizer->normalize(
        $object, 
        $format, 
        $context
      );
      $currency = $context['reportCurrency'] ?? 'VND';
      $data['quote'] = [
        'originPort' => null,
        'destinationPort' => null,
      ];
      if($object->getQuote()->getOriginPort()) {
        $data['quote']['originPort'] = [
          'id' => $object->getQuote()->getOriginPort()->getId(),
          'name' => $object->getQuote()->getOriginPort()->getName(),
          'code' => $object->getQuote()->getOriginPort()->getCode(),
          'subdiv' => $object->getQuote()->getOriginPort()->getSubdiv(),
          'displayName' => $object->getQuote()->getOriginPort()->getDisplayName(),
          'codeFull' => $object->getQuote()->getOriginPort()->getCodeFull(),
        ];
      }
      if($object->getQuote()->getDestinationPort()) {
        $data['quote']['destinationPort'] = [
          'id' => $object->getQuote()->getDestinationPort()->getId(),
          'name' => $object->getQuote()->getDestinationPort()->getName(),
          'code' => $object->getQuote()->getDestinationPort()->getCode(),
          'subdiv' => $object->getQuote()->getDestinationPort()->getSubdiv(),
          'displayName' => $object->getQuote()->getDestinationPort()->getDisplayName(),
          'codeFull' => $object->getQuote()->getDestinationPort()->getCodeFull(),
        ];
      }
      // sort price and get first one

      $firstQuotePrice = $object->getQuote()->getPrices()->reduce(function ($a, $b) {
        if(is_null($a)) return $b;
        if($a->getPosition() && $b->getPosition()) {
          return $a->getPosition() < $b->getPosition() ? $a : $b;
        }
        return $a->getId() < $b->getId() ? $a : $b;
      });
      if($firstQuotePrice) {
        $data['quote']['buying'] = $exchangeRateGroupService->formatMoney(
                                      $firstQuotePrice->getBuying(), 
                                      $currency, 
                                      $object->getQuote()->getExchangeRates()
                                    );
        $data['quote']['selling'] = $exchangeRateGroupService->formatMoney(
                                    $firstQuotePrice->getSelling(), 
                                    $currency, 
                                    $object->getQuote()->getExchangeRates()
                                  );
      } else {
        $data['quote']['buying'] = 0;
        $data['quote']['selling'] = 0;
      }
      
      $data['accountManager'] = [
        'id' => $object->getAccountManager()->getId(),
        'firstName' => $object->getAccountManager()->getFirstName(),
        'lastName' => $object->getAccountManager()->getLastName(),
      ];
      $data['salesRep'] = $object->getSalesRep() ? [
        'id' => $object->getSalesRep()->getId(),
        'firstName' => $object->getSalesRep()->getFirstName(),
        'lastName' => $object->getSalesRep()->getLastName(),
      ] : null;
      $data['subStatus'] = $object->getSubStatus()?->value;
      $data['isOnHold'] = $object->isOnHold();
      $data['consolId'] = $object->getConsolId();
      $data['parentJobId'] = $object->getParentJobId();
      $data['accountingClosedAt'] = $object->getAccountingClosedAt()?->format('Y-m-d H:i:s');
      $data['createdBy'] = [
        'id' => $object->getCreatedBy()->getId(),
        'firstName' => $object->getCreatedBy()->getFirstName(),
        'lastName' => $object->getCreatedBy()->getLastName(),
      ];
      $data['shipmentMode'] = $object->getQuote()->getShipmentMode()?->getName();
      $data['transportType'] = $object->getQuote()->getTransportType();
      $data['serviceType'] = $object->getQuote()->getServiceType();
      $data['cargoVolumeType'] = $object->getQuote()->getCargoVolumeType();

      $serviceType = $object->getQuote()->getServiceType();
      if($data['transportType'] === TransportType::Air
        || ($data['transportType'] === TransportType::Road && $serviceType === ServiceType::LTL)
        || ($data['transportType'] === TransportType::Road && $serviceType === ServiceType::Groupage)
      ) {
        $quoteCargo = $object->getQuote()->getCargoVolume();
        $bookingCargo = $object->getBooking()->getCargoVolume();
        $instructionCargo = [
          'chargeableWeight' => $object->getInstruction()->getChargeableWeight(),
          'totalWeight' => $object->getInstruction()->getGrossWeight(),
          'totalUnit' => $object->getInstruction()->getPackageCount(),
          'totalCBM' => $object->getInstruction()->getVolume(),
        ];
        if(!isset($quoteCargo['totalUnit'])) {
          //header('Access-Control-Allow-Origin: *');dd($quoteCargo, $data);
        }
        // check AIR_2368_SGN_JFK
        $data['cargoVolume'] = [
          'chargeableWeight' => empty($instructionCargo['chargeableWeight']) 
                            ? ($quoteCargo['chargeableWeight'] ?? 0)
                            : $instructionCargo['chargeableWeight'],
          'totalWeight' => empty($instructionCargo['totalWeight']) 
                        ? ($quoteCargo['totalWeight'] ?? ($bookingCargo['totalWeight'] ?? 0))
                        : $instructionCargo['totalWeight'],
          'totalUnit' => empty($instructionCargo['totalUnit']) 
                        ? ($quoteCargo['totalUnit'] ?? ($bookingCargo['totalUnit'] ?? 0))
                        : $instructionCargo['totalUnit'],
          'totalCBMNoNeed' => empty($instructionCargo['totalCBM']) 
                        ? ($quoteCargo['totalCBM'] ?? ($bookingCargo['totalCBM'] ?? 0))
                        : $instructionCargo['totalCBM'],
        ];
        
        $data['cargoVolume']['text'] = $data['cargoVolume']['chargeableWeight'];
      } else if($data['transportType'] === TransportType::Ocean && $serviceType === ServiceType::LCL) {
        $quoteCargo = $object->getQuote()->getCargoVolume();
        $bookingCargo = $object->getBooking()->getCargoVolume();
        // check LCL_2373_HPH_KEL
        $data['cargoVolume'] = [
          'chargeableWeight' => empty($bookingCargo['chargeableWeight']) 
                            ? ($quoteCargo['chargeableWeight'] ?? 0)
                            : $bookingCargo['chargeableWeight'],
          'totalWeight' => empty($bookingCargo['totalWeight']) 
                        ? ($quoteCargo['totalWeight'] ?? 0)
                        : $bookingCargo['totalWeight'],
          'totalUnit' => empty($bookingCargo['totalUnit']) 
                        ? ($quoteCargo['totalUnit'] ?? 0)
                        : $bookingCargo['totalUnit'],
          'totalCBM' => empty($bookingCargo['totalCBM']) 
                        ? $quoteCargo['totalCBM']
                        : $bookingCargo['totalCBM'],
        ];
        
        $data['cargoVolume']['text'] = $data['cargoVolume']['totalCBM'];
      } else if(
        ($data['transportType'] === TransportType::Ocean && $serviceType === ServiceType::FCL)
        || ($data['transportType'] === TransportType::Road && $serviceType === ServiceType::FTL)
      ) {
        $containerMap = [];
        forEach($object->getQuote()->getCargoVolume()['items'] as $container) {
          if(!isset($containerMap[$container['value']])) {
            $containerMap[$container['value']] = 0;
          }
          $containerMap[$container['value']] += $container['amount'];
        }
        $data['cargoVolume']['items'] = [];
        $data['cargoVolume']['text'] = [];
        
        forEach($containerMap as $type => $count) {
          if($count === 0) continue;
          $data['cargoVolume']['items'][] = ['title' => $type, 'amount' => $count];
          //$data['cargoVolume']['text'][] = $count . ' x ' . $type;
        }
        
        $data['cargoVolume']['text'] = implode(',', $data['cargoVolume']['text']);
      } else {
        $data['cargoVolume'] = $object->getQuote()->getCargoVolume();
        $data['cargoVolume']['text'] = '';
      }
      $ebitNotes = [];
      forEach($object->getEbitNotes() as $note) {
        $ebitNotes[] = ['type' => $note->getType(), 'status' => $note->getStatus()];
      }
      $data['ebitNotes'] = $ebitNotes;
      $data['client'] = [
        'name' => $object->getQuote()->getClient()->getName()
      ];
      $data['provider'] = [
        'name' => $object->getQuote()->getProvider()->getName()
      ];
      $data['statusText'] = ShipmentStatus::from($data['status'])->trans($this->translator);
      $data['isDocumentUploaded'] = $object->getDocuments()->filter(function(Media $item) {
        return $item->getCategory() === MediaCategory::MAWB;
      })->count() > 0;
      if($object->getStatus() === ShipmentStatus::Active) {
        $priceMarkup = $object->getQuote()->getClient()->getPriceMarkup();
        $priceMarkup = $priceMarkup->getId() === Magnum::PRICE_MARKUP_10PERCENT_ID ? 0.1 : 0;
        $profitRatio = empty($object->getQuote()->getTotalBuying()) ? 1 : ($object->getQuote()->getTotalProfit() / $object->getQuote()->getTotalBuying());
        $data['isProfitMet'] = $profitRatio > $priceMarkup;
        
        $timeAlerts = [];
        $now = new \DateTime();
        forEach(['etd', 'cyCutOff', 'siCutOff', 'vgmCutOff', 'gateIn'] as $timeCriteria) {
          $upperProp = ucfirst($timeCriteria);
          $timeValue = $object->getBooking()->{'get' . $upperProp}();
          if($timeValue) {
            $diff = $now->diff($timeValue);
            // out of time, show 0 day
            if($diff->invert === 1) {
              $timeAlerts[$timeCriteria] = [0, $timeValue];
            } else if ($diff->days < Magnum::MIN_DAYS_ALERT) {
              $timeAlerts[$timeCriteria] = [$diff->days, $timeValue];
            }
          }
        }
        
        $data['isTimingGood'] = count($timeAlerts) === 0;
        $data['timeAlerts'] = $timeAlerts;
      } else {
        $data['isProfitMet'] = true;
        $data['isTimingGood'] = true;
      }
      $data['revenue']['amountText'] = $exchangeRateGroupService->formatMoney(
                                          $object->getRevenue(), 
                                          $currency, 
                                          $object->getQuote()->getExchangeRates()
                                        );
      $data['profit']['amountText'] = $exchangeRateGroupService->formatMoney(
                                          $object->getProfit(), 
                                          $currency, 
                                          $object->getQuote()->getExchangeRates()
                                        );
      $data['cost']['amountText'] = $exchangeRateGroupService->formatMoney(
                                    $object->getCost(), 
                                    $currency, 
                                    $object->getQuote()->getExchangeRates()
                                  );
      $data['commissionProvider'] = [
        'amount' => $exchangeRateGroupService->formatMoney(
                    $object->getCommissionProvider(), 
                    $currency, 
                    $object->getQuote()->getExchangeRates()
                  )
      ];
      $data['commissionClient'] = [
        'amount' => $exchangeRateGroupService->formatMoney(
          $object->getCommissionClient(), 
          $currency, 
          $object->getQuote()->getExchangeRates()
        )
      ];
      $request = $this->requestStack->getCurrentRequest();
      if($request->query->has('timezone')) {
        $completedDate = (new \DateTime( $data['completedDate']))->setTimezone(new \DateTimeZone($request->query->get('timezone')));
        $data['completedDateText'] = $completedDate->format('d/m/Y H:i');
        $createdDate = (new \DateTime( $data['createdDate']))->setTimezone(new \DateTimeZone($request->query->get('timezone')));
        $data['createdDateText'] = $createdDate->format('d/m/Y H:i');
      }
      return $data;
  }

  public function getSupportedTypes(?string $format): array
  {
      return [
          Shipment::class => true
      ];
  }

  public function supportsNormalization($data, string $format = null, array $context = []): bool
  {
      return 
        $data instanceof Shipment 
        && isset($context['groups'])
        && $context['groups'] === 'list'
      ;
  }
}