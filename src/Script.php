<?php

namespace Lucinda\Migration;

/**
 * Blueprint of a Script that is subject to migration
 */
interface Script
{
    /**
     * Commits changes
     */
    public function up(): void;

    /**
     * Rolls back changes
     */
    public function down(): void;
}
