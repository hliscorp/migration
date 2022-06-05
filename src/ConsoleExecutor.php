<?php

namespace Lucinda\Migration;

use Lucinda\Console\BackgroundColor;
use Lucinda\Console\FontStyle;
use Lucinda\Console\Table;
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
     * @param Cache  $cache  Cache holding migrations progress
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
                echo $this->formatMultipleResults($results)."\n";
                break;
            case "up":
            case "down":
                if (empty($className)) {
                    throw new Exception("Class name not supplied!");
                }
                $result = $this->wrapper->$operation($className);
                echo $this->formatSingleResult($result)."\n";
                break;
            default:
                throw new Exception("Unsupported operation: ".$operation);
            }
        } catch (\Throwable $e) {
            $this->displayError($e);
        }
    }

    /**
     * Parses list of migration results into a console table
     *
     * @param  Result[] $results
     * @return Table
     * @throws \Exception
     */
    private function formatMultipleResults(array $results): Table
    {
        $table = new Table($this->getDecoratedColumns(["Class", "Status", "Message"]));
        foreach ($results as $result) {
            if ($result->getStatus()==Status::FAILED) {
                $table->addRow(
                    [
                    $result->getClassName(),
                    $this->getDecoratedStatus($result->getStatus()),
                    $result->getThrowable()->getMessage()
                    ]
                );
            } else {
                $table->addRow(
                    [
                    $result->getClassName(),
                    $this->getDecoratedStatus($result->getStatus()),
                    ""
                    ]
                );
            }
        }
        return $table;
    }

    /**
     * Parses a migration result into a console table
     *
     * @param  Result $result
     * @return Table
     * @throws \Exception
     */
    private function formatSingleResult(Result $result): Table
    {
        $table = new Table($this->getDecoratedColumns(["Status", "Message"]));
        if ($result->getStatus()==Status::FAILED) {
            $table->addRow(
                [
                $this->getDecoratedStatus($result->getStatus()),
                $result->getThrowable()->getMessage()
                ]
            );
        } else {
            $table->addRow(
                [
                $this->getDecoratedStatus($result->getStatus()),
                ""
                ]
            );
        }
        return $table;
    }

    /**
     * Styles columns for console (unless OS is windows)
     *
     * @param  string[] $columns
     * @return array<int, Text|string>
     */
    private function getDecoratedColumns(array $columns): array
    {
        if (!$this->isWindows) {
            return array_map(
                function ($column) {
                    $text = new Text($column);
                    $text->setFontStyle(FontStyle::BOLD);
                    return $text;
                },
                $columns
            );
        } else {
            return array_map(
                function ($column) {
                    return strtoupper($column);
                },
                $columns
            );
        }
    }

    /**
     * Styles migration Status-es for console (unless OS is windows)
     *
     * @param  Status $statusCode
     * @return string|Text
     */
    private function getDecoratedStatus(Status $statusCode): string|Text
    {
        $text = null;
        if (!$this->isWindows) {
            switch ($statusCode) {
            case Status::PENDING:
                $text = new Text(" PENDING ");
                $text->setBackgroundColor(BackgroundColor::BLUE);
                break;
            case Status::FAILED:
                $text = new Text(" FAILED ");
                $text->setBackgroundColor(BackgroundColor::RED);
                break;
            case Status::PASSED:
                $text = new Text(" PASSED ");
                $text->setBackgroundColor(BackgroundColor::GREEN);
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
            $text = new Text(" ERROR ");
            $text->setFontStyle(FontStyle::BOLD);
            $text->setBackgroundColor(BackgroundColor::LIGHT_RED);
            echo $text->getStyledValue()." ".$throwable->getMessage().PHP_EOL;
        } else {
            echo "ERROR: ".$throwable->getMessage().PHP_EOL;
        }
    }
}
