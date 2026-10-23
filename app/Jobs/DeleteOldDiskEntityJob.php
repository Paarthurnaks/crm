<?php

namespace App\Jobs;

use App\Models\DiskEntity;
use App\Services\FileSystemService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeleteOldDiskEntityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly DiskEntity $diskEntity,
        private readonly FileSystemService $service
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->service->deleteDiskEntity($this->diskEntity);
    }
}
