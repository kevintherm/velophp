<?php

namespace App\Domain\SchemaManagement\Infrastructure;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class SchemaLock
{
    /**
     * Attempts to acquire a MySQL-level lock for schema operations on a specific collection.
     *
     * @param int $collectionId
     * @param int $timeoutSeconds
     * @return bool True if acquired, false otherwise
     */
    public function acquire(int $collectionId, int $timeoutSeconds = 5): bool
    {
        $lockName = "schema_change:collection_{$collectionId}";
        
        $result = DB::selectOne("SELECT GET_LOCK(?, ?) AS acquired", [$lockName, $timeoutSeconds]);
        
        return (bool) ($result->acquired ?? false);
    }

    /**
     * Releases the previously acquired lock.
     *
     * @param int $collectionId
     */
    public function release(int $collectionId): void
    {
        $lockName = "schema_change:collection_{$collectionId}";
        DB::statement("SELECT RELEASE_LOCK(?)", [$lockName]);
    }
    
    /**
     * Lock wrapper utility.
     */
    public function executeWithLock(int $collectionId, callable $callback)
    {
        if (!$this->acquire($collectionId)) {
            throw new RuntimeException("Could not acquire schema lock for collection {$collectionId}");
        }
        
        try {
            return $callback();
        } finally {
            $this->release($collectionId);
        }
    }
}
