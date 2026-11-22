<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AccountInput;
use App\Support\ListFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('manage-accounts');

        $filters = ListFilters::read($request, true);
        $query = User::query()->select('id', 'nama', 'username', 'peran', 'aktif');

        if ($filters['q'] !== '') {
            $term = ListFilters::like($filters['q']);
            $query->where(fn (Builder $query) => $query->whereLike('nama', $term)->orWhereLike('username', $term));
        }

        if ($filters['status'] !== '') {
            $query->where('aktif', $filters['status'] === 'aktif');
        }

        $records = $query->orderBy('nama')->orderBy('id')->paginate(15)->appends(array_filter($filters, fn ($value) => $value !== ''));

        return view('accounts.index', compact('records', 'filters'));
    }

    public function create(): View
    {
        Gate::authorize('manage-accounts');

        return view('accounts.form', ['account' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-accounts');

        $data = $this->validated($request);

        try {
            User::create([
                'nama' => $data['nama'],
                'username' => $data['username'],
                'password_hash' => Hash::make($data['password']),
                'peran' => $data['peran'],
                'aktif' => $data['aktif'],
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['username' => 'Nama pengguna sudah dipakai.']);
        }

        return redirect()->route('accounts.index')->with('status', 'Akun berhasil dibuat.');
    }

    public function edit(User $account): View
    {
        Gate::authorize('manage-accounts');

        return view('accounts.form', compact('account'));
    }

    public function update(Request $request, User $account): RedirectResponse
    {
        Gate::authorize('manage-accounts');

        $data = $this->validated($request, $account);
        $actorId = $request->user()->getKey();

        try {
            DB::transaction(function () use ($actorId, $account, $data): void {
                $accounts = User::query()->whereKey([$actorId, $account->getKey()])
                    ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                abort_unless($accounts[$actorId]->isAdmin(), 403);

                $target = $accounts[$account->getKey()];
                if ($target->getKey() === $actorId && (! $data['aktif'] || $data['peran'] !== UserRole::Admin->value)) {
                    throw ValidationException::withMessages(['peran' => 'Akun admin yang sedang dipakai harus tetap aktif dan berperan admin.']);
                }

                $target->fill([
                    'nama' => $data['nama'],
                    'username' => $data['username'],
                    'peran' => $data['peran'],
                    'aktif' => $data['aktif'],
                ]);

                if (! empty($data['password'])) {
                    $target->password_hash = Hash::make($data['password']);
                }

                if ($target->isDirty(['password_hash', 'peran', 'aktif'])) {
                    $target->versi_sesi++;
                }

                $target->save();
            }, 3);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['username' => 'Nama pengguna sudah dipakai.']);
        }

        return redirect()->route('accounts.index')->with('status', 'Akun berhasil diperbarui.');
    }

    private function validated(Request $request, ?User $account = null): array
    {
        if (is_string($request->input('username'))) {
            $request->merge(['username' => AccountInput::username($request->input('username'))]);
        }

        return $request->validate([
            ...AccountInput::rules($account),
            'peran' => ['required', Rule::enum(UserRole::class)],
            'aktif' => ['required', 'boolean'],
        ], [
            'username.regex' => 'Nama pengguna terdiri dari 3 sampai 40 karakter berupa huruf, angka, titik, tanda hubung, atau garis bawah.',
            'username.unique' => 'Nama pengguna sudah dipakai.',
        ], [
            'nama' => 'nama lengkap', 'username' => 'nama pengguna', 'password' => 'kata sandi', 'peran' => 'peran', 'aktif' => 'status aktif',
        ]);
    }
}
