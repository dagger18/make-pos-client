<?php

namespace App\Module\Core\Controller;

use App\Module\Core\Controller\CrudController;

use App\Module\Core\Entity\User;
use App\Misc\Attribute\Log;
use App\Misc\Exception\AccessDeniedException;
use App\Module\Finance\Repository\ExchangeRateGroupRepository;
use App\Module\Core\Repository\UserRepository;
use App\Resolver\CrudRequestPayloadValueResolver;
use App\Module\Core\Service\ConfigService;
use App\Module\Core\Service\FeatureService;
use App\Module\Core\Service\MasterService;
use App\Module\Core\Service\MediaService;
use App\Module\Notification\Service\InAppNotificationService;
use App\Module\Core\Service\UserService;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use App\Misc\Attribute\AppModule;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use App\Module\Core\Entity\UserNotificationPreference;
use App\Module\Notification\Repository\NotificationRuleRepository;
use App\Module\Notification\Repository\UserNotificationPreferenceRepository;

#[Route('/my-profile')]
#[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_USER")'))]
#[AppModule('core')]
class MyProfileController extends CrudController
{

    #[Route('/get')]
    public function index(
        #[CurrentUser] User $user
    ): JsonResponse
    {
        return $this->json($user, 200, [], ['groups' => 'profile']);
    }
    

    #[Route('/ping', methods: ['GET'])]
    public function ping(
        UserService $userService,
        InAppNotificationService $notificationService,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $pingResult = $userService->ping();
        $pingResult['notification'] = $notificationService->countUnread($user->getId());
        return $this->json($pingResult, Response::HTTP_CREATED);
    }
    #[Route('/view-page/{page}', methods: ['GET'])]
    public function viewPage(
        string $page,
        UserService $userService
    ): JsonResponse {
        return $this->json($userService->viewPage($page), Response::HTTP_CREATED);
    }

    #[Route('/get-notifications/{page}', methods: ['GET'])]
    public function getNotifications(
        int $page,
        #[CurrentUser] User $user,
        InAppNotificationService $notificationService,
    ): JsonResponse {
        $data = $notificationService->getPagedForUser($user->getId(), max(1, $page));
        return $this->json([
            'currentPage' => $data['currentPage'],
            'totalPages'  => $data['totalPages'],
            'total'       => $data['total'],
            'list'        => array_map(fn($n) => [
                'id'         => $n->getId(),
                'title'      => $n->getTitle(),
                'body'       => $n->getBody(),
                'priority'   => $n->getPriority(),
                'isRead'     => $n->isRead(),
                'actionUrl'  => $n->getActionUrl(),
                'shipmentId' => null,
                'shipment'   => null,
                'ruleKey'    => $n->getRuleKey(),
                'createdDate'=> $n->getCreatedDate()?->format(\DateTimeInterface::ATOM),
            ], $data['list']),
        ]);
    }

    #[Route('/mark-notifications-read', methods: ['POST'])]
    public function markNotificationsRead(
        Request $request,
        #[CurrentUser] User $user,
        InAppNotificationService $notificationService,
    ): JsonResponse {
        $body = json_decode($request->getContent(), true) ?? [];
        $ids = isset($body['ids']) && is_array($body['ids'])
            ? array_map('intval', $body['ids'])
            : null;
        $notificationService->markRead($user->getId(), $ids);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/profile/{id}', methods: ['POST'])]
    #[Log]
    public function profile(
        #[MapRequestPayload(
            serializationContext: ['entityClassName' => User::class, 'entityName' => 'User', 'UPDATING_PROFILE'],
            resolver: CrudRequestPayloadValueResolver::class
        )]
        User $entity,
        #[CurrentUser] User $user,
        UserRepository $userRepository,
    ): JsonResponse {
        if($user->getId() !== $entity->getId()) {
            throw new AccessDeniedException(AccessDeniedException::NOT_PERMITTED, 'token check');
        }
        $userRepository->save($entity);
        return $this->json($entity, Response::HTTP_CREATED);
    }

    #[Route('/upgrade-link', methods: ['GET'])]
    public function upgradeLink(
        #[CurrentUser] User $user,
        ConfigService $configService,
        MasterService $masterService,
    ): JsonResponse {
        $config = $configService->getConfigValue('organization', isJson: true) ?? [];
        if (empty($config) || ($config['adminUserId'] ?? null) !== $user->getId()) {
            throw new AccessDeniedException(AccessDeniedException::NOT_PERMITTED, 'not org admin');
        }
        $link = $masterService->getMasterLoginLink($config['token']);
        if (!$link) {
            return $this->json(['error' => $this->trans('Could not generate upgrade link.')], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
        return $this->json(['link' => $link]);
    }

    #[Route('/avatar', methods: ['POST'])]
    public function updateAvatar(
        Request $request,
        #[CurrentUser] User $user,
        UserService $userService,
        MediaService $mediaService,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true) ?? $request->request->all();
        $mediaId = (int) ($data['mediaId'] ?? 0);
        if (!$mediaId) {
            throw new \InvalidArgumentException('mediaId is required');
        }
        $media = $mediaService->repository->find($mediaId);
        if (!$media) {
            throw $this->createNotFoundException('Media not found');
        }
        $user->setLogo($media);
        $userService->repository->save($user);
        return $this->json(['logo' => $media], Response::HTTP_CREATED);
    }

    #[Route('/table-config', methods: ['DELETE'])]
    public function clearTableConfig(
        #[CurrentUser] User $user,
        UserRepository $userRepository,
    ): JsonResponse {
        $user->setTableConfig(null);
        $userRepository->save($user);
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/table-config', methods: ['GET'])]
    public function tableConfig(
        #[CurrentUser] User $user,
        ConfigService $configService,
        ExchangeRateGroupRepository $exchangeRateGroupRepository,
        NormalizerInterface $serializer,
        FeatureService $featureService,
    ): JsonResponse {
        $config = $configService->getConfigValue('organization', isJson: true) ?? [];
        if($config['adminUserId'] === $user->getId()) {
            $organizationConfig = $config;
        }
        return $this->json([
          'tableConfig' => $user->getTableConfig(),
          'organizationConfig' => $organizationConfig ?? null,
          'exchangeRatesConfig' => $serializer->normalize($exchangeRateGroupRepository->findOneBy([], ['id' => 'DESC']) ?? null, 'json', ['groups' => ['currentExchangeRates']]),
          'featureConfig' => $featureService->enabledIds(),
        ]);
    }

    #[Route('/notification-preferences', methods: ['GET'])]
    public function getNotificationPreferences(
        #[CurrentUser] User $user,
        UserNotificationPreferenceRepository $prefRepository,
        NotificationRuleRepository $ruleRepository,
    ): JsonResponse {
        $prefs = $prefRepository->findForUser($user);
        $rules = $ruleRepository->findBy(['isActive' => true]);

        $prefMap = [];
        foreach ($prefs as $p) {
            $prefMap[$p->getRuleKey()][$p->getChannel()] = [
                'isEnabled'  => $p->isEnabled(),
                'digestMode' => $p->isDigestMode(),
            ];
        }

        return $this->json(array_map(fn($r) => [
            'ruleKey'  => $r->getRuleKey(),
            'name'     => $r->getName(),
            'priority' => $r->getPriority(),
            'channels' => array_map(fn($ch) => [
                'channel'    => $ch,
                'isEnabled'  => $prefMap[$r->getRuleKey()][$ch]['isEnabled'] ?? true,
                'digestMode' => $prefMap[$r->getRuleKey()][$ch]['digestMode'] ?? false,
            ], $r->getChannels()),
        ], $rules));
    }

    #[Route('/notification-preferences', methods: ['POST'])]
    public function saveNotificationPreferences(
        Request $request,
        #[CurrentUser] User $user,
        UserNotificationPreferenceRepository $prefRepository,
    ): JsonResponse {
        $body = json_decode($request->getContent(), true) ?? [];
        foreach ($body as $item) {
            $ruleKey = $item['ruleKey'] ?? '';
            $channel = $item['channel'] ?? '';
            if (!$ruleKey || !$channel) continue;

            $pref = $prefRepository->findOneBy([
                'user'    => $user,
                'ruleKey' => $ruleKey,
                'channel' => $channel,
            ]) ?? new UserNotificationPreference();

            $pref->setUser($user)
                 ->setRuleKey($ruleKey)
                 ->setChannel($channel)
                 ->setIsEnabled((bool) ($item['isEnabled'] ?? true))
                 ->setDigestMode((bool) ($item['digestMode'] ?? false));

            $prefRepository->savePreference($pref);
        }
        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}