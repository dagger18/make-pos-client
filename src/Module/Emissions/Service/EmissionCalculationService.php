<?php
declare(strict_types=1);
namespace App\Module\Emissions\Service;

use App\Module\Emissions\Entity\EmissionFactor;
use App\Module\Emissions\Entity\ShipmentEmission;
use App\Module\Emissions\Repository\EmissionFactorRepository;
use App\Module\Emissions\Repository\SeaDistanceRepository;
use App\Module\Emissions\Repository\ShipmentEmissionRepository;
use App\Module\Operations\Entity\Shipment;

class EmissionCalculationService
{
    public function __construct(
        private readonly EmissionFactorRepository   $efRepo,
        private readonly SeaDistanceRepository      $seaRepo,
        private readonly ShipmentEmissionRepository $emissionRepo,
    ) {}

    public function calculateAndSave(
        Shipment $shipment,
        string   $transportMode,
        ?float   $manualDistanceKm = null,
        int      $legSequence = 1,
        ?string  $legDescription = null,
        string   $calculatedBy = 'SYSTEM',
    ): ShipmentEmission {
        $ef = $this->efRepo->findBestMatch($transportMode);
        if ($ef === null) {
            throw new \RuntimeException("No emission factor found for transport mode: $transportMode");
        }

        [$distanceKm, $distanceIsEstimate] = $this->resolveDistance($shipment, $transportMode, $manualDistanceKm);
        [$weightTonnes, $weightIsEstimate]  = $this->resolveCargoWeight($shipment);

        $tonneKm   = round($distanceKm * $weightTonnes, 4);
        $co2eTtw   = round($tonneKm * (float) $ef->getEfTtw(), 4);
        $co2eWtw   = round($tonneKm * (float) $ef->getEfWtw(), 4);
        $isEstimate = $distanceIsEstimate || $weightIsEstimate;

        $record = new ShipmentEmission();
        $record->setShipment($shipment);
        $record->setTransportMode($transportMode);
        $record->setEmissionFactor($ef);
        $record->setDistanceKm((string) $distanceKm);
        $record->setCargoWeightTonnes((string) $weightTonnes);
        $record->setTonneKm((string) $tonneKm);
        $record->setCo2eTtwKg((string) $co2eTtw);
        $record->setCo2eWtwKg((string) $co2eWtw);
        $record->setMethodology($ef->getMethodology());
        $record->setIsEstimate($isEstimate);
        $record->setLegSequence($legSequence);
        $record->setLegDescription($legDescription ?? 'Main transport leg');
        $record->setCalculatedAt(new \DateTime());
        $record->setCalculatedBy($calculatedBy);

        $this->emissionRepo->save($record);

        return $record;
    }

    private function resolveDistance(Shipment $shipment, string $transportMode, ?float $manual): array
    {
        if ($manual !== null && $manual > 0) {
            return [$manual, false];
        }

        if ($transportMode === 'OCN') {
            $booking = $shipment->getBooking();
            $polCode = $booking?->getPortLoading()?->getCode();
            $podCode = $booking?->getPortDischarge()?->getCode();
            if ($polCode && $podCode) {
                $seaDist = $this->seaRepo->findDistance($polCode, $podCode);
                if ($seaDist) {
                    return [(float) $seaDist->getDistanceKm(), false];
                }
            }
        }

        return [0.0, true];
    }

    private function resolveCargoWeight(Shipment $shipment): array
    {
        $instruction = $shipment->getInstruction();
        if ($instruction === null) {
            return [1.0, true];
        }

        $gw = method_exists($instruction, 'getGrossWeight') ? $instruction->getGrossWeight() : null;
        if ($gw !== null && is_numeric((string) $gw) && (float) $gw > 0) {
            $unit = strtoupper((string) (method_exists($instruction, 'getGrossWeightUnit') ? ($instruction->getGrossWeightUnit() ?? 'KG') : 'KG'));
            $kg   = in_array($unit, ['MT', 'T', 'TON', 'TONNE']) ? (float) $gw * 1000 : (float) $gw;
            if ($kg > 0) {
                return [round($kg / 1000, 4), false];
            }
        }

        $containers = method_exists($instruction, 'getContainers') ? ($instruction->getContainers() ?? []) : [];
        if (!empty($containers)) {
            $totalTonnes = 0.0;
            foreach ($containers as $c) {
                $type = '';
                if (is_object($c) && method_exists($c, 'getType')) {
                    $type = (string) $c->getType();
                } elseif (is_array($c)) {
                    $type = (string) ($c['type'] ?? '');
                }
                $totalTonnes += str_starts_with($type, '20') ? 10.0 : 14.0;
            }
            if ($totalTonnes > 0) {
                return [$totalTonnes, true];
            }
        }

        $cw = method_exists($instruction, 'getChargeableWeight') ? $instruction->getChargeableWeight() : null;
        if ($cw !== null && is_numeric((string) $cw) && (float) $cw > 0) {
            return [round((float) $cw / 1000, 4), true];
        }

        return [1.0, true];
    }
}
