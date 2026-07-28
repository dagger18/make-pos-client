<?php
namespace App\Misc\Enum;

enum Feature: int
{
    // Core Pro
    case Branches                  = 1;
    case Departments               = 2;
    case PagesCms                  = 3;
    case Components                = 4;
    case ConfigurableShipmentId    = 5;
    // Quote Pro
    case FreeTimeAgreements        = 6;
    case PriceMarkupRules          = 7;
    case RateImport                = 8;
    // Operations Pro
    case Consolidations            = 9;
    case ArrivalNotices            = 10;
    case ShipmentDocuments         = 11;
    case ShipmentNotes             = 12;
    case ShipmentParties           = 13;
    case ShipmentTasks             = 14;
    case DocumentGeneration        = 15;
    case RoadFreight               = 16;
    case RailFreight               = 17;
    case Multimodal                = 18;
    case CourierExpress            = 19;
    case WarehouseCfs              = 20;
    // Finance Pro
    case GeneralLedger             = 21;
    case ChartOfAccounts           = 22;
    case CurrencyManagement        = 23;
    case ArAgeing                  = 24;
    case PLReports                 = 25;
    case CreditControl             = 26;
    case DdCalculator              = 27;
    // Tax Pro
    case HsCodes                   = 28;
    case DutyRates                 = 29;
    case HsRestrictions            = 30;
    case HsVersionMapping          = 31;
    case VatReporting              = 32;
    case PartnerTaxRegistration    = 33;
    case CustomerTaxExemption      = 34;
    // Carrier Pro
    case CarrierProviders          = 35;
    case CarrierProfiles           = 36;
    case ContainerTracking         = 37;
    case CarrierEventMappings      = 38;
    case CarrierPerformanceScore   = 39;
    case DdTracking                = 40;
    case VesselRollAlerts          = 41;
    case VesselSailingSchedule     = 42;
    case FlightSchedule            = 43;
    case CargoClaims               = 44;
    case CargoInsurance            = 45;
    // CRM Pro
    case CrmContacts               = 47;
    case CrmPartners               = 48;
    case CrmAgentProfiles          = 49;
    case SalesCrm                  = 50;
    // Notification Pro
    case EmailNotifications        = 51;
    case InAppNotifications        = 52;
    case NotificationRules         = 53;
    case NotificationTemplates     = 54;
    case NotificationQueue         = 55;
    // Reporting Pro
    case KpiDashboards             = 56;
    case RevenueAnalytics          = 57;
    case CustomDatasets            = 58;
    // Business
    case RateBenchmarking          = 59;
    case Co2Emissions              = 60;
    case AuditLogCompliance        = 61;
    case CustomerPortalAuth        = 62;
    case CustomerPortalShipments   = 63;
    case CustomerPortalDocuments   = 64;
    case CustomerPortalInvoices    = 65;
    case CustomerPortalQuoteRequests = 66;
    case LetterOfCredit            = 67;
}
