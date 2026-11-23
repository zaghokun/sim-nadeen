<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as ExcelReader;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as ExcelWriter;

class ImportWorkbook
{
    public const COLUMNS = [
        'pelanggan' => ['kunci', 'id_lokal', 'nama', 'kontak', 'alamat', 'wilayah'],
        'supplier' => ['kunci', 'id_lokal', 'nama', 'kontak', 'alamat', 'jenis_bahan'],
        'produk' => ['kunci', 'kode', 'nama', 'kategori', 'varian', 'harga_standar', 'stok_minimum'],
        'bahan' => ['kunci', 'kode', 'nama', 'kategori', 'satuan', 'stok_minimum'],
        'hpp' => ['kunci', 'produk', 'tanggal', 'biaya_bahan', 'upah', 'overhead', 'markup'],
        'lot' => ['kunci', 'jenis', 'item', 'tanggal', 'jumlah', 'nilai', 'kondisi', 'alasan'],
        'pesanan' => ['kunci', 'pelanggan', 'tanggal', 'status', 'nonrutin', 'catatan'],
        'rincian' => ['kunci', 'pesanan', 'produk', 'jumlah', 'sumber', 'harga_satuan'],
        'pembelian' => ['kunci', 'supplier', 'bahan', 'pesanan', 'tanggal', 'referensi', 'jumlah', 'nominal', 'metode'],
        'pembayaran' => ['kunci', 'pesanan', 'tanggal', 'nominal', 'metode'],
        'kas' => ['kunci', 'kategori', 'tanggal', 'arah', 'nominal', 'keterangan', 'masuk_hpp', 'sumber', 'kunci_sumber'],
    ];

    public function template(string $path): void
    {
        if (file_exists($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new InvalidArgumentException('Gunakan nama file baru dengan ekstensi .xlsx.');
        }
        $book = new Spreadsheet;
        foreach (self::COLUMNS as $sheet => $columns) {
            $page = $sheet === 'pelanggan' ? $book->getActiveSheet() : $book->createSheet();
            $page->setTitle($sheet)->fromArray([$columns]);
            $page->freezePane('B2');
        }
        $book->setActiveSheetIndex(0);
        (new ExcelWriter($book))->save($path);
        $book->disconnectWorksheets();
    }

    public function read(string $path): array
    {
        if (! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx' || filesize($path) > 10_000_000) {
            throw new InvalidArgumentException('Gunakan berkas .xlsx lokal berukuran maksimal 10 MB.');
        }
        $reader = (new ExcelReader)->setReadDataOnly(true)->setReadEmptyCells(false);
        $rowCount = 0;
        foreach ($reader->listWorksheetInfo($path) as $info) {
            $columns = self::COLUMNS[$info['worksheetName']] ?? [];
            $rowCount += max(0, $info['totalRows'] - 1);
            if (! $columns || $info['totalColumns'] > count($columns)) {
                throw new InvalidArgumentException('Periksa nama dan kolom pada lembar '.$info['worksheetName'].'.');
            }
        }
        if ($rowCount > 5000) {
            throw new InvalidArgumentException('Satu berkas dapat memuat maksimal 5.000 baris.');
        }
        $book = $reader->load($path);
        $output = array_fill_keys(array_keys(self::COLUMNS), []);

        try {
            foreach ($book->getWorksheetIterator() as $page) {
                $name = $page->getTitle();
                $columns = self::COLUMNS[$name];
                foreach ($columns as $index => $column) {
                    if ($page->getCell([$index + 1, 1])->getValue() !== $column) {
                        throw new InvalidArgumentException("Header lembar {$name} harus mengikuti template.");
                    }
                }
                for ($row = 2; $row <= $page->getHighestDataRow(); $row++) {
                    $fields = [];
                    foreach ($columns as $index => $column) {
                        $cell = $page->getCell([$index + 1, $row]);
                        if ($cell->isFormula()) {
                            throw ValidationException::withMessages(['impor' => "Lembar {$name} baris {$row} berisi rumus. Tempel nilainya terlebih dahulu."]);
                        }
                        $value = $cell->getValue();
                        if ($column === 'tanggal' && is_numeric($value) && (float) $value > 0 && (float) $value <= 2958465) {
                            $value = Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
                        }
                        if ($value !== null && ! is_scalar($value)) {
                            throw new InvalidArgumentException("Nilai sel pada {$name} baris {$row} tidak dapat dibaca.");
                        }
                        $fields[$column] = is_bool($value) ? (string) (int) $value : trim((string) $value);
                    }
                    if (array_filter($fields, fn ($value) => $value !== '')) {
                        $output[$name][$row] = $fields;
                    }
                }
            }
        } finally {
            $book->disconnectWorksheets();
        }

        return $output;
    }
}
