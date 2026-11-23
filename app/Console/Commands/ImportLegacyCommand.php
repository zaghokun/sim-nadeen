<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\LegacyImport;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

class ImportLegacyCommand extends Command
{
    protected $signature = 'nadeen:impor {berkas} {--dataset=} {--admin=} {--apply} {--dry-run}';

    protected $description = 'Periksa atau impor workbook normalisasi SIM Nadeen';

    public function handle(LegacyImport $import): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Pilih satu mode, --dry-run atau --apply.');

            return self::INVALID;
        }
        $dataset = (string) $this->option('dataset');
        $username = strtolower(trim((string) $this->option('admin')));
        $actor = User::query()->where('username', $username)->first();
        if (! $actor?->isAdmin()) {
            $this->error('Isi --admin dengan nama pengguna admin yang aktif.');

            return self::INVALID;
        }
        try {
            $result = $import->run($this->argument('berkas'), $dataset, $actor->id, (bool) $this->option('apply'));
        } catch (ValidationException|InvalidArgumentException|SpreadsheetException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $table = [];
        foreach ($result['counts'] as $sheet => $counts) {
            $table[] = [$sheet, $counts['baru'], $counts['sudah']];
        }
        $this->table(['Lembar', 'Baru', 'Sudah dipetakan'], $table);
        foreach ($result['errors'] as $error) {
            $this->error($error);
        }
        if ($result['errors']) {
            $this->warn('Impor ditahan. Perbaiki lembar dan baris yang disebutkan.');

            return self::FAILURE;
        }

        $this->info($result['saved'] ? 'Impor disimpan.' : 'Dry-run berhasil. Data operasional tidak berubah.');

        return self::SUCCESS;
    }
}
