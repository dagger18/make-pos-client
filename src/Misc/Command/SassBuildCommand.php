<?php

/*
 * This file is part of the SymfonyCasts SassBundle package.
 * Copyright (c) SymfonyCasts <https://symfonycasts.com/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Misc\Command;

use Symfonycasts\SassBundle\SassBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfonycasts\SassBundle\Command\SassBuildCommand as BaseSassBuildCommand;

#[AsCommand(
    name: 'sass:build-clean',
    description: 'Builds the Sass assets then remove the folder public/assets'
)]
class SassBuildCommand extends BaseSassBuildCommand
{
    public function __construct(
        private SassBuilder $sassBuilder,
    ) {
        parent::__construct($sassBuilder);
    }

    protected function configure(): void
    {
        $this->addOption('watch', 'w', null, 'Watch for changes and rebuild automatically');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->sassBuilder->setOutput($io);

        $process = $this->sassBuilder->runBuild(
            $input->getOption('watch')
        );

        $process->wait(function ($type, $buffer) use ($io) {
            $io->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $io->error('Sass build failed');

            return self::FAILURE;
        }
        $dirname = 'public/assets';
        $this->delete_all($dirname);
        return self::SUCCESS;
    }

    private function delete_all( $item ) {
        if ( is_dir( $item ) ) {
            forEach(array_diff( 
                    glob( "$item/{,.}*", GLOB_BRACE ), 
                    array( "$item/.", "$item/.." ) 
                ) as $child) 
            {
                $this->delete_all($child);
            }
            rmdir( $item );
        } else {
            unlink( $item );
        }
    }
}
