<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use Frosh\Tools\Components\Twig\TwigCacheWarmer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('frosh:twig:warmup', 'Warm up the Twig template cache')]
class TwigWarmupCommand extends Command
{
    public function __construct(
        private readonly TwigCacheWarmer $twigCacheWarmer,
        #[Autowire('%kernel.cache_dir%')]
        private readonly string $cacheDir,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->comment('Warming up Twig template cache...');

        $files = $this->twigCacheWarmer->warmUp($this->cacheDir);

        $io->success(\sprintf('Warmed up %d Twig template(s).', \count($files)));

        return self::SUCCESS;
    }
}
