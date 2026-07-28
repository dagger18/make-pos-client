<?php
namespace App\Module\Quote\Service;

use App\Module\Core\Service\MediaService;
use App\Module\Operations\Service\ShipmentService;
use App\Module\Carrier\Service\ProviderService;

use App\Module\Core\Service\BaseService;

use App\Module\Operations\Entity\Booking;
use App\Module\Operations\Entity\Instruction;
use App\Module\Quote\Entity\Quote;
use App\Module\Quote\Service\QuoteCodeGeneratorService;
use App\Module\Operations\Entity\Shipment;
use App\Module\Crm\Enum\ClientCustomInfoMode;
use App\Module\Core\Enum\EntityType;
use App\Module\Core\Enum\Magnum;
use App\Module\Quote\Enum\QuoteStatus;
use App\Module\Operations\Enum\ShipmentStatus;
use App\Module\Quote\Repository\QuoteRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Environment;

class QuoteService extends BaseService
{
    public function __construct(
        protected BaseService $baseService,
        protected MediaService $mediaService,
        protected ShipmentService $shipmentService,
        protected ProviderService $providerService,
        protected QuoteCodeGeneratorService $quoteCodeGeneratorService,
        public QuoteRepository $repository,
        protected Environment $twig,
        protected RequestStack $requestStack,
    ) {
        $this->reflectFromParent($baseService);
    }

    public function setCode(Quote $quote): void
    {
        $quote->setCode($this->quoteCodeGeneratorService->generate($quote));
    }

    public function makeShipment(Quote $quote): Shipment
    {
        return $this->getEntityManager()->wrapInTransaction(function () use ($quote): Shipment {
            $shipment = new Shipment();
            $shipment->setQuote($quote);
            $shipment->setCreatedBy($this->getUser());

            $booking = new Booking();
            $this->reflectToBooking($booking, $quote);
            $shipment->setBooking($booking);

            $instruction = new Instruction();
            $this->reflectToInstruction($instruction, $quote);
            $shipment->setInstruction($instruction);

            $shipment->setStatus(ShipmentStatus::Draft);
            $quote->setStatus(QuoteStatus::Booked);
            $this->repository->save($quote);
            $shipment->setAccountManager($quote->getClient()->getAccountManager());
            return $this->shipmentService->repository->save($shipment);
        });
    }

    public function replicate(Quote $fromQuote, QuoteStatus $status = QuoteStatus::Booked): Quote
    {
        $quote = clone $fromQuote;
        $this->setCode($quote);
        $quote->setStatus($status);
        $quote->setCreatedBy($this->getUser());
        $quote->setCreatedDate(new \DateTime('now'));
        $quote->setUpdatedDate(new \DateTime('now'));
        foreach ($fromQuote->getPrices() as $fromPrice) {
            $price = clone $fromPrice;
            $price->setCreatedBy($this->getUser());
            $price->setCreatedDate(new \DateTime('now'));
            $price->setUpdatedDate(new \DateTime('now'));
            $quote->addPrice($price);
        }
        $this->repository->save($quote);
        return $quote;
    }

    public function reflectToBooking(Booking $booking, Quote $quote): void
    {
        $map = [
            'bookingTo' => 'client.name',
            'placeReceipt' => 'originDoor||originPort',
            'portLoading' => 'originPort',
            'portDischarge' => 'destinationPort',
            'placeDelivery' => 'destinationDoor||destinationPort',
            'destination' => 'destinationDoor||destinationPort',
            'etd' => 'estimatedDeparture',
            'cargoVolume' => 'cargoVolume',
            'commodities' => 'commodities',
        ];
        foreach ($map as $to => $from) {
            $value = match ($from) {
                'client.name' => $quote->getClient()->getName(),
                'originDoor||originPort' => $quote->getOriginDoor() ?? $quote->getOriginPort()->getDisplayName(),
                'destinationDoor||destinationPort' => $quote->getDestinationDoor() ?? $quote->getDestinationPort()->getDisplayName(),
                default => $quote->{'get' . ucfirst($from)}(),
            };
            $booking->{'set' . ucfirst($to)}($value);
        }
    }

    public function reflectToInstruction(Instruction $instruction, Quote $quote): void
    {
        $cv = $quote->getCargoVolume();
        $map = [
            'grossWeight' => 'totalWeight',
            'volume' => 'totalCBM',
            'chargeableWeight' => 'chargeableWeight',
            'packageCount' => 'totalUnit',
        ];
        foreach ($map as $to => $from) {
            $value = $cv[$from] ?? null;
            $instruction->{'set' . ucfirst($to)}($value);
        }
        $instruction->setGrossWeightUnit('KGS');
    }

    public function pdfData(mixed $entity, Request $request, $language): array {
        $company = $this->providerService->repository->find(Magnum::COMPANY_PROVIDER_ID);
        $client = $entity->getClient();
        $normalClient = $this->serializer->normalize($client);
        $clientQuoteInfo = null;
        switch($client->getCustomInfoMode()) {
            case ClientCustomInfoMode::GeneralInfo :
                $clientQuoteInfo = [...$normalClient['defaultInvoiceInfo'], ...$normalClient];
                break;
            case ClientCustomInfoMode::InvoiceInfo :
                $clientQuoteInfo = [...$normalClient['defaultInvoiceInfo']];
                break;
            case ClientCustomInfoMode::CustomInfo :
                $clientQuoteInfo = [...$normalClient['customInfo'], ...$normalClient];
                break;
        }
        // todo: only include shown currency in exchange rates :))
        return [
            'company' => $company,
            'client' => $client,
            'quote' => $entity,
            'clientQuoteInfo' => $clientQuoteInfo,
            'basePath' => $this->requestStack->getCurrentRequest()->getUriForPath(''),
            'filename' => 'QUOTE_' . $entity->getCode() . '_' . $language . '.pdf',
            'template' => 'pdf/quote.html.twig'
        ];
    }
}
