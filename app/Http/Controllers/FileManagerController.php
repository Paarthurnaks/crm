<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class FileManagerController extends Controller
{
    public function index(): View
    {
        return view("filemanager");
    }
}
