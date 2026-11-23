<?php

namespace App\Services;

use App\Enums\MasterSection;
use App\Enums\StockKind;
use App\Support\BusinessDate;
use App\Support\Decimal;
use Illuminate\Database\QueryException;
use Illuminate\Database\RecordNotFoundException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class LegacyImport
{
    private const MASTERS = [
        'pelanggan' => MasterSection::Customer, 'supplier' => MasterSection::Supplier,
        'produk' => MasterSection::Product, 'bahan' => MasterSection::Material, 'hpp' => MasterSection::Cost,
    ];

    private const KEY = 'regex:/\A[A-Za-z0-9][A-Za-z0-9._-]{0,99}\z/';

    public function __construct(private ImportWorkbook $workbook, private Inventory $inventory, private Orders $orders, private Finance $finance) {}

    public function run(string $path, string $dataset, int $actor, bool $apply): array
    {
        Validator::make(['dataset' => $dataset], ['dataset' => ['required', 'max:60', self::KEY]])->validate();
        $dataset = strtolower($dataset);
        $input = $this->workbook->read($path);
        $checksum = hash_file('sha256', $path);

        return Cache::lock('impor:'.$dataset, 600)->block(5, fn () => $this->process($input, $dataset, $checksum, $actor, $apply));
    }

    private function process(array $input, string $dataset, string $checksum, int $actor, bool $apply): array
    {
        $counts = array_fill_keys(array_keys(ImportWorkbook::COLUMNS), ['baru' => 0, 'sudah' => 0]);
        $errors = [];
        $batchData = ['dataset' => $dataset, 'sidik_berkas' => $checksum, 'aktor_id' => $actor, 'created_at' => now()];
        DB::beginTransaction();

        try {
            $batch = DB::table('batch_impor')->insertGetId([...$batchData, 'status' => 'diproses']);
            $locate = fn (string $sheet, string $key): int => $this->resolve($dataset, $sheet, $key);
            $store = function (string $sheet, int $number, array $row, callable $write) use (&$errors, &$counts, $batch, $dataset): void {
                try {
                    $created = DB::transaction(fn () => $this->postRow($dataset, $batch, $sheet, $row, $write));
                    $counts[$sheet][$created ? 'baru' : 'sudah']++;
                } catch (ValidationException $e) {
                    $errors[] = $sheet.' baris '.$number.' · '.$e->validator->errors()->first();
                } catch (RecordNotFoundException|QueryException) {
                    $errors[] = $sheet.' baris '.$number.' · Periksa referensi, kode ganda, atau batas data.';
                }
            };

            $seen = [];
            foreach ($input as $sheet => $rows) {
                foreach ($rows as $number => $row) {
                    $key = strtolower($row['kunci']);
                    if ($key === '' || isset($seen[$sheet][$key])) {
                        $errors[] = "{$sheet} baris {$number} · Kunci sumber kosong atau ganda.";
                        unset($input[$sheet][$number]);
                    }
                    $seen[$sheet][$key] = true;
                }
            }

            foreach (self::MASTERS as $sheet => $section) {
                foreach ($input[$sheet] as $number => $row) {
                    $store($sheet, $number, $row, fn () => $this->master($section, $row, $locate));
                }
            }
            foreach ($input['lot'] as $number => $row) {
                $store('lot', $number, $row, fn () => $this->lot($row, $locate, $actor));
            }
            $details = collect($input['rincian'])->groupBy(fn ($row) => strtolower($row['pesanan']), preserveKeys: true);
            foreach ($input['pesanan'] as $number => $row) {
                $lines = $details->pull(strtolower($row['kunci']), collect());
                $store('pesanan', $number, [...$row, 'detail' => $lines->values()->all()], fn () => $this->order($row, $lines->all(), $locate, $actor));
                if ($lines->isNotEmpty()) {
                    foreach ($lines->keys() as $offset => $lineNumber) {
                        $store('rincian', $lineNumber, $lines[$lineNumber], fn () => $this->orders->lines($locate('pesanan', $row['kunci']))
                            ->orderBy('id')->offset($offset)->firstOrFail()->id);
                    }
                }
            }
            foreach ($details->flatten(1) as $line) {
                $errors[] = 'Rincian '.$line['kunci'].' · Sertakan pesanan induk pada berkas yang sama.';
            }
            foreach ($input['pembelian'] as $number => $row) {
                $store('pembelian', $number, $row, fn () => $this->purchase($row, $locate, $actor));
            }
            foreach (collect($input['pembayaran'])->sortBy('tanggal') as $number => $row) {
                $store('pembayaran', $number, $row, fn () => $this->payment($row, $locate, $actor));
            }
            foreach ($input['kas'] as $number => $row) {
                $store('kas', $number, $row, fn () => $this->cash($row, $locate, $actor));
            }

            $result = ['counts' => $counts, 'errors' => $errors, 'saved' => $apply && ! $errors];
            if ($result['saved']) {
                DB::table('batch_impor')->where('id', $batch)->update(['status' => 'selesai', 'ringkasan' => json_encode($result, JSON_THROW_ON_ERROR)]);
                DB::commit();
            } else {
                DB::rollBack();
            }
        } catch (Throwable $error) {
            DB::rollBack();
            throw $error;
        }

        if ($apply && $errors) {
            DB::table('batch_impor')->insert([...$batchData, 'status' => 'ditahan', 'ringkasan' => json_encode($result, JSON_THROW_ON_ERROR)]);
        }

        return $result;
    }

    private function postRow(string $dataset, int $batch, string $sheet, array $row, callable $write): bool
    {
        Validator::make($row, ['kunci' => ['required', self::KEY]])->validate();
        $key = strtolower($row['kunci']);
        $fingerprint = hash('sha256', json_encode($row, JSON_THROW_ON_ERROR));
        $source = DB::table('baris_impor')->where('dataset', $dataset)->where('lembar', $sheet)->where('kunci_sumber', $key)->first();
        if ($source) {
            if ($source->sidik_data !== $fingerprint) {
                throw ValidationException::withMessages(['kunci' => 'Kunci ini sudah diimpor dengan isi berbeda. Koreksi melalui dokumen sumber.']);
            }

            return false;
        }

        DB::table('baris_impor')->insert([
            'batch_id' => $batch, 'dataset' => $dataset, 'lembar' => $sheet, 'kunci_sumber' => $key,
            'sidik_data' => $fingerprint, 'entitas_id' => $write(),
        ]);

        return true;
    }

    private function resolve(string $dataset, string $sheet, string $key): int
    {
        $id = DB::table('baris_impor')->where('dataset', $dataset)->where('lembar', $sheet)
            ->where('kunci_sumber', strtolower($key))->value('entitas_id');
        if (! $id) {
            throw ValidationException::withMessages(['referensi' => "Referensi {$sheet} {$key} belum dipetakan."]);
        }

        return $id;
    }

    private function master(MasterSection $section, array $row, callable $resolve): int
    {
        $model = $section->model();
        if (! empty($row['id_lokal'])) {
            $values = $this->validated($row, ['id_lokal' => ['required', 'integer', 'min:1']]);

            return $model::query()->findOrFail($values['id_lokal'])->id;
        }
        $row['aktif'] = true;
        if (isset($row['kode'])) {
            $row['kode'] = strtoupper($row['kode']);
        }
        if ($section === MasterSection::Cost) {
            $row['produk_id'] = $resolve('produk', $row['produk']);
            $row['berlaku_mulai'] = $row['tanggal'];
        }
        $data = $this->validated($row, array_map(fn (array $field) => $field['rules'], $section->fields()));
        $identity = match ($section) {
            MasterSection::Cost => ['produk_id' => $data['produk_id'], 'berlaku_mulai' => $data['berlaku_mulai']],
            MasterSection::Product, MasterSection::Material => ['kode' => $data['kode']],
            default => [],
        };
        if ($identity && $existing = $model::query()->where($identity)->first()) {
            foreach ($data as $field => $value) {
                if (in_array($field, ['aktif', 'berlaku_mulai'], true)) {
                    continue;
                }
                if ($existing->{$field} != $value) {
                    throw ValidationException::withMessages(['kode' => 'Data master yang ada berbeda. Cocokkan nilai sebelum mengimpor.']);
                }
            }

            return $existing->id;
        }

        return $model::create($data)->id;
    }

    private function lot(array $row, callable $resolve, int $actor): int
    {
        $data = $this->validated($row, [
            'jenis' => ['required', Rule::enum(StockKind::class)], 'item' => ['required', self::KEY],
            'tanggal' => $this->date(false), 'jumlah' => ['required', Decimal::QUANTITY],
            'nilai' => ['nullable', Decimal::MONEY], 'kondisi' => ['required', Rule::in(['layak', 'rusak'])],
            'alasan' => ['required', 'string', 'max:220'],
        ]);
        $kind = StockKind::from($data['jenis']);

        return $this->inventory->receive($kind, $resolve($kind === StockKind::Material ? 'bahan' : 'produk', $data['item']),
            $data['jumlah'], $data['nilai'], $data['tanggal'], $data['kondisi'], $actor, $data['alasan']);
    }

    private function order(array $row, array $lines, callable $resolve, int $actor): int
    {
        $data = $this->validated($row, [
            'pelanggan' => ['required', self::KEY], 'tanggal' => $this->date(),
            'status' => ['required', Rule::in(['draf', 'konfirmasi'])], 'nonrutin' => ['required', 'boolean'],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);
        if (! $lines || count($lines) > 20) {
            throw ValidationException::withMessages(['rincian' => 'Pesanan memerlukan 1 sampai 20 baris rincian.']);
        }
        $detail = [];
        foreach ($lines as $number => $line) {
            try {
                $item = $this->validated($line, [
                    'produk' => ['required', self::KEY], 'jumlah' => ['required', 'integer', 'between:1,1000000'],
                    'sumber' => ['required', Rule::in(['stok', 'khusus'])], 'harga_satuan' => ['nullable', Decimal::MONEY],
                ]);
                $detail[] = [...$item, 'produk_id' => $resolve('produk', $item['produk'])];
            } catch (ValidationException $e) {
                throw ValidationException::withMessages(['rincian' => 'Rincian baris '.$number.' · '.$e->validator->errors()->first()]);
            }
        }
        $id = $this->orders->saveDraft([...$data, 'pelanggan_id' => $resolve('pelanggan', $data['pelanggan']), 'detail' => $detail], $actor);

        return $data['status'] === 'konfirmasi' ? $this->orders->confirm($id, $actor, $data['tanggal']) : $id;
    }

    private function purchase(array $row, callable $resolve, int $actor): int
    {
        $data = $this->validated($row, [
            'supplier' => ['required', self::KEY], 'bahan' => ['required', self::KEY], 'pesanan' => ['nullable', self::KEY],
            'tanggal' => $this->date(), 'jumlah' => ['required', Decimal::QUANTITY], 'nominal' => ['required', Decimal::MONEY],
            'referensi' => ['required', 'string', 'max:100'], 'metode' => ['required', 'string', 'max:40'],
        ]);

        return $this->finance->purchase([
            ...$data, 'supplier_id' => $resolve('supplier', $data['supplier']), 'bahan_id' => $resolve('bahan', $data['bahan']),
            'pesanan_id' => $data['pesanan'] ? $resolve('pesanan', $data['pesanan']) : null,
        ], $actor, $data['tanggal']);
    }

    private function payment(array $row, callable $resolve, int $actor): int
    {
        $data = $this->validated($row, [
            'pesanan' => ['required', self::KEY], 'tanggal' => $this->date(),
            'nominal' => ['required', Decimal::MONEY], 'metode' => ['required', 'string', 'max:40'],
        ]);

        return $this->finance->payment($resolve('pesanan', $data['pesanan']), $data['nominal'], $data['metode'], $actor, $data['tanggal']);
    }

    private function cash(array $row, callable $resolve, int $actor): int
    {
        $data = $this->validated($row, [
            'kategori' => ['required', self::KEY], 'tanggal' => $this->date(),
            'arah' => ['required', Rule::in(['masuk', 'keluar'])], 'nominal' => ['required', Decimal::MONEY],
            'keterangan' => ['required', 'string', 'max:250'], 'masuk_hpp' => ['required', 'boolean'],
            'sumber' => ['nullable', Rule::in(['pembelian', 'pembayaran'])], 'kunci_sumber' => ['required_with:sumber', 'nullable', self::KEY],
        ]);
        $category = DB::table('kategori_kas')->where('kode', $data['kategori'])->firstOrFail();
        if ($data['sumber']) {
            $id = $resolve($data['sumber'], $data['kunci_sumber']);
            $journal = DB::table('transaksi_kas')->where($data['sumber'].'_id', $id)->firstOrFail();
            if ($journal->kategori_id !== $category->id || $journal->tanggal !== $data['tanggal']
                || $journal->arah !== $data['arah'] || bccomp($journal->nominal, $data['nominal'], 2) !== 0) {
                throw ValidationException::withMessages(['sumber' => 'Tanggal, kategori, arah, dan nominal kas harus sama dengan dokumen sumber.']);
            }

            return $journal->id;
        }

        return $this->finance->manual([...$data, 'kategori_id' => $category->id], $actor, $data['tanggal']);
    }

    private function date(bool $required = true): array
    {
        return [$required ? 'required' : 'nullable', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:'.BusinessDate::today()];
    }

    private function validated(array $row, array $rules): array
    {
        return Validator::make(array_map(fn ($value) => $value === '' ? null : $value, $row), $rules)->validate();
    }
}
