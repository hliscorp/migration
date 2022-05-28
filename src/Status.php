<?php

namespace Lucinda\Migration;

/**
 * Enum encapsulating status of up/down command results
 */
enum Status: int
{
    case PENDING = 1;
    case FAILED = 2;
    case PASSED = 3;
}
