<?php

namespace App\Module\Carrier\Enum;

enum MilestoneCode: string
{
    // Universal
    case JobCreated      = 'JOB_CREATED';
    case JobClosed       = 'JOB_CLOSED';

    // Ocean
    case CargoBooked     = 'CARGO_BOOKED';
    case CargoReady      = 'CARGO_READY';
    case EmptyReleased   = 'EMPTY_RELEASED';
    case GateIn          = 'GATE_IN';
    case VgmSubmitted    = 'VGM_SUBMITTED';
    case SiSubmitted     = 'SI_SUBMITTED';
    case OnBoard         = 'ON_BOARD';
    case VesselDeparted  = 'VESSEL_DEPARTED';
    case AtTransshipment = 'AT_TRANSSHIPMENT';
    case VesselArrived   = 'VESSEL_ARRIVED';
    case Discharged      = 'DISCHARGED';
    case Available       = 'AVAILABLE';
    case EntryFiled      = 'ENTRY_FILED';
    case CustomsReleased = 'CUSTOMS_RELEASED';
    case GateOut         = 'GATE_OUT';
    case Delivered       = 'DELIVERED';
    case PodReceived     = 'POD_RECEIVED';
    case EmptyReturned   = 'EMPTY_RETURNED';

    // Air
    case CargoAccepted   = 'CARGO_ACCEPTED';
    case FlightDeparted  = 'FLIGHT_DEPARTED';
    case FlightArrived   = 'FLIGHT_ARRIVED';

    // Road
    case PickupCompleted = 'PICKUP_COMPLETED';
    case InTransit       = 'IN_TRANSIT';
    case BorderCrossed   = 'BORDER_CROSSED';

    public function description(): string
    {
        return match($this) {
            self::JobCreated      => 'Shipment created in system',
            self::JobClosed       => 'All charges settled, job closed',
            self::CargoBooked     => 'Booking confirmed with carrier',
            self::CargoReady      => 'Cargo ready at origin warehouse',
            self::EmptyReleased   => 'Empty container released from depot',
            self::GateIn          => 'Container gated in at origin terminal',
            self::VgmSubmitted    => 'VGM declared to carrier',
            self::SiSubmitted     => 'Shipping instruction sent to carrier',
            self::OnBoard         => 'Cargo loaded on vessel (BL date)',
            self::VesselDeparted  => 'Vessel departed port of loading',
            self::AtTransshipment => 'At transshipment port',
            self::VesselArrived   => 'Vessel arrived at port of discharge',
            self::Discharged      => 'Container discharged from vessel',
            self::Available       => 'Container available for pickup',
            self::EntryFiled      => 'Customs entry submitted',
            self::CustomsReleased => 'Customs cleared',
            self::GateOut         => 'Container gated out from terminal',
            self::Delivered       => 'Cargo delivered to consignee',
            self::PodReceived     => 'Signed proof of delivery received',
            self::EmptyReturned   => 'Empty container returned to depot',
            self::CargoAccepted   => 'Cargo accepted at origin airport',
            self::FlightDeparted  => 'Flight departed origin',
            self::FlightArrived   => 'Flight arrived at destination',
            self::PickupCompleted => 'Cargo picked up from shipper',
            self::InTransit       => 'Truck in transit',
            self::BorderCrossed   => 'Border crossing completed',
        };
    }

    public function isSystem(): bool
    {
        return match($this) {
            self::JobCreated, self::JobClosed => true,
            default => false,
        };
    }

    public function customerLabel(): string
    {
        return match($this) {
            self::CargoBooked     => 'Booking confirmed',
            self::CargoReady      => 'Cargo ready for collection',
            self::GateIn          => 'Cargo received at port',
            self::OnBoard         => 'Cargo loaded on vessel',
            self::VesselDeparted  => 'Vessel departed',
            self::AtTransshipment => 'At transshipment port',
            self::VesselArrived   => 'Vessel arrived at destination',
            self::Discharged      => 'Cargo discharged',
            self::CustomsReleased => 'Customs cleared',
            self::Available       => 'Available for pickup',
            self::Delivered       => 'Cargo delivered',
            self::CargoAccepted   => 'Cargo accepted at airport',
            self::FlightDeparted  => 'Flight departed',
            self::FlightArrived   => 'Flight arrived',
            self::PickupCompleted => 'Cargo collected',
            self::InTransit       => 'In transit',
            default               => '',
        };
    }

    public function isCustomerVisible(): bool
    {
        return match($this) {
            self::CargoBooked,
            self::CargoReady,
            self::GateIn,
            self::OnBoard,
            self::VesselDeparted,
            self::AtTransshipment,
            self::VesselArrived,
            self::Discharged,
            self::CustomsReleased,
            self::Available,
            self::Delivered,
            self::CargoAccepted,
            self::FlightDeparted,
            self::FlightArrived,
            self::PickupCompleted,
            self::InTransit       => true,
            default               => false,
        };
    }
}
