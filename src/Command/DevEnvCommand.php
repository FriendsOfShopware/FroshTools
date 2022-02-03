<?php

namespace Frosh\Tools\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DevEnvCommand extends Command
{
    public static $defaultName = 'frosh:dev:env';
    public static $defaultDescription = 'For testshops - add/change robots.txt to stop crawling';
    private string $envPath;

    public function __construct(string $envPath)
    {
        parent::__construct();
        $this->envPath = $envPath;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $robotsPath = $this->envPath."/robots.txt";

        $fileExists = file_exists($robotsPath);
        if($fileExists) {
            $output->writeln('robots.txt exists in public folder');

            $file = file_get_contents($robotsPath);
            $content = "User-agent: *\nDisallow: /\n\n".$file;
            file_put_contents($robotsPath, $content);

            $io->success('robots.txt changed :)');

            return self::SUCCESS;
        } 

        $robotsFile = fopen($robotsPath, "w");
        $robotsContent = "User-agent: *\nDisallow: /";
        fwrite($robotsFile, $robotsContent);
        fclose($robotsFile);

        $io->success('robots.txt created :)');

        return self::SUCCESS;
    }
}