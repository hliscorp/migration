<?php
namespace Lucinda\Migration;

require_once("Status.php");

/**
 * Encapsulates results of a Script up/down command, characterized by:
 * - class: name of Script class
 * - status: one of Status enum values
 */
class Result
{
    private $className;
    private $status;
    private $throwable;
    
    /**
     * Saves results by name of Script class and status code
     *
     * @param string $className
     * @param Status $status
     */
    public function __construct(string $className, int $status)
    {
        $this->className = $className;
        $this->status = $status;
    }
    
    /**
     * Sets throwable that happened when up/down fails
     *
     * @param \Throwable $throwable
     */
    public function setThrowable(\Throwable $throwable)
    {
        $this->throwable = $throwable;
    }
    
    /**
     * Sets throwable that happened when up/down command failed
     *
     * @return \Throwable|NULL
     */
    public function getThrowable(): ?\Throwable
    {
        return $this->throwable;
    }
    
    /**
     * Gets name of Script class (corresponding to file name)
     *
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }
    
    /**
     * Gets status code associated with results of up/down command
     *
     * @return Status
     */
    public function getStatus(): int
    {
        return $this->status;
    }
}
