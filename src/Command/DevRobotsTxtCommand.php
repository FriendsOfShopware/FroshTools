<?php

declare(strict_types=1);

namespace Frosh\Tools\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand('frosh:dev:robots-txt', 'For testshops - add/change robots.txt to stop crawlers')]
class DevRobotsTxtCommand extends Command
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $envPath
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('remove', 'r', InputOption::VALUE_NONE, 'Return to original file - delete input from this command');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $robotsPath = $this->envPath . '/robots.txt';
        $originalState = $input->getOption('remove');
        $fileExists = file_exists($robotsPath);

        // robots.txt exists in public folder
        if ($fileExists) {
            if ($originalState) {
                return $this->revertToOriginal($input, $output, $robotsPath);
            }

            return $this->changeRobotsTxt($input, $output, $robotsPath);
        }

        // robots.txt does not exist in public folder
        if ($originalState) {
            $io->error('There is no robots.txt in public folder');

            return self::SUCCESS;
        }

        $robotsFile = fopen($robotsPath, 'wb');
        \assert($robotsFile !== false);

        $robotsContent = "#soc\nUser-agent: *\nDisallow: /\n#eoc";
        fwrite($robotsFile, $robotsContent);
        fclose($robotsFile);

        $io->success('robots.txt created :)');

        return self::SUCCESS;
    }

    private function revertToOriginal(InputInterface $input, OutputInterface $output, string $robotsPath): int
    {
        // returns robots.txt to original state
        $io = new SymfonyStyle($input, $output);

        $file = (string) file_get_contents($robotsPath);
        $createdString = "#soc\nUser-agent: *\nDisallow: /\n#eoc";

        // If only input from command is present
        if ($file === $createdString) {
            unlink($robotsPath);
            $io->success('robots.txt file deleted :)');

            return self::SUCCESS;
        }

        // removes everything between #soc & #eoc
        $content = preg_replace('/#soc[\s\S]+?#eoc/', '', $file);

        file_put_contents($robotsPath, $content);
        $io->success('robots.txt reverted to original :)');

        return self::SUCCESS;
    }

    private function changeRobotsTxt(InputInterface $input, OutputInterface $output, string $robotsPath): int
    {
        // change robots.txt to disable crawlers
        $io = new SymfonyStyle($input, $output);

        $file = (string) file_get_contents($robotsPath);
        $commandString = "#soc\nUser-agent: *\nDisallow: /\n#eoc";

        // If command is called multiple times
        if (str_contains($file, $commandString)) {
            $io->error('Command was already executed before');

            return self::SUCCESS;
        }

        $content = "#soc\nUser-agent: *\nDisallow: /\n#eoc\n" . $file;
        file_put_contents($robotsPath, $content);

        $io->success('robots.txt changed :)');

        return self::SUCCESS;
    }
}
