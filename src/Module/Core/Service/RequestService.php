<?php
namespace App\Module\Core\Service;

use App\Misc\Attribute\CheckOwnership;
use App\Misc\Attribute\Log as LogAttribute;
use App\Misc\Exception\CacheHitResponse;
use App\Module\Core\Entity\Log;
use App\Module\Core\Repository\LogRepository;
use App\Module\Core\Repository\UserAgentRepository;
use App\Module\Core\Service\BaseService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\ItemInterface;
/**
 * Class RequestService
 * @package App\Service
 */
class RequestService extends BaseService
{
    public $exception = null;
    public $exceptionCode = 0;
    public $methodName = null;
    public $logging = false;
    public $log = null;
    public $useCache = false;
    public $cacheResponseKey = null;
    public $isBackend = false;
    public $language = 'en_EN';
    public $deletedEntities = [];
    public $siteId = null;
    
    public function __construct(
        protected BaseService $baseService,
        protected RequestStack $requestStack,
        private readonly SerializerInterface&DenormalizerInterface $denormalizer,
        protected LogRepository $logRepo,
        protected UserAgentRepository $userAgentRepo
    ) {
        $this->reflectFromParent($baseService);
    }
    
    public function startLogging($methodName, $controller) {
        $this->methodName = $methodName;
        $this->logging = $this->checkAnnotation(LogAttribute::class, $methodName, $controller);
    }
    
    public function startCache($methodName, $controller) {
        $request = $this->requestStack->getCurrentRequest();
        $this->language = $request->headers->get('X-Language') ?? 'en_EN';
        $this->useCache = $this->checkAnnotation('App\Annotation\Cache', $methodName, $controller);
        if($this->useCache === true) {
            $route = $request->attributes->get('_route');
            $routeParams = $request->attributes->get('_route_params');
            $allInputs = array_merge($this->stripRequest($request), $routeParams);
            $allInputs['lang'] = $this->language;
            $hashedInputs = md5(json_encode($allInputs, 1));
            
            $this->cacheResponseKey = 'Response.' . $route . '.' . $hashedInputs;
            $cacheResponse = $this->cache->getItem($this->cacheResponseKey);
            
            if($cacheResponse->isHit()) {
                $payload = json_decode($cacheResponse->get(), 1);
                $data = json_encode($payload['data'], 1);
                throw new CacheHitResponse($data, $payload['code']);
            }
        }
    }
    
    public function getUserAgent($name) {
        $userAgent = $this->userAgentRepo->findOneBy(['name' => $name]);
        if (!$userAgent) {
            $this->userAgentRepo->getConnection()->insert('user_agent', [
                'name' => $name
            ]);
            return $this->userAgentRepo->getConnection()->lastInsertId();
        }
        return $userAgent->getId();
    }
    
    public function checkAnnotation($nameAnnotation, $methodName, $controller) {
        $controllerName = $this->commonService->getClassName($controller);
        $annotationShortName = $this->commonService->getClassName($nameAnnotation);
        $cacheKey = 'Annotation.' . $annotationShortName . '.' . $controllerName . '.' . $methodName;
        $cachedCheckAnnotation = $this->codeCache->getItem($cacheKey);

        if($cachedCheckAnnotation->isHit()) {
            //return $cachedCheckAnnotation->get() == 'yes';
        }
        $reflectionClass = new \ReflectionClass($controller);
        $reflectionObject = new \ReflectionObject($controller);
        $reflectionMethod = $reflectionObject->getMethod($methodName);
        $hasAnnotation = false;
        if (!is_null($reflectionClass) && !is_null($reflectionMethod)) {
            $methodAnnotation = $reflectionMethod->getAttributes($nameAnnotation);
            if ($methodAnnotation) {
                $hasAnnotation = true;
            }
        }
        $cachedCheckAnnotation->set($hasAnnotation === true ? 'yes' : 'no');
        $this->codeCache->save($cachedCheckAnnotation);
        return $hasAnnotation;
    }
    
    public function saveLog($code, $content, $force = false) {
        if ($this->logging || $force) {
            $this->logging = false;
            $request = $this->requestStack->getCurrentRequest();
            $log = [];
            $log['ip'] = $request->getClientIp();
            
            if(!is_null($this->getUser())) {
                $log['user_id'] = $this->getUser()->getId();
            }
            $allInputs = $this->stripRequest($request);
            $log['request'] = json_encode($allInputs);
            $log['url'] = $request->getRequestUri();
            $log['method'] = $request->getMethod();
            $log['result'] = $code;
            if($this->exception) {
                $log['result'] = $this->exceptionCode;
                $line = method_exists($this->exception, 'getLine') ? ('at line ' . $this->exception->getLine()) : '';
                $file = method_exists($this->exception, 'getFile') ? ('of ' . $this->exception->getFile()) : '';
                $trace = method_exists($this->exception, 'getTraceAsString') ? ($this->exception->getTraceAsString()) : '';
                $log['exception'] = $this->exception->getMessage() . ' ' . $line . ' ' . $file . ' ' . $trace;
            }
            $log['response'] = json_encode($content);
            $log['size'] = memory_get_usage() / 1000;
            $log['response_time'] = (microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000;
            $log['created_date'] = (new \Datetime('now'))->format('Y-m-d H:i:s');
            $logRepo = $this->logRepo;
            $userAgentHeader = $request->headers->get('user-agent');
            if($userAgentHeader) {
                $log['user_agent_id'] = $this->getUserAgent($userAgentHeader);
            }
            //header('Access-Control-Allow-Origin: *');dd($log);
            //$this->logger->info('im saving log', [$log]);
            //$this->logger->info('connection', [$logRepo->getConnection()]);
            $logRepo->getConnection()->insert('log', $log);
            //$logRepo->save($log, true);
        }
    }
    
    public function stripRequest($request) {
        $content = $request->getContent() ?? '';
        $content = json_decode($content, 1) ?? [];
        $allInputs = $request->request->all() + $request->query->all() + $content;
        return $allInputs;
    }

    public function archiveDeletedEntities(): void
    {
        // Archive functionality removed (freight-specific).
    }

    public function getRealm() {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();
        // here you find the group for deserialize
        $re = '/App\\\\Module\\\\(.+)\\\\Controller\\\\(.+)Controller::(.+)/m';
        preg_match($re, $request->attributes->get('_controller'), $matches);
        list($_, $realm, $entityName, $action) = $matches;
        return $realm;
    }
    public function getAction() {
        /** @var Request $request */
        $request = $this->requestStack->getCurrentRequest();
        // here you find the group for deserialize
        $re = '/App\\\\Module\\\\(.+)\\\\Controller\\\\(.+)Controller::(.+)/m';
        preg_match($re, $request->attributes->get('_controller'), $matches);
        list($_, $realm, $entityName, $action) = $matches;
        return $action;
    }


    public function getOwnershipFields(Request $request): string {
      $re = '/(App\\\\Module\\\\.+Controller)::(.+)/m';
      preg_match($re, $request->attributes->get('_controller'), $matches);
      list($_, $controllerName, $action) = $matches;
      $cacheKey = str_replace('\\', '', $controllerName . CheckOwnership::class);
      return $this->codeCache->get($cacheKey, function (ItemInterface $item) use ($controllerName): string {
          $item->expiresAfter(3600);
          $ownershipAttribute = null;
          $reflectionClass = new \ReflectionClass($controllerName);
          forEach ($reflectionClass->getAttributes() as $attribute) {
            if($attribute->getName() === CheckOwnership::class) {
              $ownershipAttribute = $attribute;
              break;
            }
              
          }
          if(is_null($ownershipAttribute)) return '';
          return implode(',', $ownershipAttribute->newInstance()->ownershipFields);
      });
    }
}
