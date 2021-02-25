<?php
namespace Test\Lucinda\Migration;

class MockCache implements \Lucinda\Migration\Cache
{
    private $db=[];
    
    public function exists(): bool
    {
        return true;
    }
    
    public function create(): void
    {
        $this->db = [];
    }
        
    public function add(string $className, int $statusCode): void
    {
        $this->db[$className] = $statusCode;
    }

    public function read(): array
    {
        return $this->db;
    }
    
    public function remove(string $className): void
    {
        unset($this->db[$className]);
    }
}
