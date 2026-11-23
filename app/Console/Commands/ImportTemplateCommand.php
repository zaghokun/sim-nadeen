<?php

namespace App\Console\Commands;

use App\Services\ImportWorkbook;
use Illuminate\Console\Command;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;

class ImportTemplateCommand extends Command
{
    protected $signature = 'nadeen:template-impor {berkas}';

    protected $description = 'Buat workbook Excel kosong untuk pemetaan histori';

    public function handle(ImportWorkbook $workbook): int
    {
        try {
            $workbook->template($this->argument('berkas'));
        } catch (InvalidArgumentException|SpreadsheetException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Template impor disimpan.');

        return self::SUCCESS;
    }
}
