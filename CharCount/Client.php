<?php
/**
 * Author: Diana Ungaro Arnos <hmdiana@gmail.com>
 * Date: 3/10/16
 * Time: 5:12 PM
 */

namespace DianaArnos\DarkmiraTourBR2016\CharCount;

require('Sender.php');

class Client
{
    private $filePath = '';
    private $workerHosts = [];
    private $tmpFolder = 'tmp';
    private $filePieces = 0;
    private $senderPool = [];

    public function __construct($args)
    {
        $this->filePath = $args[1];
        for ($i = 2; $i < count($args); $i++) {
            array_push($this->workerHosts, $args[$i]);
        }

        $this->filePieces = count($this->workerHosts);
    }

    public function run()
    {
        $this->showWorkers();
        $this->splitFile();
        $results = $this->sendFilePiecesToWorkers();
        echo 'Há ' . array_sum($results) . ' caracteres no arquivo ' . $this->filePath . PHP_EOL;
    }

    protected function showWorkers()
    {
        echo "File: " . $this->filePath . "\n";
        echo "Workers: \n";
        foreach ($this->workerHosts as $host) {
            echo $host . "\n";
        }
    }

    private function splitFile()
    {
        echo "O arquivo será dividido em " . count($this->workerHosts) . " parte(s), uma para cada Worker\n";

        $fileSize = filesize($this->filePath);
        $pieceSize = $fileSize / count($this->workerHosts);
        $handle = fopen($this->filePath, 'rb');

        $i = 1;
        while (!feof($handle) && $i <= count($this->workerHosts)) {
            $buffer = fread($handle, round($pieceSize));
            $pieceName = $this->tmpFolder . '/piece_' . $i . '.txt';
            $fw = fopen($pieceName, 'wb');
            fwrite($fw, $buffer);
            fclose($fw);
            $i++;
        }

        fclose($handle);

    }

    private function sendFilePiecesToWorkers()
    {
        $results = [];
        $i = 1;
        foreach ($this->workerHosts as $worker) {
            $handle = fopen($this->tmpFolder . '/piece_' . $i . '.txt', 'rb');
            $text = fread($handle, 1048576); //1Mb
            echo "Iniciando threads...\n";
            $this->senderPool[$worker] = new Sender($worker, $text);
            $this->senderPool[$worker]->start();
            $i++;
        }
        /** @var Sender $sender */
        foreach ($this->senderPool as $sender) {
            $sender->join();
            echo "Encerrando thread...\n";
            array_push($results, $sender->result);
        }

        return $results;
    }
}

$client = new Client($argv);
$client->run();
