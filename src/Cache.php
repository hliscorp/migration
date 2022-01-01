<?php
namespace Lucinda\Migration;

/**
 * Encapsulates a cache in which migration progress is saved along with operations available
 */
interface Cache
{
    /**
     * Checks if cache exists physically
     *
     * @return bool
     */
    public function exists(): bool;
    
    /**
     * Creates cache if not exists
     */
    public function create(): void;
    
    /**
     * Gets script from cache by class name and runtime status
     *
     * @return \Lucinda\Migration\Status[string]
     */
    public function read(): array;
    
    /**
     * Inserts or updates entry in cache by class name and runtime status
     *
     * @param string $className
     * @param Status $statusCode
     */
    public function add(string $className, Status $statusCode): void;
    
    /**
     * Removes entry from cache by class name
     *
     * @param string $className
     */
    public function remove(string $className): void;
}
