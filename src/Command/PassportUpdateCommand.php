<?php

namespace App\Command;

use Exception;
use GuzzleHttp\Client;
use Psr\Log\LoggerInterface;
use App\Service\PassportService;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use App\Service\BaseStopwatch as Stopwatch;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\LockableTrait;

class PassportUpdateCommand extends Command
{
    use LockableTrait;

    protected const SOURCE_URL = 'https://guvm.mvd.ru/upload/expired-passports/list_of_expired_passports.csv.bz2';
    protected const BATCH_INSERT = 100000;
    protected const SW_FIRST = 'first';

    private $em;
    private $logger;
    private $stopwatch;
    private $passportService;

    public function __construct(EntityManagerInterface $em, LoggerInterface $logger, PassportService $passportService)
    {
        $this->em = $em;
        $this->logger = $logger;
        $this->passportService = $passportService;
        $this->stopwatch = new Stopwatch();
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
     * @return int|null
     * @throws DBALException
     * @throws Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$this->lock()) {
            $this->logger->info('The command is already running in another process. Terminated.');
            return false;
        }

        $this->stopwatch->start(self::SW_FIRST);
        $this->log('Script started.', self::SW_FIRST);
        $this->passportService->setLogger($this->logger);

        $bz2File = $this->passportService->getFile(PassportService::EXPIRED_PASSPORTS_BZ2_FILE);
        $csvFile = $this->passportService->getFile(PassportService::EXPIRED_PASSPORTS_SCV_FILE);
        $version = $this->passportService->getVersion();
        $versionNew = '';
        $progress = $this->passportService->getProgress();
        if (!$progress) {
            $progress = $this->passportService->setProgress(PassportService::PROGRESS_COMPLETED);
        }

        if ($progress == PassportService::PROGRESS_COMPLETED) {
            $headers = get_headers(self::SOURCE_URL, 1);
            if (!is_array($headers) || !array_key_exists('Last-Modified', $headers)) {
                throw new Exception('Expired passports file: wrong headers.');
            }

            $versionNew = $headers['Last-Modified'];

            /* Check version without downloading file */
            if ($versionNew == $version) {
                $this->log('Expired passports file is up to date.', self::SW_FIRST);
            } else {
                /* Download and save file */
                $this->log('Downloading archive file started.', self::SW_FIRST);
                $filePointer = fopen($bz2File, 'wb');
                $client = new Client();
                $response = $client->get(self::SOURCE_URL, ['sink' => $filePointer]);
                fclose($filePointer);
                if (200 != $response->getStatusCode()) {
                    throw new Exception('Expired passports: wrong response code.');
                }
                $this->log('Downloading archive file finished.', self::SW_FIRST);

                /* Extract csv file from archive */
                $this->log('Extracting archive file started.', self::SW_FIRST);
                if (!$this->bunzip2($bz2File, $csvFile)) {
                    throw new Exception('Cannot extract archive file.');
                }
                $this->log('Extracting archive file finished.', self::SW_FIRST);
                if (file_exists($bz2File)) {
                    unlink($bz2File);
                }
                $this->log('Archive file deleted.', self::SW_FIRST);

                $this->executeQuery('ALTER DATABASE passport SET random_page_cost=1.4;');
                $this->executeQuery('DROP TABLE IF EXISTS passport_new');
                $this->executeQuery('CREATE TABLE passport_new (seriesnumber character varying(10) NOT NULL UNIQUE)' );
                //$this->executeQuery('CREATE INDEX ind_sn ON passport_new USING btree (seriesnumber)');

                /* Update progress */
                $progress = $this->passportService->setProgress(PassportService::PROGRESS_PROCESSING);
            }
        }

        if ($progress != PassportService::PROGRESS_COMPLETED) {
            $processed = 0;
            $batch = 0;
            $this->log('Inserting to database...', self::SW_FIRST);
            if (($handle = fopen($csvFile, 'r')) !== false) {
                fgets($handle); // skip first line with columns headers
                $passportList = [];
                while ($str = fgets($handle)) {
                    $data = explode(',', trim($str));
                    if (is_numeric($data[0]) && is_numeric($data[1])) {
                        $passportList[] = $this->passportService->arrayToString($data);
                        if (count($passportList) >= self::BATCH_INSERT) {
                            $this->flushPassportData($passportList);
                            $processed += count($passportList);
                            $batch++;
                            $this->log(sprintf('Inserted: batch %04d, processed %09d records.', $batch, $processed), self::SW_FIRST);
                            $passportList = [];
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
                if (file_exists($csvFile)) {
                    unlink($csvFile);
                }
                $this->log("Complete $processed records.", self::SW_FIRST);
            } else {
                throw new Exception('Unable to open csv file');
            }

            /* Update version and progress */
            if ($versionNew) {
                $this->passportService->setVersion($versionNew);
            }
            $this->passportService->setProgress(PassportService::PROGRESS_COMPLETED);
        }

        $this->release();
        return true;
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
            $toInsert .= "('{$item}'), ";
        }
        $toInsert = rtrim($toInsert, ', ');

        $this->executeQuery("INSERT INTO passport_new (seriesnumber) VALUES {$toInsert} ON CONFLICT DO NOTHING");
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

    private function log($message, $eventName)
    {
        $this->logger->info($this->stopwatch->getFormattedDuration($eventName) . ' ' . $message);
    }

    /**
     * @return bool
     * @param string $in
     * @param string $out
     * @desc uncompressing the file with the bzip2-extension
     */
    function bunzip2($in, $out)
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
