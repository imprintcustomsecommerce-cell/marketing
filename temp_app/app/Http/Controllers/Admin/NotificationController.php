<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\NotificationCenter;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function __invoke(Request $request, NotificationCenter $center): View
    {
        return view('admin.notifications', ['notifications' => $center->for($request->user())]);
    }
}
