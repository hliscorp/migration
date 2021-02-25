<?php
namespace Lucinda\Migration;

/**
 * Performs migration process based on XML received
 */
class Wrapper
{
    private $folder;
    private $instances = [];
    private $cache;
    
    /**
     * Sets up Cache and Script instances based on migration path received
     *
     * @param string $folder
     * @param Cache $cache
     * @throws Exception
     */
    public function __construct(string $folder, Cache $cache)
    {
        // sets folder that stores migrations
        if (!file_exists($folder) || !is_dir($folder)) {
            throw new Exception("Migration folder not found: ".$folder);
        }
        $this->folder = $folder;
        
        // sets cache
        $this->cache = $cache;
        
        // sets migration classes instances
        $files = scandir($folder);
        foreach ($files as $classPath) {
            if (in_array($classPath, [".", ".."])) {
                continue;
            }
            require_once($folder."/".$classPath);
            $className = str_replace(".php", "", $classPath);
            $object = new $className();
            if (!($object instanceof Script)) {
                throw new Exception($className." must be instanceof \Lucinda\Migration\Script");
            }
            $this->instances[$className] = $object;
        }
    }
    
    /**
     * Generates a migration class
     *
     * @return string
     */
    public function generate(): string
    {
        $className = "Version".date("YmdHis");
        $contents = str_replace("TemplateScript", $className, file_get_contents(dirname(__DIR__)."/templates/TemplateScript.php"));
        file_put_contents($this->folder."/".$className.".php", $contents);
        return $className;
    }
    
    /**
     * Runs all remaining migration Script-s (those whose Status is PENDING/FAILED) and returns results
     *
     * @return Result[]
     */
    public function migrate(): array
    {
        $output = [];
        $cacheEntries = $this->cache->read();
        foreach ($this->instances as $className=>$instance) {
            if (!isset($cacheEntries[$className]) || $cacheEntries[$className]==Status::FAILED) {
                $result = $this->goUp($cacheEntries, $className, $instance);
                $output[] = $result;
                if ($result->getThrowable()) {
                    break;
                }
            }
        }
        return $output;
    }
    
    /**
     * Runs DOWN (roll back) command migration Script (whose Status is PASSED) and returns result
     *
     * @return ?Result
     */
    public function down(string $className): ?Result
    {
        if (!isset($this->instances[$className])) {
            throw new Exception($className." not known!");
        }
        $cacheEntries = $this->cache->read();
        if (isset($cacheEntries[$className]) && $cacheEntries[$className]==Status::PASSED) {
            return $this->goDown($cacheEntries, $className, $this->instances[$className]);
        }
        throw new Exception("Migration class not found or not in PASSED state");
    }
        
    /**
     * Runs UP (commit) command migration Script (whose Status is PENDING/FAILED) and returns result
     *
     * @return ?Result
     */
    public function up(string $className): ?Result
    {
        if (!isset($this->instances[$className])) {
            throw new Exception($className." not known!");
        }
        $cacheEntries = $this->cache->read();
        if (!isset($cacheEntries[$className]) || $cacheEntries[$className]==Status::FAILED) {
            return $this->goUp($cacheEntries, $className, $this->instances[$className]);
        }
        throw new Exception("Migration class already in PASSED state");
    }
    
    /**
     * Executes UP (commit) command on Script, updates Cache and returns Result
     *
     * @param array $cacheEntries
     * @param string $className
     * @param Script $instance
     * @return Result
     */
    private function goUp(array $cacheEntries, string $className, Script $instance): Result
    {
        try {
            $instance->up();
            $this->cache->add($className, Status::PASSED);
            return new Result($className, Status::PASSED);
        } catch (\Throwable $throwable) {
            $this->cache->add($className, Status::FAILED);
            $result = new Result($className, Status::FAILED);
            $result->setThrowable($throwable);
            return $result;
        }
    }
    
    /**
     * Executes DOWN (roll back) command on Script, updates Cache and returns Result
     *
     * @param array $cacheEntries
     * @param string $className
     * @param Script $instance
     * @return Result
     */
    private function goDown(array $cacheEntries, string $className, Script $instance): Result
    {
        try {
            $instance->down();
            $this->cache->remove($className);
            return new Result($className, Status::PASSED);
        } catch (\Throwable $throwable) {
            $result = new Result($className, Status::FAILED);
            $result->setThrowable($throwable);
            return $result;
        }
    }
}
