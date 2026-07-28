<?php

declare(strict_types=1);

namespace CommonMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use App\Entity\User;
use App\Misc\Enum\Magnum;
use App\Misc\Enum\Permission;
use App\Misc\Enum\UserStatus;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251021072043 extends AbstractMigration
{
    public $container;
    public $conn;

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $isMysql = $this->conn->getDatabasePlatform() instanceof \Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
        if ($isMysql) {
            $this->conn->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        }

        $this->insertUserGroup();
        $this->insertCalculationType();
        $this->insertCurrency();
        // $this->insertPort();
        $this->insertIncoterm();
        $this->insertPaymentMethod();
        $this->insertPriceMarkup();
        $this->insertShipmentMode();
        $this->insertTaxGroup();

        if ($isMysql) {
            $this->conn->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs

    }
    public function insertUserGroup() {
        $statement = $this->conn->prepare("
        INSERT INTO `user_group` (`id`, `name`, `description`, `permissions`) VALUES
        (1,	'Admin',	NULL,	'99'),
        (2,	'Sale',	'Sale department',	'100,101,201,298,300,200,301,302,304,203,103,403,400'),
        (3,	'Accountant',	'keeps or examines the records of money received, paid, and owed by company',	'500,501,503,504,505,506,499,407,408,404,402'),
        (4,	'Manager',	NULL,	'900,200,201,202,203,298,299,300,301,302,303,304,399,100,101,102,103,800,801,802,803,804,805,806,807,808,901,500,501,503,504,505,506,400,402,403,404,405,406,407,408,499,850,851,852,905'),
        (6,	'Docs / Cust',	NULL,	'200,201,203,298,100,101,103,300,301,302,304,850,851,852,403,400,402,405,406,407,408')
        ");
        $statement->executeStatement();
    }

    public function insertCalculationType() {
        $json = <<<'EOD'
        [{"code":"by_containers_count","title":"By Containers count","transportTypes":["FCL","RFCL","OTHER"]},{"code":"by_container_type_20DC","title":"By Container type (20'DC)","transportTypes":["FCL","RFCL","OTHER"]},{"code":"by_container_type_40DC","title":"By Container type (40'DC)","transportTypes":["FCL","RFCL","OTHER"]},{"code":"by_container_type_20RF","title":"By Container type (20'RF)","transportTypes":["FCL","RFCL","OTHER"]},{"code":"by_container_type_40RF","title":"By Container type (40'RF)","transportTypes":["FCL","RFCL","OTHER"]},{"code":"by_container_type_20OT","title":"By Container type (20'OT)","transportTypes":["FCL","RFCL","OTHER"]},{"code":"by_container_type_40OT","title":"By Container type (40'OT)","transportTypes":["FCL","RFCL","OTHER"]},{"code":"by_container_type_20FR","title":"By Container type (20'FR)","transportTypes":["FCL","RFCL","OTHER"]},{"code":"by_container_type_40FR","title":"By Container type (40'FR)","transportTypes":["FCL","RFCL","OTHER"]},{"code":"by_container_type_40HC","title":"By Container type (40'HC)","transportTypes":["FCL","RFCL","OTHER"]},{"code":"by_container_type_45HC","title":"By Container type (45'HC)","transportTypes":["FCL","RFCL","OTHER"]},{"code":"by_container_type_20TK","title":"By Container type (20'Tank)","transportTypes":["FCL","RFCL","OTHER"]},{"code":"by_container_type_40TK","title":"By Container type (40'Tank)","transportTypes":["FCL","RFCL","OTHER"]},{"code":"by_container_type_FDC","title":"By Container type (FOOCDC)","transportTypes":["FCL","RFCL","OTHER"]},{"code":"by_container_type_FHC","title":"By Container type (FOOCHC)","transportTypes":["FCL","RFCL","OTHER"]},{"code":"by_shipment","title":"By Shipment","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_truck","title":"By Truck","transportTypes":["FCL","LCL","AIR","OTHER"]},{"code":"by_bl","title":"By B/L","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_seal","title":"By Seal","transportTypes":["FCL","OTHER"]},{"code":"by_invoice","title":"By Invoice","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_hour","title":"By Hour","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_day","title":"By Day","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_week","title":"By Week","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_month","title":"By Month","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_set","title":"By Set","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_drum","title":"By Drum","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_ton","title":"By Ton","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_kgs","title":"By KGS","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_customs_document","title":"By Customs","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_teu","title":"By TEU","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_20f","title":"By 20'","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_40f","title":"By 40'","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_45f","title":"By 45'","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_cbm_day","title":"By CBM/Day","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_cbm_hour","title":"By CBM/Hour","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_kgs_day","title":"By KGS/Day","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_kgs_hour","title":"By KGS/Hour","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_form","title":"By Form","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_unit","title":"By Unit","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_pcs","title":"By Pcs","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_dong_cont","title":"By Dong/Cont","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_dong_ton","title":"By Dong/Ton","transportTypes":["FCL","LCL","AIR","RFCL","RFTL","RLTL","OTHER"]},{"code":"by_cbm","title":"By CBM","transportTypes":["LCL","RLTL","OTHER"]},{"code":"by_p_45","title":"By 45 +","transportTypes":["AIR"]},{"code":"by_p_100","title":"By 100 +","transportTypes":["AIR"]},{"code":"by_p_300","title":"By 300 +","transportTypes":["AIR"]},{"code":"by_p_500","title":"By 500 +","transportTypes":["AIR"]},{"code":"by_min","title":"By Min","transportTypes":["AIR"]},{"code":"by_normal","title":"By Normal","transportTypes":["AIR"]}]
        EOD;
        $calculationTypes = json_decode($json, true);
        forEach($calculationTypes as $calType) {
            $this->conn->insert(
                'calculation_type',
                [
                    'name' => $calType['title'],
                    'code' => $calType['code'],
                    'transport_types' => implode(',', $calType['transportTypes'])
                ]
                );
        }
    }
    public function insertCurrency() {
         $this->conn->insert(
                'currency',
                [
                    'id' => 142,
                    'name' => 'US Dollar',
                    'code' => 'USD',
                    'symbol' => '$',
                    'rate' => 1.0,
                    'thousand_separator' => ',',
                    'decimal_separator' => '.',
                    'decimal_places' => 2
                ]
                );
    }

    public function insertPort(): void
    {
        $this->conn->executeQuery(file_get_contents("https://macarg.s3.ap-southeast-1.amazonaws.com/port.sql"));
    }

    public function insertIncoterm() {
        $statement = $this->conn->prepare("
        INSERT INTO `incoterm` (`id`, `created_by_id`, `name`, `description`) VALUES
            (1,	1,	'Delivered at Place Unloaded',	'Delivered at Place Unloaded'),
            (2,	1,	'Delivered Duty Paid',	'Delivered Duty Paid'),
            (3,	1,	'Cost, Insurance and Freight',	'Cost, Insurance and Freight'),
            (4,	1,	'Free on Board',	'Free on Board'),
            (5,	1,	'Cost and Freight',	'Cost and Freight'),
            (6,	1,	'Carriage and Insurance Paid To',	'Carriage and Insurance Paid To'),
            (7,	1,	'Free Carrier',	'Free Carrier'),
            (8,	1,	'Ex Works',	'Ex Works'),
            (9,	1,	'Carriage Paid To',	'Carriage Paid To'),
            (10,1,	'Free Alongside Ship',	'Free Alongside Ship'),
            (11,1,	'Delivered at Place',	'Delivered at Place')
        ");
        $statement->executeStatement();
    }
    public function insertPaymentMethod() {
        $statement = $this->conn->prepare("
        INSERT INTO `payment_method` (`id`, `created_by_id`, `name`,`type`, `description`, `created_date`, `updated_date`) VALUES
            (1,	1,	'Cash','C',	'Cash', '2024-01-01 00:00:00', '2024-01-01 00:00:00'),
            (2,	1,	'Cash/Telegraphic Transfer','C',	'Cash/Telegraphic Transfer', '2024-01-01 00:00:00', '2024-01-01 00:00:00'),
            (3,	1,	'Telegraphic Transfer','B',	'Telegraphic Transfer', '2024-01-01 00:00:00', '2024-01-01 00:00:00')
        ");
        $statement->executeStatement();
    }
    public function insertPriceMarkup() {
        $statement = $this->conn->prepare("
        INSERT INTO `price_markup` (`id`, `name`) VALUES
            (1,'10%'),
            (2,'0%')
        ");
        $statement->executeStatement();
    }
    public function insertShipmentMode() {
        $statement = $this->conn->prepare("
        INSERT INTO `shipment_mode` (`id`, `created_by_id`, `name`, `description`, `created_date`, `updated_date`) VALUES
        (1,	1,	'Handling',	NULL,	'2024-01-24 08:13:12',	'2024-01-24 08:13:12'),
        (2,	1,	'Other',	NULL,	'2024-01-24 08:13:18',	'2024-01-24 08:13:18')
        ");
        $statement->executeStatement();
    }
    public function insertTaxGroup() {
        $statement = $this->conn->prepare("
        INSERT INTO `tax_group` (`id`, `created_by_id`, `name`, `amount`, `description`, `created_date`, `updated_date`) VALUES
        (1,	1,	'0%',	0,	NULL,	'2024-02-08 15:13:36',	'2024-02-08 15:13:36'),
        (2,	1,	'8%',	8,	NULL,	'2024-02-08 15:13:45',	'2024-02-08 15:13:52'),
        (3,	1,	'10%',	10,	NULL,	'2024-02-08 15:14:03',	'2024-02-08 15:14:03');
        ");
        $statement->executeStatement();
    }
}
