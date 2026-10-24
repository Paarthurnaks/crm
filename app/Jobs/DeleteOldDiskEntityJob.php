<?php

namespace App\Jobs;

use App\Models\DiskEntity;
use App\Services\FileSystemService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteOldDiskEntityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly int $diskEntityId) {}

    /**
     * Execute the job.
     */
    public function handle(FileSystemService $service): void
    {
        Log::info('Starting expired disk entity deletion', ['disk_entity_id' => $this->diskEntityId]);

        $diskEntity = DiskEntity::find($this->diskEntityId);
        if ($diskEntity === null || ! $diskEntity->deleted) {
            Log::warning('Expired disk entity deletion skipped', [
                'disk_entity_id' => $this->diskEntityId,
                'reason' => $diskEntity === null ? 'missing' : 'restored',
            ]);

            return;
        }

        try {
            $service->deleteDiskEntity($diskEntity);
            Log::info('Expired disk entity deletion completed', ['disk_entity_id' => $this->diskEntityId]);
        } catch (Throwable $exception) {
            Log::error('Expired disk entity deletion failed', [
                'disk_entity_id' => $this->diskEntityId,
                'exception' => $exception::class,
            ]);

            throw $exception;
        }
    }
}
