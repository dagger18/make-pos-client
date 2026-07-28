<?php
namespace App\Module\Sales\Controller;

use App\Misc\Attribute\AppModule;
use App\Module\Catalog\Repository\ProductRepository;
use App\Module\Sales\Entity\Order;
use App\Module\Sales\Entity\OrderItem;
use App\Module\Sales\Entity\OrderPayment;
use App\Module\Sales\Enum\OrderStatus;
use App\Module\Sales\Enum\PaymentMethod;
use App\Module\Sales\Repository\OrderPaymentRepository;
use App\Module\Sales\Repository\OrderRepository;
use App\Module\Sales\Service\SalesService;
use App\Module\Core\Controller\CrudController;
use App\Module\Core\Service\BaseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/order')]
#[IsGranted('ROLE_USER')]
#[AppModule('sales')]
class OrderController extends CrudController
{
    public function __construct(
        BaseService $baseService,
        private readonly OrderRepository $repo,
        private readonly OrderPaymentRepository $paymentRepo,
        private readonly ProductRepository $productRepo,
        private readonly SalesService $salesService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($baseService);
    }

    #[Route('', methods: ['GET'])]
    public function LIST(Request $request): JsonResponse
    {
        $q = $request->query;
        $orders = $this->repo->findByFilters(
            $q->get('status'),
            $q->get('date_from'),
            $q->get('date_to'),
            $q->get('q'),
        );

        return $this->json(array_map(fn(Order $o) => [
            'id'         => $o->getId(),
            'status'     => $o->getStatus()->value,
            'total'      => (float) $o->getTotal(),
            'paidAmount' => (float) $o->getPaidAmount(),
            'itemCount'  => $o->getItems()->count(),
            'notes'      => $o->getNotes(),
            'createdAt'  => $o->getCreatedDate()?->format('Y-m-d H:i:s'),
        ], $orders));
    }

    #[Route('/{id}', methods: ['GET'])]
    public function DETAIL(int $id): JsonResponse
    {
        $order = $this->repo->find($id);
        if (!$order) {
            return $this->json(['error' => 'Not found.'], 404);
        }
        return $this->json($order->toArray());
    }

    #[Route('', methods: ['POST'])]
    public function POST(Request $request): JsonResponse
    {
        $data     = $request->toArray();
        $user     = $this->getUser();
        $location = $user->getLocation();
        if (!$location) {
            return $this->json(['error' => 'User has no location assigned.'], 400);
        }
        if (empty($data['items'])) {
            return $this->json(['error' => 'Order must have at least one item.'], 400);
        }

        $order = new Order();
        $order->setLocation($location);
        $order->setCreatedBy($user);
        $order->setNotes($data['notes'] ?? null);
        $order->setDiscountAmount($data['discount_amount'] ?? 0);
        $order->setTaxAmount($data['tax_amount'] ?? 0);

        foreach ($data['items'] as $row) {
            $product = $this->productRepo->find((int) ($row['product_id'] ?? 0));

            $item = new OrderItem();
            $item->setProductId($product?->getId());
            $item->setProductName($product?->getName() ?? ($row['product_name'] ?? ''));
            $item->setProductSku($product?->getSku() ?? ($row['product_sku'] ?? ''));
            $item->setQuantity((int) ($row['quantity'] ?? 1));
            $item->setNotes($row['notes'] ?? null);

            $basePrice     = (float) ($product?->getPrice() ?? $row['unit_price'] ?? 0);
            $modifiers     = $row['modifiers'] ?? [];
            $modifierDelta = array_sum(array_column($modifiers, 'price_delta'));
            $unitPrice     = $basePrice + $modifierDelta;
            $item->setUnitPrice($unitPrice);
            $item->setModifiers(empty($modifiers) ? null : $modifiers);
            $item->setItemTotal($unitPrice * $item->getQuantity());

            $order->addItem($item);
        }

        $this->salesService->calculateTotals($order);

        $this->em->persist($order);
        $this->em->flush();

        return $this->json(['id' => $order->getId()], 201);
    }

    #[Route('/{id}', methods: ['PUT'])]
    public function PUT(int $id, Request $request): JsonResponse
    {
        $order = $this->repo->find($id);
        if (!$order) {
            return $this->json(['error' => 'Not found.'], 404);
        }

        $data = $request->toArray();
        if (isset($data['status'])) {
            $order->setStatus(OrderStatus::from($data['status']));
        }
        if (array_key_exists('notes', $data)) {
            $order->setNotes($data['notes']);
        }
        if (isset($data['discount_amount'])) {
            $order->setDiscountAmount($data['discount_amount']);
            $this->salesService->calculateTotals($order);
        }

        $this->em->flush();

        return $this->json(['id' => $order->getId()]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function DELETE(int $id): JsonResponse
    {
        $order = $this->repo->find($id);
        if (!$order) {
            return $this->json(['error' => 'Not found.'], 404);
        }
        if ($order->getStatus() === OrderStatus::Paid) {
            return $this->json(['error' => 'Cannot delete a paid order.'], 400);
        }

        $order->setStatus(OrderStatus::Cancelled);
        $this->em->flush();

        return $this->json(null, 204);
    }

    #[Route('/{id}/payment', methods: ['POST'])]
    public function ADD_PAYMENT(int $id, Request $request): JsonResponse
    {
        $order = $this->repo->find($id);
        if (!$order) {
            return $this->json(['error' => 'Not found.'], 404);
        }
        if ($order->getStatus() === OrderStatus::Cancelled) {
            return $this->json(['error' => 'Cannot add payment to a cancelled order.'], 400);
        }

        $data    = $request->toArray();
        $payment = new OrderPayment();
        $payment->setMethod(PaymentMethod::from($data['method'] ?? 'cash'));
        $payment->setAmount($data['amount'] ?? 0);
        $payment->setReference($data['reference'] ?? null);

        $order->addPayment($payment);
        $this->salesService->calculatePaidAmount($order);

        if ($this->salesService->isFullyPaid($order)) {
            $order->setStatus(OrderStatus::Paid);
        }

        $this->em->persist($payment);
        $this->em->flush();

        return $this->json(['id' => $payment->getId(), 'orderStatus' => $order->getStatus()->value], 201);
    }

    #[Route('/{id}/payment/{pid}', methods: ['DELETE'])]
    public function DELETE_PAYMENT(int $id, int $pid): JsonResponse
    {
        $payment = $this->paymentRepo->find($pid);
        if (!$payment || $payment->getOrder()?->getId() !== $id) {
            return $this->json(['error' => 'Payment not found.'], 404);
        }

        $order = $payment->getOrder();
        $this->em->remove($payment);
        $this->em->flush();

        $this->salesService->calculatePaidAmount($order);
        if ($order->getStatus() === OrderStatus::Paid && !$this->salesService->isFullyPaid($order)) {
            $order->setStatus(OrderStatus::Open);
        }
        $this->em->flush();

        return $this->json(null, 204);
    }
}
