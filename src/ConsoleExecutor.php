<?php
namespace Lucinda\Migration;

use Lucinda\Console\Text;

/**
 * Performs migrations and binds to Console API to display results in console.
 */
class ConsoleExecutor
{
    private bool $isWindows = false;
    private Wrapper $wrapper;
        
    /**
     * Registers migration objects based on folder they are located into and cache table
     *
     * @param string $folder Absolute location of migration files folder
     * @param Cache $cache Cache holding migrations progress
     */
    public function __construct(string $folder, Cache $cache)
    {
        try {
            // detects caller operating system
            $this->isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
            
            // creates migration cache if not exists
            if (!$cache->exists()) {
                $cache->create();
            }
            
            // instances wrapper
            $this->wrapper = new Wrapper($folder, $cache);
        } catch (\Throwable $e) {
            $this->displayError($e);
            exit(1);
        }
    }
    
    /**
     * Executes a migration operation and displays results on console. Program accepts following operations:
     * - migrate (default): executes "up" command for all Script-s whose status is PENDING or FAILED and returns results
     * - up: executes "up" command for Script whose status is PENDING or FAILED and returns result
     * - down: executes "down" command for Script by class name whose status is PASSED and returns result
     * - generate: generates a new migration class in migrations folder and returns its name
     *
     * @param string $operation Name of operation to execute
     * @param string $className Migration class name operation should be focused on
     */
    public function execute(string $operation, string $className=""): void
    {
        try {
            switch ($operation) {
                case "generate":
                    $className = $this->wrapper->generate();
                    echo $className.PHP_EOL;
                    break;
                case "migrate":
                    $results = $this->wrapper->migrate();
                    $table = new \Lucinda\Console\Table($this->getDecoratedColumns(["Class", "Status", "Message"]));
                    foreach ($results as $result) {
                        if ($result->getStatus()==Status::FAILED) {
                            $table->addRow([$result->getClassName(), $this->getDecoratedStatus($result->getStatus()), $result->getThrowable()->getMessage()]);
                        } else {
                            $table->addRow([$result->getClassName(), $this->getDecoratedStatus($result->getStatus()), ""]);
                        }
                    }
                    echo $table."\n";
                    break;
                case "up":
                case "down":
                    if (empty($className)) {
                        throw new Exception("Class name not supplied!");
                    }
                    $result = $this->wrapper->$operation($className);
                    $table = new \Lucinda\Console\Table($this->getDecoratedColumns(["Status", "Message"]));
                    if ($result->getStatus()==Status::FAILED) {
                        $table->addRow([$this->getDecoratedStatus($result->getStatus()), $result->getThrowable()->getMessage()]);
                    } else {
                        $table->addRow([$this->getDecoratedStatus($result->getStatus()), ""]);
                    }
                    echo $table."\n";
                    break;
                default:
                    throw new Exception("Unsupported operation: ".$operation);
                    break;
            }
        } catch (\Throwable $e) {
            $this->displayError($e);
        }
    }
    
    /**
     * Styles columns for console (unless OS is windows)
     *
     * @param array $columns
     * @return array
     */
    private function getDecoratedColumns(array $columns): array
    {
        if (!$this->isWindows) {
            return array_map(function ($column) {
                $text = new \Lucinda\Console\Text($column);
                $text->setFontStyle(\Lucinda\Console\FontStyle::BOLD);
                return $text;
            }, $columns);
        } else {
            return array_map(function ($column) {
                return strtoupper($column);
            }, $columns);
        }
    }
    
    /**
     * Styles migration Status-es for console (unless OS is windows)
     *
     * @param Status $statusCode
     * @return string|Text
     */
    private function getDecoratedStatus(Status $statusCode): string|Text
    {
        $text = null;
        if (!$this->isWindows) {
            switch ($statusCode) {
                case Status::PENDING:
                    $text = new \Lucinda\Console\Text(" PENDING ");
                    $text->setBackgroundColor(\Lucinda\Console\BackgroundColor::BLUE);
                    break;
                case Status::FAILED:
                    $text = new \Lucinda\Console\Text(" FAILED ");
                    $text->setBackgroundColor(\Lucinda\Console\BackgroundColor::RED);
                    break;
                case Status::PASSED:
                    $text = new \Lucinda\Console\Text(" PASSED ");
                    $text->setBackgroundColor(\Lucinda\Console\BackgroundColor::GREEN);
                    break;
            }
        } else {
            switch ($statusCode) {
                case Status::PENDING:
                    $text = "PENDING";
                    break;
                case Status::FAILED:
                    $text = "FAILED";
                    break;
                case Status::PASSED:
                    $text = "PASSED";
                    break;
            }
        }
        return $text;
    }
    
    /**
     * Displays error to caller
     *
     * @param \Throwable $throwable
     */
    private function displayError(\Throwable $throwable): void
    {
        if (!$this->isWindows) {
            $text = new \Lucinda\Console\Text(" ERROR ");
            $text->setFontStyle(\Lucinda\Console\FontStyle::BOLD);
            $text->setBackgroundColor(\Lucinda\Console\BackgroundColor::LIGHT_RED);
            echo $text->getStyledValue()." ".$throwable->getMessage().PHP_EOL;
        } else {
            echo "ERROR: ".$throwable->getMessage().PHP_EOL;
        }
    }
}
