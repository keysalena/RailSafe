<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Crossing;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return view('admin.users', ['users' => User::with('crossings')->orderBy('role')->orderBy('name')->get(), 'crossings' => Crossing::orderBy('code')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => 'required|in:superadmin,operator',
            'crossings' => 'array', 'crossings.*' => 'exists:crossings,id',
        ]);

        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password'], 'role' => $data['role']]);
        $user->crossings()->sync($data['crossings'] ?? []);
        AuditLog::record('create_user', 'user', $user->id, ['role' => $user->role]);

        return back()->with('status', 'Akun berhasil dibuat.');
    }

    public function edit(User $user)
    {
        return view('admin.user-edit', ['u' => $user->load('crossings'), 'crossings' => Crossing::orderBy('code')->get()]);
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => 'nullable|string|min:8',
            'role' => 'required|in:superadmin,operator',
            'crossings' => 'array', 'crossings.*' => 'exists:crossings,id',
        ]);

        if ($user->id === $request->user()->id && $data['role'] !== 'superadmin') {
            return back()->withErrors(['role' => 'Anda tidak dapat menurunkan role akun sendiri.']);
        }

        $user->fill(collect($data)->only(['name', 'email', 'role'])->all());
        $reset = ! empty($data['password']);
        if ($reset) $user->password = $data['password'];
        $user->save();
        $user->crossings()->sync($data['crossings'] ?? []);

        AuditLog::record($reset ? 'reset_password' : 'update_user', 'user', $user->id);

        return redirect()->route('admin.users.index')->with('status', 'Akun diperbarui.');
    }

    public function toggle(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'Anda tidak dapat menonaktifkan akun sendiri.']);
        }
        $user->update(['is_active' => ! $user->is_active]);
        AuditLog::record($user->is_active ? 'activate_user' : 'deactivate_user', 'user', $user->id);

        return back()->with('status', $user->is_active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.');
    }
}
