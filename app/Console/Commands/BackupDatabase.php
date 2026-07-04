<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Backup diário do banco pra storage/app/backups (retém os últimos 7).
 * O envio pra fora (R2/S3) está CONGELADO; quando liberar, é só anexar o
 * upload ao final deste comando. Em produção (PostgreSQL) usa pg_dump -Fc;
 * em dev (SQLite) copia o arquivo, então o comando roda igual nos dois.
 */
class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--keep=7 : Quantos backups manter}';

    protected $description = 'Gera um backup do banco em storage/app/backups e apaga os mais antigos';

    public function handle(): int
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $driver = DB::connection()->getDriverName();
        $stamp = now()->format('Ymd-His');

        $file = match ($driver) {
            'pgsql' => $this->backupPostgres($dir, $stamp),
            'sqlite' => $this->backupSqlite($dir, $stamp),
            default => null,
        };

        if ($file === null) {
            $this->error("Backup não suportado para o driver '{$driver}'.");

            return self::FAILURE;
        }

        $this->prune($dir, max(1, (int) $this->option('keep')));

        $this->info('Backup gerado: '.$file);

        return self::SUCCESS;
    }

    private function backupPostgres(string $dir, string $stamp): ?string
    {
        $config = config('database.connections.pgsql');
        $file = "{$dir}/degrade-{$stamp}.dump";

        // -Fc: formato custom (comprimido, restaura com pg_restore)
        $process = new Process(
            ['pg_dump', '-Fc', '--no-owner', '-f', $file],
            env: [
                'PGHOST' => $config['host'],
                'PGPORT' => (string) $config['port'],
                'PGDATABASE' => $config['database'],
                'PGUSER' => $config['username'],
                'PGPASSWORD' => $config['password'],
            ],
            timeout: 300,
        );
        $process->run();

        if (! $process->isSuccessful()) {
            $this->error('pg_dump falhou: '.trim($process->getErrorOutput()));

            return null;
        }

        return $file;
    }

    private function backupSqlite(string $dir, string $stamp): ?string
    {
        $source = config('database.connections.sqlite.database');
        if ($source === ':memory:' || ! is_file($source)) {
            $this->error('Banco SQLite não encontrado em disco.');

            return null;
        }

        $file = "{$dir}/degrade-{$stamp}.sqlite";
        copy($source, $file);

        return $file;
    }

    private function prune(string $dir, int $keep): void
    {
        $files = glob($dir.'/degrade-*');
        rsort($files); // nome tem timestamp: ordem desc = mais novo primeiro

        foreach (array_slice($files, $keep) as $old) {
            unlink($old);
        }
    }
}
