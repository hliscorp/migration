<?php

namespace Lucinda\Migration;

/**
 * Encapsulates results of a Script up/down command, characterized by:
 * - class: name of Script class
 * - status: one of Status enum values
 */
class Result
{
    private string $className;
    private Status $status;
    private ?\Throwable $throwable = null;

    /**
     * Saves results by name of Script class and status code
     *
     * @param string $className
     * @param Status $status
     */
    public function __construct(string $className, Status $status)
    {
        $this->className = $className;
        $this->status = $status;
    }

    /**
     * Sets throwable that happened when up/down fails
     *
     * @param \Throwable $throwable
     */
    public function setThrowable(\Throwable $throwable): void
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
    public function getStatus(): Status
    {
        return $this->status;
    }
}
