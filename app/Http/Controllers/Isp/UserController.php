<?php

namespace App\Http\Controllers\Isp;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Pengguna tenant. User tidak memakai global scope, jadi setiap akses dicek tenant-nya di sini.
 */
class UserController extends Controller
{
    public function index()
    {
        return view('isp.users.index', ['users' => User::forCurrentTenant()->with('roles')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('isp.users.form', ['user' => new User(['is_active' => true]), 'roles' => Role::assignableForTenant()->get(), 'selected' => []]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        [$user, $token] = DB::transaction(function () use ($data) {
            $user = new User(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);
            $user->tenant_id = $this->tenant()->id;
            $user->is_active = true;
            $user->save();
            $user->roles()->sync($data['roles']);
            [, $token] = UserInvitation::issue($user, auth()->id());
            AuditLogger::log('user.created', $user, null, ['email' => $user->email, 'roles' => $data['roles']]);

            return [$user, $token];
        });

        return redirect()->route('app.users.index')->with('success', "Pengguna {$user->name} dibuat.")
            ->with('activation_link', route('activation.show', $token));
    }

    public function edit(User $user)
    {
        $this->ensureSameTenant($user);

        return view('isp.users.form', ['user' => $user, 'roles' => Role::assignableForTenant()->get(), 'selected' => $user->roles->pluck('id')->all()]);
    }

    public function update(Request $request, User $user)
    {
        $this->ensureSameTenant($user);
        $data = $this->validated($request, $user);

        if ($user->id === auth()->id() && (! $request->boolean('is_active') || ! in_array(Role::where('slug', 'isp_admin')->whereNull('tenant_id')->value('id'), $data['roles']))) {
            return back()->with('error', 'Anda tidak dapat menonaktifkan atau mencabut role Admin ISP dari akun sendiri.');
        }

        $before = ['name' => $user->name, 'is_active' => $user->is_active, 'roles' => $user->roles->pluck('slug')->all()];
        $user->fill(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);
        $user->is_active = $request->boolean('is_active');
        $user->save();
        $user->roles()->sync($data['roles']);
        AuditLogger::log('user.updated', $user, $before, ['name' => $user->name, 'is_active' => $user->is_active, 'roles' => Role::whereIn('id', $data['roles'])->pluck('slug')->all()]);

        return redirect()->route('app.users.index')->with('success', 'Pengguna diperbarui.');
    }

    public function activationLink(User $user)
    {
        $this->ensureSameTenant($user);
        [, $token] = UserInvitation::issue($user, auth()->id());
        AuditLogger::log('user.activation_link_issued', $user);

        return back()->with('activation_link', route('activation.show', $token));
    }

    protected function ensureSameTenant(User $user): void
    {
        abort_unless($user->tenant_id === $this->tenant()->id && ! $user->is_super_admin, 404);
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        // Hanya role sistem scope tenant yang boleh diberikan (Admin ISP tidak bisa memberi permission Super Admin).
        $assignable = Role::assignableForTenant()->pluck('id')->all();

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'regex:/^[0-9+\-\s]{8,20}$/'],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['integer', Rule::in($assignable)],
        ]);
    }
}
