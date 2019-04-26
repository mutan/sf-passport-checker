<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PassportUpdateCommand extends Command
{
    protected function configure()
    {
        $this->setName('app:passport:update')
             ->setDescription('Download, parse and save to DB file with passport data.')
             ->addArgument('arg1', InputArgument::OPTIONAL, 'Argument description')
             ->addOption('option1', null, InputOption::VALUE_NONE, 'Option description')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);



        $io->success('You have a new command! Now make it your own! Pass --help to see your options.');
    }
}
