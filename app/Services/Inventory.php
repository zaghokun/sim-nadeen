<?php

namespace App\Services;

use App\Enums\StockKind;
use App\Support\BusinessDate;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Inventory
{
    public function lots(StockKind $kind, int $item): Builder
    {
        return DB::table('lot_stok')->where($kind->column(), $item);
    }

    public function receive(
        StockKind $kind, int $item, string $quantity, ?string $value, ?string $date,
        string $condition, int $actor, string $reason, ?int $purchase = null,
    ): int {
        $this->lockActiveItem($kind, $item);
        $this->requireQuantity($kind, $quantity);

        $id = DB::table('lot_stok')->insertGetId([
            $kind->column() => $item, 'pembelian_id' => $purchase, 'tanggal_masuk' => $date,
            'kondisi' => $condition, 'jumlah_awal' => $quantity, 'jumlah_sisa' => $quantity,
            'nilai_awal' => $value, 'nilai_sisa' => $value, 'nilai_disahkan_pada' => $value !== null ? now() : null,
            'created_at' => now(),
        ]);
        $this->movement($id, 'masuk', $quantity, $value, $actor, $reason);

        return $id;
    }

    public function issue(StockKind $kind, int $item, string $quantity, int $actor, string $reason, ?int $line = null, ?string $asOf = null): string
    {
        $this->lockActiveItem($kind, $item);
        $this->requireQuantity($kind, $quantity);

        $lots = $this->lots($kind, $item)->where('kondisi', 'layak')->where('jumlah_sisa', '>', 0)
            ->when($asOf, fn (Builder $q) => $q->where(fn (Builder $dates) => $dates->whereNull('tanggal_masuk')->orWhere('tanggal_masuk', '<=', $asOf)))
            ->whereNotNull('nilai_disahkan_pada')->orderByRaw('tanggal_masuk IS NULL')
            ->orderBy('tanggal_masuk')->orderBy('id')->lockForUpdate()->get();
        $remaining = $quantity;
        $cost = '0.00';

        foreach ($lots as $lot) {
            $complete = bccomp($remaining, $lot->jumlah_sisa, 3) >= 0;
            $taken = $complete ? $lot->jumlah_sisa : $remaining;
            $value = $complete ? $lot->nilai_sisa : bcround(bcdiv(bcmul($lot->nilai_sisa, $taken, 5), $lot->jumlah_sisa, 12), 2);
            $this->updateLot($lot->id, [
                'jumlah_sisa' => bcsub($lot->jumlah_sisa, $taken, 3),
                'nilai_sisa' => bcsub($lot->nilai_sisa, $value, 2),
            ]);
            $this->movement($lot->id, 'keluar', bcsub('0', $taken, 3), bcsub('0', $value, 2), $actor, $reason, $line);
            $cost = bcadd($cost, $value, 2);
            $remaining = bcsub($remaining, $taken, 3);

            if (bccomp($remaining, '0', 3) === 0) {
                return $cost;
            }
        }

        throw ValidationException::withMessages(['jumlah' => 'Stok layak pakai dengan nilai disahkan belum mencukupi.']);
    }

    public function approve(int $id, string $value, int $actor, string $reason): int
    {
        $lot = $this->lockLot($id);
        if ($lot->nilai_disahkan_pada !== null) {
            throw ValidationException::withMessages(['nilai' => 'Nilai lot sudah disahkan.']);
        }

        $this->updateLot($id, ['nilai_awal' => $value, 'nilai_sisa' => $value, 'nilai_disahkan_pada' => now()]);
        $this->movement($id, 'valuasi', '0', $value, $actor, $reason);

        return $id;
    }

    public function condition(int $id, string $condition, int $actor, string $reason): int
    {
        $lot = $this->lockLot($id);
        if ($lot->kondisi !== $condition) {
            $this->updateLot($id, ['kondisi' => $condition]);
            $this->movement($id, 'kondisi', '0', null, $actor, "{$lot->kondisi} → {$condition}. {$reason}");
        }

        return $id;
    }

    public function undoIssue(int $id, int $actor, string $reason): int
    {
        $movement = DB::table('mutasi_stok')->where('id', $id)->lockForUpdate()->firstOrFail();
        $lot = $this->lockLot($movement->lot_id);
        if ($movement->jenis !== 'keluar' || DB::table('mutasi_stok')->where('asal_id', $id)->exists()) {
            throw ValidationException::withMessages(['alasan' => 'Mutasi ini tidak bisa dibalik lagi.']);
        }

        $quantity = bcsub('0', $movement->perubahan_jumlah, 3);
        $value = bcsub('0', $movement->perubahan_nilai, 2);
        $this->updateLot($lot->id, [
            'jumlah_sisa' => bcadd($lot->jumlah_sisa, $quantity, 3),
            'nilai_sisa' => bcadd($lot->nilai_sisa, $value, 2),
        ]);
        $this->movement($lot->id, 'pembalik', $quantity, $value, $actor, $reason, $movement->detail_id, $id);

        return $lot->id;
    }

    public function undoPurchase(int $lotId, int $actor, string $reason): void
    {
        $lot = $this->lockLot($lotId);
        if (bccomp($lot->jumlah_awal, $lot->jumlah_sisa, 3) !== 0 || $lot->kondisi !== 'layak') {
            throw ValidationException::withMessages(['alasan' => 'Pembelian dapat dibalik jika seluruh lot masih utuh dan layak.']);
        }

        $this->updateLot($lotId, ['jumlah_sisa' => '0.000', 'nilai_sisa' => '0.00']);
        $this->movement($lotId, 'retur_beli', bcsub('0', $lot->jumlah_sisa, 3), bcsub('0', $lot->nilai_sisa, 2), $actor, $reason);
    }

    private function lockActiveItem(StockKind $kind, int $item): void
    {
        $record = DB::table($kind->table())->where('id', $item)->lockForUpdate()->firstOrFail();
        if (! $record->aktif) {
            throw ValidationException::withMessages(['item' => 'Item sudah nonaktif.']);
        }
    }

    private function requireQuantity(StockKind $kind, string $quantity): void
    {
        if (bccomp($quantity, '0', 3) <= 0 || ($kind === StockKind::Product && bccomp($quantity, bcadd($quantity, '0', 0), 3) !== 0)) {
            throw ValidationException::withMessages(['jumlah' => 'Jumlah harus positif. Barang jadi hanya menerima hitungan satuan utuh.']);
        }
    }

    private function lockLot(int $id): object
    {
        $lot = DB::table('lot_stok')->where('id', $id)->firstOrFail();
        $kind = $lot->bahan_id ? StockKind::Material : StockKind::Product;
        DB::table($kind->table())->where('id', $lot->{$kind->column()})->lockForUpdate()->firstOrFail();

        return DB::table('lot_stok')->where('id', $id)->lockForUpdate()->firstOrFail();
    }

    private function updateLot(int $id, array $changes): void
    {
        DB::table('lot_stok')->where('id', $id)->update($changes);
    }

    private function movement(int $lot, string $type, string $quantity, ?string $value, int $actor, string $reason, ?int $detail = null, ?int $original = null): void
    {
        DB::table('mutasi_stok')->insert([
            'lot_id' => $lot, 'detail_id' => $detail, 'asal_id' => $original, 'aktor_id' => $actor,
            'jenis' => $type, 'perubahan_jumlah' => $quantity, 'perubahan_nilai' => $value,
            'alasan' => $reason, 'tanggal' => BusinessDate::today(), 'created_at' => now(),
        ]);
    }
}
