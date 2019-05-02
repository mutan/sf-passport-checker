<?php

namespace App\Service;

use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * БНП – база недействительный паспортов
 */
class PassportService
{
    const DATA_PATH = '/var/data/passports/'; # starting from 'kernel.project-dir'

    const VERSION_FILE = 'version.txt';
    const PROGRESS_FILE = 'progress.txt';
    const EXPIRED_PASSPORTS_BZ2_FILE = 'expired_passports.csv.bz2';
    const EXPIRED_PASSPORTS_SCV_FILE = 'expired_passports.csv';

    const PROGRESS_COMPLETED = 'completed';
    const PROGRESS_PROCESSING = 'processing';

    private $em;
    private $logger;
    private $projectDir;

    public function __construct(EntityManagerInterface $em, LoggerInterface $logger, $projectDir)
    {
        $this->em = $em;
        $this->logger = $logger;
        $this->projectDir = $projectDir;
    }

    /**
     * Конвертирует строку из константы DATA_PATH в валидный путь для текущей ОС
     * @return string
     * @throws Exception
     */
    public function getSanitizedPath(): string
    {
        $pieces = array_filter(explode('/', self::DATA_PATH));
        $path = $this->projectDir . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $pieces) . DIRECTORY_SEPARATOR;
        if (!file_exists($path) ){
            if (!mkdir($path, 0755, true)) {
                throw new Exception('Expired passports: cannot create data directory.');
            }
            $this->logger->info('Created directory: ' . $path);
        }
        return $path;
    }

    /**
     * Возвращает путь к файлу по его имени
     * Если файл не существует, создает его
     * @param string $name
     * @return string
     * @throws Exception
     */
    public function getFile(string $name): string
    {
        $file = $this->getSanitizedPath() . $name;
        if (!file_exists($file)) {
            if (!touch($file)) {
                throw new Exception('Cannot make file.');
            }
            $this->logger->info('Created file: ' . $name);
        }
        return $file;
    }

    /**
     * Получить путь к файлу, содержащему версию БНП
     * @return string
     * @throws Exception
     */
    public function getVersionFile(): string
    {
        return $this->getFile(PassportService::VERSION_FILE);
    }

    /**
     * Получить путь к файлу, содержащему прогресс обработки БНП
     * @return string
     * @throws Exception
     */
    public function getProgressFile(): string
    {
        return $this->getFile(PassportService::PROGRESS_FILE);
    }

    /**
     * Получить версию БНП
     * @return string
     * @throws Exception
     */
    public function getVersion(): string
    {
        return file_get_contents($this->getVersionFile());
    }

    /**
     * Установить версию БНП
     * @param string $text
     * @return string
     * @throws Exception
     */
    public function setVersion(string $text): string
    {
        if (!file_put_contents($this->getVersionFile(), $text)) {
            throw new Exception('Cannot write version to file.');
        }
        $this->logger->info('Version set to: ' . $text);
        return $text;
    }

    /**
     * Получить версию прогресса обработки БНП
     * @return string
     * @throws Exception
     */
    public function getProgress(): string
    {
        return file_get_contents($this->getProgressFile());
    }

    /**
     * Установить версию прогресса обработки БНП
     * @param string $text
     * @return string
     * @throws Exception
     */
    public function setProgress(string $text): string
    {
        if (!file_put_contents($this->getProgressFile(), $text)) {
            throw new Exception('Cannot write progress to file.');
        }
        $this->logger->info('Progress set to: ' . $text);
        return $text;
    }

    public function arrayToString(array $data): string
    {
        return strval($data[0] . $data[1]);
    }

    public function stringToArray(string $seriesnumber): array
    {


        if (strlen($seriesnumber) < 10) {
            $seriesnumber .= str_repeat('0', 10 - strlen($seriesnumber));
        }
        dump([
            'series' => substr($seriesnumber, 0, 4),
            'number' => substr($seriesnumber, -6, 6)
        ]); die('ok');


        
        return [
            'series' => substr($seriesnumber, 0, 4),
            'number' => substr($seriesnumber, -6, 6)
        ];
    }

    /**
     * Проверить массив паспортов на недействительность
     * @param array $data
     * @return array
     */
    public function check(array $data): array
    {
        $result = [];
        $where = '';
        foreach ($data as $item) {
            if (count($item) != 2) {
                throw new InvalidArgumentException('Item must have series and number only');
            }
            if (!is_string($item[0]) ||
                !is_string($item[1]) ||
                !preg_match('/\d{4}/', $item[0]) ||
                !preg_match('/\d{6}/', $item[1])
            ) {
                throw new InvalidArgumentException('Wrong format: series must be 4-digit string, and number must be 6-digit string');
            }
            $seriesnumber = $this->arrayToString($item);
            dump($seriesnumber); die('ok');
            $where .= " OR (\"seriesnumber\" = '$seriesnumber')";
        }
        if ($where) {
            $limit = count($data);
            $where = ltrim($where, "OR ");
            $sql = "SELECT seriesnumber FROM passport WHERE {$where} LIMIT {$limit}";
            dump($sql); die('ok');
            if ($resultItems = $this->em->getConnection()->fetchAll($sql)) {
                foreach ($resultItems as $resultItem) {
                    $result[] = $this->strToArray($resultItem['seriesnumber']);
                }
            }
        }
        dump($result); die('ok');
        return $result;
    }
}
