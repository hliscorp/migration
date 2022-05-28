<?php

namespace Test\Lucinda\Migration;

use Lucinda\Migration\Result;
use Lucinda\Migration\Status;

class ResultTest
{
    private $result;

    public function __construct()
    {
        $this->result = new Result("Foo\\Bar", Status::FAILED);
    }

    public function setThrowable()
    {
        $this->result->setThrowable(new \Exception("test"));
        return new \Lucinda\UnitTest\Result(true);
    }


    public function getThrowable()
    {
        return new \Lucinda\UnitTest\Result($this->result->getThrowable()->getMessage()=="test");
    }


    public function getClassName()
    {
        return new \Lucinda\UnitTest\Result($this->result->getClassName()=="Foo\\Bar");
    }


    public function getStatus()
    {
        return new \Lucinda\UnitTest\Result($this->result->getStatus()==Status::FAILED);
    }
}
