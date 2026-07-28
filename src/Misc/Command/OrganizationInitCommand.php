<?php

/*
 * This file is part of the SymfonyCasts SassBundle package.
 * Copyright (c) SymfonyCasts <https://symfonycasts.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Misc\Command;

use App\Module\Core\Entity\User;
use App\Module\Core\Enum\Magnum;
use App\Module\Core\Enum\UserStatus;
use App\Module\Core\Service\ConfigService;
use App\Module\Core\Service\InterServiceTokenService;
use App\Module\Core\Repository\UserRepository;
use App\Module\Core\Repository\UserGroupRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'organization:init',
    description: 'Set name and create first admin'
)]
class OrganizationInitCommand extends Command
{
    public function __construct(
        private ConfigService $configService,
        private UserGroupRepository $userGroupRepository,
        private UserRepository $userRepository,
        private HttpClientInterface $client,
        private ParameterBagInterface $params,
        private InterServiceTokenService $interServiceTokenService,
    ){
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('client', null, 'Client organization token');
        $this->addArgument('api-url', null, 'This client\'s API base URL (e.g. https://cargo.example.com)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if($this->configService->isConfigExists('organization')){
            $output->writeln('<error>Organization is already initialized.</error>');
            return self::SUCCESS;
        }
        $client = $input->getArgument('client');
        $apiUrl = $input->getArgument('api-url');
        $masterApiUrl = $this->params->get('master_api_url');
        $headers = ['X-Service-Token' => $this->interServiceTokenService->generate()];
        if ($apiUrl) {
            $headers['X-Client-Api-Url'] = $apiUrl;
        }
        $response = $this->client->request('GET', "{$masterApiUrl}/public/organization/init-info/{$client}", [
            'headers' => $headers,
        ]);
        ['organization' => $organization, 'user' => $user] = json_decode($response->getContent(), true);
        $user2 = new User();
        $user2->setFirstName($user['firstName']);
        $user2->setLastName($user['lastName']);
        $user2->setEmail($user['username']);
        $user2->setStatus(UserStatus::Active);
        $user2->setPassword($user['password']);
        $user2->setRoles(['ROLE_USER']);
        $user2->setUserGroup($this->userGroupRepository->find(Magnum::ADMIN_GROUP_ID));
        $this->userRepository->save($user2);
        $this->configService->setConfig('organization', json_encode([
            'token' => $organization['token'],
            'adminUserId' => $user2->getId(),
            'isInit' => 0,
            'planName' => $organization['planName'] ?? null,
        ]));

        $features = array_map('intval', $organization['features'] ?? []);
        if (!empty($features)) {
            $this->configService->setConfig('features', json_encode($features));
        }
        $capacity = $organization['capacity'] ?? null;
        if ($capacity !== null) {
            $this->configService->setConfig('capacity', json_encode($capacity));
        }
        $maxUsers = $organization['maxUsers'] ?? null;
        if ($maxUsers !== null) {
            $this->configService->setConfig('maxUsers', (string) (int) $maxUsers);
        }

        return self::SUCCESS;
    }
}
