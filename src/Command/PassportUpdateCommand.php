<?php

namespace App\Command;

use App\Service\PassportService;
use Exception;
use Psr\Log\LoggerInterface;
use GuzzleHttp\Client;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PassportUpdateCommand extends Command
{
    const SOURCE_URL = 'https://guvm.mvd.ru/upload/expired-passports/list_of_expired_passports.csv.bz2';
    const BATCH_INSERT = 10000;

    private $em;
    private $logger;
    private $projectDir;
    private $passportService;

    public function __construct(EntityManagerInterface $em, LoggerInterface $logger, PassportService $passportService, $projectDir)
    {
        $this->em = $em;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
        $this->passportService = $passportService;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('app:passport:update')
             ->setDescription('Download, parse and save to DB file with passport data.')
             ->setHelp('This command allows you to download and parse file with passport data, handle that data and then save to database.');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        //$io = new SymfonyStyle($input, $output);

        $versionFile = $this->passportService->getVersionFile();
        $bz2File = $this->passportService->getFile(PassportService::EXPIRED_PASSPORTS_BZ2_FILE);
        $csvFile = $this->passportService->getFile(PassportService::EXPIRED_PASSPORTS_SCV_FILE);
        $version = $this->passportService->getVersion();

        $headers = get_headers(self::SOURCE_URL, 1);
        if (!is_array($headers) || !array_key_exists('Last-Modified', $headers)) {
            throw new Exception('Expired passports: wrong header.');
        }

        /* Check version without downloading file */
        if ($headers['Last-Modified'] != $version) { // !=
            /* Update version */
            file_put_contents($versionFile, $headers['Last-Modified']);

            /* Download and save file */
            /*$output->writeln('Downloading...');
            $filePointer = fopen($bz2File, 'wb');
            $client = new Client();
            $response = $client->get(self::SOURCE_URL, ['sink' => $filePointer]);
            fclose($filePointer);
            if (200 != $response->getStatusCode()) {
                throw new Exception('Expired passports: wrong response code.');
            }*/

            /* Extract csv file from archive */
            /*$output->writeln('Extracting...');
            $this->bunzip2($bz2File, $csvFile);

            $output->writeln('Extracted.');*/

            $this->executeQuery('DROP TABLE IF EXISTS passport_new');
            $this->executeQuery(
                'CREATE TABLE passport_new (
                        series character varying(4) NOT NULL,
                        number character varying(6) NOT NULL,
                        PRIMARY KEY (series, number)
                    )'
            );

            $processed = 0;
            $output->writeln('Inserting to database...');
            if (($handle = fopen($csvFile, 'r')) !== false) {
                fgets($handle); // skip first line with columns headers
                $passportList = [];
                while ($str = fgets($handle)) {
                    $data = explode(',', trim($str));
                    if (is_numeric($data[0]) && is_numeric($data[1])) {
                        $passportList[] = array_slice($data, 0, 2);
                        if (count($passportList) >= self::BATCH_INSERT) {
                            $this->flushPassportData($passportList);
                            $processed += count($passportList);
                            $passportList = [];
                            $output->write('.');
                        }
                    }
                }
                fclose($handle);
                if (!empty($passportList)) {
                    $this->flushPassportData($passportList);
                    $processed += count($passportList);
                    $output->write('.');
                }

                $this->em->getConnection()->exec("ALTER TABLE passport RENAME TO passport_old");
                $this->em->getConnection()->exec("ALTER TABLE passport_new RENAME TO passport");
                $this->em->getConnection()->exec("DROP TABLE passport_old");
                unlink($csvFile);
                $output->writeln("Complete $processed records");
            } else {
                throw new Exception('Unable to open csv file');
            }
        }
    }

    /**
     * @param array $data
     * @throws \Doctrine\DBAL\DBALException
     */
    private function flushPassportData(array &$data)
    {
        if (empty($data)) {
            return;
        }

        $toInsert = '';
        foreach ($data as $item) {
            $toInsert .= "('{$item[0]}', '{$item[1]}'), ";
        }
        $toInsert = rtrim($toInsert, ', ');

        $this->executeQuery("INSERT INTO passport_new (series, number) VALUES {$toInsert} ON CONFLICT DO NOTHING");
    }

    /**
     * @param $sql
     * @return mixed
     * @throws \Doctrine\DBAL\DBALException
     */
    public function executeQuery($sql)
    {
        $stmt = $this->em->getConnection()->prepare($sql);
        $stmt->execute();
        return $stmt;
    }

    /**
     * @return bool
     * @param string $in
     * @param string $out
     * @desc uncompressing the file with the bzip2-extension
     */
    function bunzip2 ($in, $out)
    {
        if (!file_exists ($in) || !is_readable ($in)) {
            return false;
        }
        if ((!file_exists ($out) && !is_writeable (dirname ($out)) || (file_exists($out) && !is_writable($out)) )) {
            return false;
        }

        $in_file = bzopen ($in, "r");
        $out_file = fopen ($out, "wb");

        while ($buffer = bzread ($in_file, 4096)) {
            fwrite ($out_file, $buffer, 4096);
        }

        bzclose ($in_file);
        fclose ($out_file);

        return true;
    }
}
