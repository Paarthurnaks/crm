<?php

namespace App\Http\Repository;

use Alexusmai\LaravelFileManager\Services\ACLService\ACLRepository;
use Illuminate\Support\Facades\Auth;

class UsersACLRepository implements ACLRepository
{
    /**
     * Get user ID
     *
     * @return mixed
     */
    public function getUserID()
    {
        return Auth::id();
    }

    /**
     * Get ACL rules list for user
     *
     * @return array
     */
    public function getRules(): array
    {
        $userFullName = Auth::user()->name."_".Auth::user()->surname;

        return [
            ['disk_entities' => 'public', 'path' => '/', 'access' => 1],
            ['disk_entities' => 'public', 'path' => '/*', 'access' => 1],
            ['disk_entities' => 'public', 'path' => 'users', 'access' => 1],
            ['disk_entities' => 'public', 'path' => 'users/'.$userFullName, 'access' => 1],
            ['disk_entities' => 'public', 'path' => 'users/'.$userFullName .'/*', 'access' => 2],
        ];
    }
}
