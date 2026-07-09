<?php

namespace Veloquent\Core\Domain\Records\Observers;

use Spatie\Multitenancy\Contracts\IsTenant;
use Veloquent\Core\Domain\Records\Models\Record;
use Veloquent\Core\Domain\Realtime\Events\RealtimeRecordEvent;
use Veloquent\Core\Domain\Records\Services\FileFieldProcessor;
use Veloquent\Core\Domain\Realtime\Contracts\RealtimeDispatcher;
use Veloquent\Core\Domain\Records\Services\RelationIntegrityService;
use Illuminate\Support\Facades\Cache;
use Veloquent\Core\Domain\Auth\Models\AuthToken;
use Veloquent\Core\Domain\Collections\Models\Collection;
use Veloquent\Core\Domain\Collections\Enums\CollectionType;

class RecordObserver
{
    public function __construct(
        protected RealtimeDispatcher $dispatcher,
        protected RelationIntegrityService $integrityService,
        protected FileFieldProcessor $fileProcessor,
    ) {}

    public function creating(Record $record): void
    {
        //
    }

    public function created(Record $record): void
    {
        $this->publishEvent('created', $record);
    }

    public function updating(Record $record): void
    {
        //
    }

    public function updated(Record $record): void
    {
        $this->invalidateAuthTokens($record, revoke: false);
        $this->publishEvent('updated', $record);
    }

    public function deleting(Record $record): void
    {
        $this->integrityService->handleRecordDeletion($record->collection, $record->id);
        $this->fileProcessor->cleanupRecordFiles($record);
        $this->invalidateAuthTokens($record, revoke: true);

        $this->publishEvent('deleted', $record);
    }

    private function invalidateAuthTokens(Record $record, bool $revoke = false): void
    {
        $collection = $record->collection;
        if (! $collection && ($collectionId = $record->getAttribute('collection_id'))) {
            $collection = Collection::findByIdCached($collectionId);
        }

        if ($collection && $collection->type === CollectionType::Auth) {
            $query = AuthToken::query()
                ->forRecord($collection->id, (string) $record->id)
                ->active();

            $hashes = $query->pluck('token_hash')->toArray();

            foreach ($hashes as $hash) {
                Cache::forget("velo:auth:{$hash}");
            }

            if ($revoke) {
                $query->update(['revoked_at' => now()]);
            }
        }
    }

    public function restored(Record $record): void
    {
        //
    }

    public function forceDeleted(Record $record): void
    {
        //
    }

    private function publishEvent(string $event, Record $record): void
    {
        $tenantId = $this->resolveTenantId();
        if ($tenantId === null) {
            return;
        }

        $collectionId = $record->collection?->id ?? $record->getAttribute('collection_id');
        if (! is_string($collectionId) || $collectionId === '') {
            return;
        }

        $realtimeEvent = new RealtimeRecordEvent(
            tenantId: $tenantId,
            collectionId: $collectionId,
            record: $record->toArray(),
            event: $event,
        );

        $this->dispatcher->handle($realtimeEvent);
    }

    private function resolveTenantId(): ?string
    {
        return data_get(app(IsTenant::class)::current(), 'id');
    }
}
