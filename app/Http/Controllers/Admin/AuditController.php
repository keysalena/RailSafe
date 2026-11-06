<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;

class AuditController extends Controller
{
    public function index()
    {
        return view('admin.audit', ['logs' => AuditLog::with('user')->orderByDesc('id')->simplePaginate(25)]);
    }
}
