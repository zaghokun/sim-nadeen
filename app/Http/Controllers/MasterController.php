<?php

namespace App\Http\Controllers;

use App\Enums\MasterSection;
use App\Models\CostStandard;
use App\Models\Product;
use App\Support\ListFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MasterController extends Controller
{
    public function index(Request $request, MasterSection $section): View
    {
        Gate::authorize('view-master', $section);

        $model = $section->model();
        $admin = Gate::allows('manage-master');
        $columns = $section->columns($admin);
        $filters = ListFilters::read($request, $admin);
        $query = $model::query();

        if (! $admin) {
            $query->select('id', ...array_keys($columns))->addSelect('aktif')->where('aktif', true);
        } elseif ($filters['status'] !== '') {
            $query->where('aktif', $filters['status'] === 'aktif');
        }

        if ($section === MasterSection::Cost) {
            $query->with('produk:id,kode,nama')->orderByDesc('berlaku_mulai');
        } else {
            $query->orderBy('nama');
        }

        if ($filters['q'] !== '') {
            $term = ListFilters::like($filters['q']);
            $query->where(function (Builder $query) use ($section, $term): void {
                match ($section) {
                    MasterSection::Cost => $query->whereHas('produk', fn (Builder $product) => $product
                        ->whereLike('nama', $term)->orWhereLike('kode', $term)),
                    MasterSection::Product, MasterSection::Category => $query->whereLike('nama', $term)->orWhereLike('kode', $term),
                    MasterSection::Supplier, MasterSection::Customer => $query->whereLike('nama', $term),
                };
            });
        }

        $records = $query->orderBy('id')->paginate(15)->appends(array_filter($filters, fn ($value) => $value !== ''));

        return view('master.index', compact('section', 'records', 'columns', 'filters', 'admin'));
    }

    public function create(MasterSection $section): View
    {
        Gate::authorize('manage-master');

        return $this->form($section);
    }

    public function store(Request $request, MasterSection $section): RedirectResponse
    {
        Gate::authorize('manage-master');

        $model = $section->model();

        return $this->save($request, $section, new $model);
    }

    public function edit(MasterSection $section, int $record): View
    {
        Gate::authorize('manage-master');

        return $this->form($section, $section->model()::query()->findOrFail($record));
    }

    public function update(Request $request, MasterSection $section, int $record): RedirectResponse
    {
        Gate::authorize('manage-master');

        return $this->save($request, $section, $section->model()::query()->findOrFail($record));
    }

    private function form(MasterSection $section, ?Model $record = null): View
    {
        $fields = $section->fields();
        $productRequired = false;

        if ($section === MasterSection::Cost) {
            $products = Product::query()->orderBy('kode')->get(['id', 'kode', 'nama', 'aktif']);
            $fields['produk_id']['options'] = $products->mapWithKeys(fn (Product $product) => [
                $product->id => $product->kode.' · '.$product->nama.($product->aktif ? '' : ' (nonaktif)'),
            ])->all();
            $productRequired = $products->isEmpty();
        }

        if ($section === MasterSection::Category && $record) {
            $fields['kode']['readonly'] = true;
        }

        $values = $record?->getAttributes() ?? ['aktif' => true, 'stok_minimum' => 0];
        if ($record instanceof CostStandard) {
            $values['berlaku_mulai'] = $record->berlaku_mulai->format('Y-m-d');
        }

        return view('master.form', compact('section', 'fields', 'record', 'values', 'productRequired'));
    }

    private function save(Request $request, MasterSection $section, Model $record): RedirectResponse
    {
        $fields = $section->fields();
        $rules = array_map(fn (array $field) => $field['rules'], $fields);
        $duplicateField = 'kode';

        if ($section === MasterSection::Category && $record->exists) {
            unset($rules['kode']);
        }

        if (isset($rules['kode'])) {
            if (is_string($request->input('kode'))) {
                $request->merge(['kode' => Str::upper(trim($request->input('kode')))]);
            }
            $rules['kode'][] = Rule::unique($record::class, 'kode')->ignore($record->getKey());
        }

        if ($section === MasterSection::Cost) {
            $duplicateField = 'berlaku_mulai';
            $productId = filter_var($request->input('produk_id'), FILTER_VALIDATE_INT) ?: 0;
            $rules['berlaku_mulai'] = [
                'bail', ...$rules['berlaku_mulai'],
                Rule::unique(CostStandard::class, 'berlaku_mulai')->where('produk_id', $productId)->ignore($record->getKey()),
            ];
        }

        $messages = [
            'kode.unique' => 'Kode sudah dipakai.',
            'kode.regex' => 'Gunakan kode 1 sampai 40 karakter berupa huruf, angka, tanda hubung, atau garis bawah.',
            'berlaku_mulai.unique' => 'Produk ini sudah memiliki standar HPP pada tanggal tersebut.',
        ];

        $data = $request->validate($rules, $messages, array_map(fn (array $field) => $field['label'], $fields));

        try {
            $record->fill($data)->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([$duplicateField => $messages[$duplicateField.'.unique']]);
        }

        return redirect()->route('master.index', $section->value)
            ->with('status', $section->label().' berhasil disimpan.');
    }
}
