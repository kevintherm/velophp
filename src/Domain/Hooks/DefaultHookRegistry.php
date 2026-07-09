<?php

namespace Veloquent\Core\Domain\Hooks;

use Veloquent\Core\Domain\Hooks\Contracts\HookRegistry;

class DefaultHookRegistry implements HookRegistry
{
    private array $pipes = [];

    private array $eventAliases = [
        'record.create' => ['creating' => 'record.creating', 'created' => 'record.created'],
        'record.update' => ['updating' => 'record.updating', 'updated' => 'record.updated'],
        'record.delete' => ['deleting' => 'record.deleting', 'deleted' => 'record.deleted'],
        'auth.login' => ['logging_in' => 'auth.logging_in', 'logged_in' => 'auth.logged_in'],
        'auth.logout' => ['logging_out' => 'auth.logging_out', 'logged_out' => 'auth.logged_out'],
        'auth.password_reset' => ['password_resetting' => 'auth.password_resetting', 'password_reset' => 'auth.password_reset'],
        'auth.email_verify' => ['email_verifying' => 'auth.email_verifying', 'email_verified' => 'auth.email_verified'],
        'auth.email_change' => ['email_changing' => 'auth.email_changing', 'email_changed' => 'auth.email_changed'],
        'ai.generate' => ['generating' => 'ai.generating', 'generated' => 'ai.generated'],
    ];

    /**
     * Register one or more pipes for a specific event.
     *
     * @param string $event The internal event name (e.g. 'record.creating')
     * @param string|array|callable $pipes A single pipe class, an array of pipes, or a closure.
     */
    public function register(string $event, string|array|callable $pipes): void
    {
        if (! isset($this->pipes[$event])) {
            $this->pipes[$event] = [];
        }

        $pipes = is_array($pipes) ? $pipes : [$pipes];

        foreach ($pipes as $pipe) {
            $this->pipes[$event][] = $pipe;
        }
    }

    /**
     * Register one or more "before" pipes using a human-readable alias.
     *
     * @param string $alias The event alias (e.g. 'record.create')
     * @param string|array|callable $pipes A single pipe class, an array of pipes, or a closure.
     */
    public function before(string $alias, string|array|callable $pipes): void
    {
        $event = $this->resolveEvent($alias, 'before');
        $this->register($event, $pipes);
    }

    /**
     * Register one or more "after" pipes using a human-readable alias.
     *
     * @param string $alias The event alias (e.g. 'record.create')
     * @param string|array|callable $pipes A single pipe class, an array of pipes, or a closure.
     */
    public function after(string $alias, string|array|callable $pipes): void
    {
        $event = $this->resolveEvent($alias, 'after');
        $this->register($event, $pipes);
    }

    /**
     * Get all pipes registered for a specific internal event.
     *
     * @param string $event
     * @return array
     */
    public function pipesFor(string $event): array
    {
        return $this->pipes[$event] ?? [];
    }

    private function resolveEvent(string $alias, string $type): string
    {
        if (isset($this->eventAliases[$alias])) {
            $mapping = $this->eventAliases[$alias];
            if ($type === 'before') {
                return reset($mapping);
            }
            return end($mapping);
        }

        return $alias;
    }
    
    public function isAfterEvent(string $event): bool
    {
        foreach ($this->eventAliases as $events) {
            if (end($events) === $event) {
                return true;
            }
        }

        return false;
    }

    /**
     * Unregister pipes for a specific event.
     * If $pipe is null, all pipes for that event are removed.
     */
    public function unregister(string $event, string|callable|null $pipe = null): void
    {
        if ($pipe === null) {
            unset($this->pipes[$event]);
            return;
        }

        if (isset($this->pipes[$event])) {
            $this->pipes[$event] = array_values(array_filter(
                $this->pipes[$event],
                fn ($p) => $p !== $pipe
            ));
        }
    }

    /**
     * Get all currently registered pipes.
     */
    public function all(): array
    {
        return $this->pipes;
    }

    /**
     * Explicitly set/overwrite the pipes for a specific event.
     */
    public function set(string $event, array $pipes): void
    {
        $this->pipes[$event] = $pipes;
    }
}
