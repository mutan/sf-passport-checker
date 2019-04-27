<?php

namespace App\Command;

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
    const BATCH_INSERT = 10;

    private $em;
    private $logger;
    private $projectDir;

    public function __construct(EntityManagerInterface $em, LoggerInterface $logger, $projectDir)
    {
        $this->em = $em;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
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

        $path = $this->getSanitizedPath('/var/data/passports/');
        if (!file_exists($path) ){
            if (!mkdir($path, 0755, true)) {
                throw new Exception('Expired passports: cannot create directory.');
            }
        }

        $versionFilePath = $this->getOrCreateFilePath('version.txt', $path);
        $bz2FilePath = $this->getOrCreateFilePath('expired_passports.csv.bz2', $path);
        $csvFilePath = $this->getOrCreateFilePath('expired_passports.csv', $path);
        $version = file_get_contents($versionFilePath);

        $headers = get_headers(self::SOURCE_URL, 1);
        if (!is_array($headers) || !array_key_exists('Last-Modified', $headers)) {
            throw new Exception('Expired passports: wrong header.');
        }

        /* Check version without downloading file */
        if ($headers['Last-Modified'] == $version) { // !=
            /* Update version */
            file_put_contents($versionFilePath, $headers['Last-Modified']);

            /* Download and save file */
            /*$output->writeln('Downloading...');
            $filePointer = fopen($bz2FilePath, 'wb');
            $client = new Client();
            $response = $client->get(self::SOURCE_URL, ['sink' => $filePointer]);
            fclose($filePointer);
            if (200 != $response->getStatusCode()) {
                throw new Exception('Expired passports: wrong response code.');
            }*/

            /* Extract csv file from archive */
            /*$output->writeln('Extracting...');
            $this->bunzip2($bz2FilePath, $csvFilePath);

            $output->writeln('Extracted.');*/

            $this->executeQuery(
                'CREATE TABLE passport_new (
                        "series" varchar(4) NOT NULL,
                        "number" varchar(6) NOT NULL,
                        PRIMARY KEY ("series", "number")
                     )'
            );

            $processed = 0;
            $output->writeln('Inserting to database...');
            if (($handle = fopen($csvFilePath, 'r')) !== false) {
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
                unlink($csvFilePath);
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

        $this->em->getConnection()->exec(
            "INSERT INTO passport_new (series, number) VALUES {$toInsert} ON CONFLICT DO NOTHING"
        );
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
        return $stmt->fetchAll();
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

    public function getSanitizedPath(string $path): string
    {
        $pieces = array_filter(explode('/', $path));
        return $this->projectDir . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $pieces) . DIRECTORY_SEPARATOR;
    }

    /**
     * @param string $name
     * @param string $path
     * @return string
     */
    protected function getOrCreateFilePath(string $name, string $path): string
    {
        $filePath = $path . $name;
        if (!file_exists($filePath)) {
            touch($filePath);
        }
        return $filePath;
    }
}
