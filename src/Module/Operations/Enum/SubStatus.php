<?php

namespace App\Module\Operations\Enum;

enum SubStatus: string
{
    // DRAFT
    case PendingBooking = 'PENDING_BOOKING';

    // PENDING
    case AwaitingSI = 'AWAITING_SI';
    case SISubmitted = 'SI_SUBMITTED';
    case AwaitingVGM = 'AWAITING_VGM';
    case VGMSubmitted = 'VGM_SUBMITTED';
    case AwaitingCargoReceipt = 'AWAITING_CARGO_RECEIPT';
    case CargoReceived = 'CARGO_RECEIVED';

    // ACTIVE
    case LoadedOnBoard = 'LOADED_ON_BOARD';
    case VesselDeparted = 'VESSEL_DEPARTED';
    case AtTransshipmentPort = 'AT_TRANSSHIPMENT_PORT';
    case VesselArrived = 'VESSEL_ARRIVED';
    case CustomsClearance = 'CUSTOMS_CLEARANCE';
    case CustomsReleased = 'CUSTOMS_RELEASED';
    case OutForDelivery = 'OUT_FOR_DELIVERY';
    case Delivered = 'DELIVERED';
    case PODReceived = 'POD_RECEIVED';

    // COMPLETED
    case Invoiced = 'INVOICED';
    case FullyPaid = 'FULLY_PAID';
    case ClosedWithVariance = 'CLOSED_WITH_VARIANCE';

    // CANCELLED
    case CancelledByCustomer = 'CANCELLED_BY_CUSTOMER';
    case CancelledNoSpace = 'CANCELLED_NO_SPACE';
    case RolledOver = 'ROLLED_OVER';

    public function parentStatus(): ShipmentStatus
    {
        return match ($this) {
            self::PendingBooking                        => ShipmentStatus::Draft,
            self::AwaitingSI, self::SISubmitted,
            self::AwaitingVGM, self::VGMSubmitted,
            self::AwaitingCargoReceipt, self::CargoReceived => ShipmentStatus::Pending,
            self::LoadedOnBoard, self::VesselDeparted,
            self::AtTransshipmentPort, self::VesselArrived,
            self::CustomsClearance, self::CustomsReleased,
            self::OutForDelivery, self::Delivered,
            self::PODReceived                           => ShipmentStatus::Active,
            self::Invoiced, self::FullyPaid,
            self::ClosedWithVariance                    => ShipmentStatus::Completed,
            self::CancelledByCustomer, self::CancelledNoSpace,
            self::RolledOver                            => ShipmentStatus::Cancelled,
        };
    }

    /** @return SubStatus[] */
    public static function forStatus(ShipmentStatus $status): array
    {
        return array_filter(self::cases(), fn($s) => $s->parentStatus() === $status);
    }
}
