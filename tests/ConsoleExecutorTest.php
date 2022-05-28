<?php

namespace Test\Lucinda\Migration;

use Lucinda\UnitTest\Result;

class ConsoleExecutorTest
{
    public function execute()
    {
        return new Result(false, "Console is not unit testable");
    }
}
