<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PassportUpdateCommand extends Command
{
    const SOURCE_URL = 'https://guvm.mvd.ru/upload/expired-passports/list_of_expired_passports.csv.bz2';

    private $projectDir;

    public function __construct($projectDir)
    {
        $this->projectDir = $projectDir;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('app:passport:update')
             ->setDescription('Download, parse and save to DB file with passport data.')
             ->setHelp('This command allows you to download and parse file with passport data, handle that data and then save to database.');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $versionFile = $this->projectDir . implode(DIRECTORY_SEPARATOR, ['', 'var', 'data', 'version.txt']);
        //file_put_contents($versionFile, date("Y-m-d H:i:s"));


        $headers = get_headers(self::SOURCE_URL, 1);
        
        dump($versionFile); die('ok');


        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');
    }
}
