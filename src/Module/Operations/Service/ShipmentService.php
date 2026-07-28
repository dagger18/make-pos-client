<?php
namespace App\Module\Operations\Service;

use App\Module\Finance\Entity\Charge;

use App\Module\Finance\Service\EbitNoteService;
use App\Module\Finance\Service\ExchangeRateGroupService;

use App\Module\Core\Service\BaseService;

use App\Module\Core\Entity\Money;
use App\Module\Finance\Entity\EbitNote;
use App\Module\Operations\Entity\Shipment;
use App\Module\Finance\Entity\ChargeItem;
use App\Module\Operations\Entity\Instruction;
use App\Module\Operations\Service\ShipmentIdGeneratorService;
use App\Module\Operations\Service\ShipmentDocumentService;
use App\Module\Operations\Service\ShipmentTaskService;
use App\Module\Core\Enum\Permission;
use Doctrine\ORM\QueryBuilder;
use App\Module\Finance\Enum\EbitNoteType;
use App\Module\Finance\Enum\EbitNoteStatus;
use App\Module\Operations\Enum\ShipmentStatus;
use App\Module\Core\Repository\UserRepository;
use App\Module\Operations\Repository\ShipmentRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShipmentService extends BaseService
{

    public function __construct(
        protected BaseService $baseService,
        protected EbitNoteService $ebitNoteService,
        protected ExchangeRateGroupService $exchangeRateGroupService,
        protected ShipmentIdGeneratorService $shipmentIdGeneratorService,
        protected ShipmentDocumentService $shipmentDocumentService,
        protected ShipmentTaskService $shipmentTaskService,
        public ShipmentRepository $repository,
        public UserRepository $userRepository,
        protected RequestStack $requestStack,
    ) {
        $this->reflectFromParent($baseService);
    }

    public function replicate(Shipment $fromShipment) {
        $shipment = new Shipment();
        $shipment->setStatus(ShipmentStatus::Draft);
        $shipment->setAccountManager($fromShipment->getAccountManager());
        $shipment->setCreatedBy($this->getUser());
        $quote = $this->quoteService->replicate($fromShipment->getQuote());
        $shipment->setQuote($quote);
        $booking = $this->bookingService->replicate($fromShipment->getBooking());
        $shipment->setBooking($booking);
        
        $instruction = new Instruction();
        $shipment->setInstruction($instruction);

        $this->repository->save($shipment);
        return $shipment;
    }

    public function setCode(Shipment $shipment) {
        $shipment->setCode($this->shipmentIdGeneratorService->generate($shipment));
        $this->repository->save($shipment);
        $this->shipmentDocumentService->seedRequiredDocuments($shipment);
        $this->shipmentTaskService->seedTasks($shipment);
    }

    public function parseNumberFromCode(Shipment $shipment) {
        $codeParts = explode('_', $shipment->getCode());
        return (int) ($codeParts[1] ?? 0);
    }
    public function subscribeAllUserAssignedToClient(Shipment $shipment) {
        $clientUsers = $shipment->getQuote()->getClient()->getAssignedUsers();
        $manageAllShipmentUserIds = array_map(function($us) {
            return $us->getId();
        }, $this->userRepository->getUsersWithPermission(Permission::ManageAllShipment->value));
        forEach($clientUsers as $clientUser) {
            if($shipment->getAssignedUsers()->contains($clientUser)) continue;
            if(in_array($clientUser->getId(), $manageAllShipmentUserIds)) continue;
            if($shipment->getCreatedBy() == $clientUser) continue;
            if($shipment->getAccountManager() == $clientUser) continue;
            $shipment->addAssignedUser($clientUser);
        }
        $this->repository->save($shipment);
    }
    public function calculateProfitLoss(Shipment $shipment) {
        $exRateGroup = $this->exchangeRateGroupService->repository->getCurrentGroup();
        $revenue = 0;
        $cost = 0;
        $COBO = 0;
        $POBO = 0;
        $currency = $exRateGroup->getCurrency()->getCode();
        $rate = $exRateGroup->getExchangeRates()->findFirst(function ($index, $item) {
            return $item->getFromCurrency()->getCode() === 'USD' ;
        })->getRate();
        forEach($shipment->getEbitNotes() as $ebitNote) {
            if($ebitNote->getStatus() === EbitNoteStatus::Pending) continue;
            if($ebitNote->getType() === EbitNoteType::InvoiceDebit) {
                $revenue += $this->commonService->convertMoney($ebitNote->getAmountNoTax(), $currency, $rate);
            }
            if($ebitNote->getType() === EbitNoteType::InvoiceCredit) {
                $cost += $this->commonService->convertMoney($ebitNote->getAmountNoTax(), $currency, $rate);
            }
            if($ebitNote->getType() === EbitNoteType::COBO) {
                $COBO += $this->commonService->convertMoney($ebitNote->getAmount(), $currency, $rate);
            }
            if($ebitNote->getType() === EbitNoteType::POBO) {
                $POBO += $this->commonService->convertMoney($ebitNote->getAmount(), $currency, $rate);
            }
        }
        $shipment->setRevenue(new Money($revenue, $currency, $rate));
        $shipment->setCost(new Money($cost, $currency, $rate));
        $shipment->setPobo(new Money($POBO, $currency, $rate));
        $shipment->setCobo(new Money($COBO, $currency, $rate));
        $shipment->setProfit(new Money($revenue - $cost, $currency, $rate));
    }

    public function doDeleteShipment(Shipment $shipment) {
        $callback = function() use ($shipment) {
            $records = [];
            $invoices = [];
            $notes = [];
            forEach($shipment->getEbitNotes() as $ebitNote) {
                switch($ebitNote->getType()) {
                    case EbitNoteType::Debit:
                    case EbitNoteType::Credit:
                    case EbitNoteType::COBO:
                    case EbitNoteType::POBO:
                        $notes[] = $ebitNote;
                        break;
                    case EbitNoteType::RecordReceipt:
                    case EbitNoteType::RecordPayment:
                        $records[] = $ebitNote;
                        break;
                    case EbitNoteType::InvoiceDebit:
                    case EbitNoteType::InvoiceCredit:
                        $invoices[] = $ebitNote;
                        break;
                }
            }
            forEach($records as $note) {
                $this->ebitNoteService->repository->delete($note, true);
            }
            forEach($invoices as $note) {
                $this->ebitNoteService->repository->delete($note, true);
            }
            forEach($notes as $note) {
                $this->ebitNoteService->repository->delete($note, true);
            }
            
            $this->repository->delete($shipment, true);
        };
        $this->repository->getEntityManager()->transactional($callback);
    }

    public function setCommission(Shipment $shipment) {
        $commissionProviderAmount = 0;
        $commissionProviderRate = 0;
        $commissionProviderCurrency = '';
        $commissionClientAmount = 0;
        $commissionClientRate = 0;
        $commissionClientCurrency = 0;
        forEach($shipment->getEbitNotes() as $ebitNote) {
            if($ebitNote->getType() !== EbitNoteType::InvoiceCredit) continue;
            forEach($ebitNote->getChargeItems() as $chargeItem) {
                if($chargeItem->getChargeTypeName() !== 'Commission') continue;
                if(str_contains(strtolower($chargeItem->getChargeName()), 'provider')) {
                    $commissionProviderAmount += $chargeItem->getAmount()->getAmount();
                    $commissionProviderRate = $chargeItem->getAmount()->getRate();
                    $commissionProviderCurrency = $chargeItem->getAmount()->getCurrency();
                    continue;
                }
                
                $commissionClientAmount += $chargeItem->getAmount()->getAmount();
                $commissionClientRate = $chargeItem->getAmount()->getRate();
                $commissionClientCurrency = $chargeItem->getAmount()->getCurrency();
            }
        }
        $shipment->setCommissionProvider( new Money($commissionProviderAmount,  $commissionProviderCurrency, $commissionProviderRate));
        $shipment->setCommissionClient( new Money($commissionClientAmount,  $commissionClientCurrency, $commissionClientRate));
        $shipment->noLog = true;
        $this->repository->save($shipment);
    }

    public function exportChargeDetails($requestData, Array|QueryBuilder $queryBuilder) {
        $spreadsheet = new Spreadsheet();
        $activeWorksheet = $spreadsheet->getActiveSheet();
        list($minDate, $maxDate) = $this->exportItems($activeWorksheet, $queryBuilder->getQuery()->toIterable() );
        $this->exportHeader($activeWorksheet, [$minDate, $maxDate]);
        $writer = new Xlsx($spreadsheet);
        //$writer->save('lol.xlsx');
        //return new JsonResponse(['status' => 'ok']);
        $response =  new StreamedResponse(
            function () use ($writer) {
                $writer->save('php://output');
            }
        );
        $response->headers->set('Content-Type', 'application/vnd.ms-excel');
        $response->headers->set('Content-Disposition', 'attachment;filename="Profit_Loss_With_ChargeDetails_' . $minDate->format('Y-m-d') . '_' . $maxDate->format('Y-m-d') . '.xlsx"');
        $response->headers->set('Cache-Control','max-age=0');
        return $response;
    }

    public function exportItems(Worksheet $s, $shipments) {
        $request = $this->requestStack->getCurrentRequest();
        $timezone = new \DateTimeZone(
                    $request->query->has('timezone') 
                    ? $request->query->get('timezone') : 'Asia/Ho_Chi_Minh'
                  );
        $currency = $request->query->has('reportCurrency') ? $request->query->get('reportCurrency') : 'VND';
        $formatCurrencyVND = '#,##0_-';
        $formatCurrency = $currency === 'VND' ? $formatCurrencyVND : '#,##0.00_-';
        $this->exportTableHeader($s);
        $minDate = null;
        $maxDate = null;
        $rowNum = 7;

        $totals = [
            'revenueAmount' => 0,
            'costAmount' => 0,
            'realProfit' => 0
        ];
        /** @var Shipment $shipment */
        foreach($shipments as $shipmentIndex => $shipment) {
            $quote = $shipment->getQuote();
            $booking = $shipment->getBooking();
            $createdDate = (clone $shipment->getCreatedDate())->setTimezone($timezone);
            if($booking->getEtd()) {
                if(!$minDate || $booking->getEtd() < $minDate) {
                    $minDate = $booking->getEtd();
                }
                if(!$maxDate || $booking->getEtd() > $maxDate) {
                    $maxDate = $booking->getEtd();
                }
                $etdDate = (clone $booking->getEtd())->setTimezone($timezone);
            }
            forEach($this->matchChargeItemFromRevenueAndCost($shipment) as list($revenue, $cost)) {
                /** @var ChargeItem $revenue */
                /** @var ChargeItem $cost */
                $entity = $revenue ? $revenue : $cost;
                $revenueAmount = $revenue 
                                    ? $this->exchangeRateGroupService->convertMoney(
                                        $revenue->getAmount(), 
                                        $currency, 
                                        $revenue->getEbitNote()->getExchangeRates()
                                        ) 
                                    : 0;
                $revenueRate = $revenue ? ($revenue->getEbitNote()->getExchangeRates()['VND'] ?? 0) : 0;
                $totals['revenueAmount'] += $revenueAmount;
                $costAmount = $cost 
                                ? $this->exchangeRateGroupService->convertMoney(
                                    $cost->getAmount(), 
                                    $currency, 
                                    $cost->getEbitNote()->getExchangeRates()
                                    ) 
                                : 0;
                $costRate = $cost ? ($cost->getEbitNote()->getExchangeRates()['VND'] ?? 0) : 0;
                $totals['costAmount'] += $costAmount;
                $realProfit = $revenueAmount - $costAmount;
                $totals['realProfit'] += $realProfit;
                $profitRevenueRate = round(empty($revenueAmount) ? -100 : ($realProfit*100 / $revenueAmount), 0) . '%';
                $row = [
                    // created date
                    [$createdDate->format('Y-m-d H:i:s')],
                    // etd
                    [$booking->getEtd() ? $etdDate->format('Y-m-d') : ''],
                    // code
                    [$shipment->getCode()],
                    // mbl
                    [implode(';', array_filter([$shipment->getMasterBill(), $shipment->getHouseBill()]))],
                    // charge type
                    [$entity->getChargeTypeName()],
                    // charge name
                    [$entity->getChargeName()],
                    // client name
                    [$quote->getClient()?->getName()],
                    // description
                    [$entity->getDescription()],
                    // calculation type
                    [$entity->getCalculationType()],
                    // revenue qty
                    [
                        $revenue ? $revenue->getQuantity() : '', 
                        [
                            'styles' => [
                                'alignment' => [
                                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                                ],
                            ]
                        ]
                    ],
                    // revenue price
                    [
                        $revenue 
                        ? $this->exchangeRateGroupService->convertMoney(
                            $revenue->getPrice(), 
                            $currency, 
                            $revenue->getEbitNote()->getExchangeRates()
                            ) 
                        : '', 
                        [
                            'formatCurrency' => $formatCurrency,
                            'styles' => [
                                'alignment' => [
                                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                                ],
                            ]
                        ]
                    ],
                    // revenue amount
                    [
                        $revenueAmount, 
                        [
                            'formatCurrency' => $formatCurrency,
                            'styles' => [
                                'alignment' => [
                                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                                ],
                            ]
                        ]
                    ],
                    // revenue rate
                    [
                        $revenueRate, 
                        [
                            'formatCurrency' => $formatCurrencyVND,
                            'styles' => [
                                'alignment' => [
                                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                                ],
                            ]
                        ]
                    ],
                    // provider name
                    [
                        $cost 
                        ? $cost->getEbitNote()->getPayTo()->getName()
                        : ($revenue 
                            ? $revenue->getProvider()?->getName() 
                            : '')
                    ],
                    // cost qty
                    [
                        $cost ? $cost->getQuantity() : '', 
                        [
                            'styles' => [
                                'alignment' => [
                                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                                ],
                            ]
                        ]    
                    ],
                    // cost price
                    [
                        $cost 
                        ? $this->exchangeRateGroupService->convertMoney(
                            $cost->getPrice(), 
                            $currency, 
                            $cost->getEbitNote()->getExchangeRates()
                            ) 
                        : '', 
                        [
                            'formatCurrency' => $formatCurrency,
                            'styles' => [
                                'alignment' => [
                                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                                ],
                            ]
                        ]
                    ],
                    // cost amount
                    [
                        $costAmount, 
                        [
                            'formatCurrency' => $formatCurrency,
                            'styles' => [
                                'alignment' => [
                                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                                ],
                            ]
                        ]
                    ],
                    // cost rate
                    [
                        $costRate, 
                        [
                            'formatCurrency' => $formatCurrencyVND,
                            'styles' => [
                                'alignment' => [
                                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                                ],
                            ]
                        ]
                    ],
                    // real profit
                    [
                        $realProfit, 
                        [
                            'formatCurrency' => $formatCurrency,
                            'styles' => [
                                'alignment' => [
                                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                                ],
                            ]
                        ]
                    ],
                    // real profit percent
                    [
                        $profitRevenueRate, 
                        [
                            'formatCurrency' => $formatCurrency,
                            'styles' => [
                                'alignment' => [
                                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                                ],
                            ]
                        ]
                    ],
                    [$quote->getShipmentType()->title()],
                    [$shipment->getAccountManager()->getFullName()],
                    [$shipment->getCreatedBy()->getFullName()],
                ];
                forEach($row as $index => $columnConfig) {
                    list($columnValue, $columnStyle) = $columnConfig + [null, null];
                    $this->setCellValue($s, $this->getNameFromNumber($index) . $rowNum, $columnValue, $columnStyle ?? []);
                }
                $rowNum = $rowNum + 1;
            }
            
        }
        $this->setCellValue($s, 'L' . $rowNum, $totals['revenueAmount'], [
            'formatCurrency' => $formatCurrency,
            'styles' => [
                'font' => ['bold' => true, 'size' => 18],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                ],
            ]
        ]);
        $this->setCellValue($s, 'Q' . $rowNum, $totals['costAmount'], [
            'formatCurrency' => $formatCurrency,
            'styles' => [
                'font' => ['bold' => true, 'size' => 18],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                ],
            ]
        ]);
        $this->setCellValue($s, 'S' . $rowNum, $totals['realProfit'], [
            'formatCurrency' => $formatCurrency,
            'styles' => [
                'font' => ['bold' => true, 'size' => 18],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
                ],
            ]
        ]);
        return [$minDate, $maxDate];
    }

    private function matchChargeItemFromRevenueAndCost(Shipment $shipment) {
        $allRevenueCharges = $shipment->getEbitNotes()
                ->filter(function (EbitNote $en) {
                    return $en->getType() === EbitNoteType::InvoiceDebit;
                })
                ->reduce(function ($resultArray, EbitNote $en) {
                    $iterator = $en->getChargeItems()->getIterator();
                    $iterator->uasort(function ($a, $b) {
                        return !empty($a->getPosition()) || !empty($b->getPosition())
                            ? ($a->getPosition() ?? 999) <=> ($b->getPosition() ?? 999)
                            : ($a->getId() ?? 999) <=> ($b->getId() ?? 999)
                        ;
                    });
                    return array_merge($resultArray, iterator_to_array($iterator)) ;
                }, []);
        $allCostCharges = $shipment->getEbitNotes()
                ->filter(function (EbitNote $en) {
                    return $en->getType() === EbitNoteType::InvoiceCredit;
                })
                ->reduce(function ($resultArray, EbitNote $en) {
                    $iterator = $en->getChargeItems()->getIterator();
                    $iterator->uasort(function ($a, $b) {
                        return !empty($a->getPosition()) || !empty($b->getPosition())
                            ? ($a->getPosition() ?? 999) <=> ($b->getPosition() ?? 999)
                            : ($a->getId() ?? 999) <=> ($b->getId() ?? 999)
                        ;
                    });
                    return array_merge($resultArray, iterator_to_array($iterator)) ;
                }, []);
        $chargeItems = [];
        forEach($allRevenueCharges as $revenueCharge) {
        $costCharge = current(array_filter($allCostCharges, function (ChargeItem $charge) use ($revenueCharge) {
            return $charge->getQuotePrice() 
            && $revenueCharge->getQuotePrice()
            && $charge->getQuotePrice()->getId() === $revenueCharge->getQuotePrice()->getId();
        }));
        if($costCharge) {
            $chargeItems[$costCharge->getId()] = [$revenueCharge, $costCharge];
        } else {
            $chargeItems['A'.$revenueCharge->getId()] = [$revenueCharge, $costCharge];
        }

        }
        forEach($allCostCharges as $costCharge) {
            if(isset($chargeItems[$costCharge->getId()])) continue;
            $chargeItems[$costCharge->getId()] = [false, $costCharge];
        }
        return $chargeItems;
    }
    private function exportHeader(Worksheet $s, $dateRange) {
        list($minDate, $maxDate) = $dateRange;
        
        $s->mergeCells('A1:E1');
        $s->setCellValue('A1', '      PROFIT & LOSS by Shipment with Charge Details      ');
        $s->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 25, 'color' => ['argb' => 'ffa500']],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            ]
        ]);
        $s->setCellValue('C3', 'Statement Date');
        $s->setCellValue('C4', 'Reporting Period');
        $s->setCellValue('D3', (new \DateTime())->format('M d, Y'));
        $s->setCellValue('D4', ($minDate ? $minDate->format('M d, Y') : '') . ' - ' . ($maxDate ? $maxDate->format('M d, Y') : ''));
    }
    private function exportTableHeader(Worksheet $s) {
        $s->getStyle('A6:W6')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'ffffff'], 'size' => 13],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => '00b0f0',
                ]
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
            ],
        ]);
        $s->getStyle('J6:M6')->applyFromArray([
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => '00b050',
                ]
            ],
        ]);
        $s->getStyle('O6:R6')->applyFromArray([
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => 'e26b0a',
                ]
            ],
        ]);
        $s->getStyle('S6:T6')->applyFromArray([
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => [
                    'argb' => 'c90000',
                ]
            ],
        ]);

        $columnConfigs = [
            ['Created on',          130],
            ['ETD',                 75],
            ['Shipment ID',         130],
            ['MBL;HBL;',            220],
            ['Charge Type',         110],
            ['Item Name',           220],
            ['Client',              320],
            ['Description',         220],
            ['Calculation Type',    100],
            ['Qty',                 50],
            ['Price',               110],
            ['Revenue',             180],
            ['Rate',                80],
            ['Provider',            320],
            ['Qty',                 50],
            ['Price',               110],
            ['Cost',                180],
            ['Rate',                80],
            ['Profit',              180],
            ['Profit(%)',           80],
            ['Shipment Type',       130],
            ['Account Manager',     130],
            ['Created By',          130],
        ];
        forEach($columnConfigs as $index => list($name, $width)) {
            $s->setCellValue($this->getNameFromNumber($index) . '6', $name);
            $s->getColumnDimension($this->getNameFromNumber($index))->setWidth($width, 'px');
        }
    }
    private function setCellValue($s, $coor, $value, $config = [], $doMerge = false) {
        list($from) = explode(':',$coor); 
        $s->setCellValue($from, $value);
        $s->getStyle($coor)->applyFromArray([
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
            ],
        ]);
        if(isset($config['formatCurrency'])) {
            $s->getStyle($coor)
            ->getNumberFormat()
            ->setFormatCode($config['formatCurrency']);
        }
        if(isset($config['styles'])) {
            $s->getStyle($coor)->applyFromArray($config['styles']);
        }
        if($doMerge) {
            $s->mergeCells($coor);
        }
    }
    private function getNameFromNumber($num) {
        $numeric = $num % 26;
        $letter = chr(65 + $numeric);
        $num2 = intval($num / 26);
        if ($num2 > 0) {
            return $this->getNameFromNumber($num2 - 1) . $letter;
        } else {
            return $letter;
        }
    }
}
