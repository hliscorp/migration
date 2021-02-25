<?php
namespace Test\Lucinda\Migration;
    
use Lucinda\Migration\Wrapper;
use Lucinda\UnitTest\Result;
use Lucinda\Migration\Status;

class WrapperTest
{
    private $cache;
    private $className;
    
    public function __construct()
    {
        // create and empty directory
        $directory = dirname(__DIR__)."/migrations";
        if (file_exists($directory)) {
            $files = scandir($directory);
            foreach($files as $file) {
                if(!in_array($file, [".", ".."])) {
                    unlink($directory."/".$file);
                }
            }
        } else {
            mkdir($directory);
        }
        
        $this->cache = new MockCache();
    }

    public function generate()
    {
        $wrapper = new Wrapper(dirname(__DIR__)."/migrations", $this->cache);
        $this->className = $wrapper->generate();
        return new Result($this->className);
    }
        

    public function migrate()
    {
        $wrapper = new Wrapper(dirname(__DIR__)."/migrations", $this->cache);
        $results = $wrapper->migrate();
        return new Result(!empty($results) && $results[0]->getStatus() == Status::PASSED);
    }
    
    
    public function down()
    {
        $wrapper = new Wrapper(dirname(__DIR__)."/migrations", $this->cache);
        $result = $wrapper->down($this->className);
        return new Result($result->getStatus() == Status::PASSED);
    }
        

    public function up()
    {
        $wrapper = new Wrapper(dirname(__DIR__)."/migrations", $this->cache);
        $result = $wrapper->up($this->className);
        return new Result($result->getStatus() == Status::PASSED);
    }
}
