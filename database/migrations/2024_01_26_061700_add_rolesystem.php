<?php

use App\Enums\PermissionEntityEnum;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->timestamps();
        });

        Role::factory()->create([
            'title' => 'common',
        ]);

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(Role::class)
                ->constrained()
                ->cascadeOnDelete();
            $table->string('entity');
            $table->string('view');
            $table->string('create');
            $table->string('update');
            $table->string('delete');
            $table->timestamps();
        });

        $enumsArr = PermissionEntityEnum::cases();
        $entities = array_column($enumsArr, 'value');

        foreach ($entities as $entity) {
            Permission::factory()->create([
                'entity' => $entity,
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignIdFor(Role::class)
                ->default(Role::COMMON)
                ->nullable()
                ->constrained()
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignIdFor(Role::class);
        });
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
