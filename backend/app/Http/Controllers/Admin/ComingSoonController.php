<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Placeholder for SRS modules that exist in the navigation but are not built yet.
 */
class ComingSoonController extends Controller
{
    public function __invoke(string $module): View
    {
        return view('admin.coming-soon', ['module' => Str::headline($module)]);
    }
}
