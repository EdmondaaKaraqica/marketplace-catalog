<?php

namespace App\Service;

/**
 * Immutable summary of a single catalog import run.
 */
final class ImportResult
{
    public function __construct(
        public readonly int $productsCreated = 0,
        public readonly int $productsUpdated = 0,
        public readonly int $productsDeleted = 0,
        public readonly int $categoriesCreated = 0,
        public readonly int $categoriesDeleted = 0,
    ) {
    }
}
