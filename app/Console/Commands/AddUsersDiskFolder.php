<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AddUsersDiskFolder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:add-users-disk_entities-folder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Создать локальные папки пользователей';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $created = 0;

        foreach (User::query()->select(['id', 'name', 'surname'])->lazyById(200) as $user) {
            try {
                $userFullName = $user->name.'_'.$user->surname;
                if (! Storage::disk('local')->makeDirectory('public/users/'.$userFullName)) {
                    throw new RuntimeException('Не удалось создать каталог пользователя');
                }
                $created++;
                if ($created % 200 === 0) {
                    Log::info('User directory command progress', ['processed' => $created]);
                }
            } catch (\Throwable $e) {
                Log::error('User directory command failed', ['user_id' => $user->id, 'exception_class' => get_class($e)]);
                throw $e;
            }
        }

        Log::info('User directory command completed', ['processed' => $created]);

        return self::SUCCESS;
    }
}
