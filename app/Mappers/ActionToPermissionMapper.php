<?php

namespace App\Mappers;

class ActionToPermissionMapper
{
    public function map(string $actionName)
    {
        return $this->{$actionName}();
    }

    private function index()
    {
        return 'view';
    }

    private function show()
    {
        return 'view';
    }

    private function store()
    {
        return 'create';
    }

    private function update()
    {
        return 'update';
    }

    private function destroy()
    {
        return 'delete';
    }
}
