<?php
namespace Lucinda\Migration;

/**
 * Enum encapsulating status of up/down command results
 */
interface Status
{
    const PENDING = 1;
    const FAILED = 2;
    const PASSED = 3;
}
