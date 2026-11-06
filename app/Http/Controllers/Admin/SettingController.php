<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function edit()
    {
        return view('admin.settings', ['s' => Setting::all_()]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'confidence_threshold' => 'required|integer|between:50,99',
            'countdown_seconds' => 'required|integer|between:5,60',
            'gate_reopen_seconds' => 'required|integer|between:5,600',
        ]);

        $old = Setting::all_();
        foreach ($data as $k => $v) Setting::put($k, $v);
        AuditLog::record('update_settings', 'settings', null, ['old' => $old, 'new' => $data]);

        return back()->with('status', 'Pengaturan disimpan. Perangkat mengambil nilai baru saat memanggil /api/config.');
    }
}
