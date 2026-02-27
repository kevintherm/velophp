<?php

namespace App\Domain\SchemaManagement\Steps;

/**
 * Represents a single executable, resumable unit of work in a schema change workflow.
 */
interface SchemaChangeStep
{
    /**
     * Returns the step's canonical name.
     */
    public function getName(): string;

    /**
     * Executes the step.
     */
    public function execute(): void;

    /**
     * Indicates whether this step is idempotent and can be re-run safely if it failed midway.
     */
    public function isIdempotent(): bool;
    
    /**
     * Serializes any necessary state into an array to be saved in DB.
     */
    public function toArray(): array;
}
