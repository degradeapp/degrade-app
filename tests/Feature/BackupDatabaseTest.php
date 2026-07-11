<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    private string $storage;

    private string $source;

    private string $originalDefault;

    protected function setUp(): void
    {
        parent::setUp();

        // storage descartável: o prune do comando apaga backups antigos e um
        // teste não pode encostar nos backups reais de storage/app/backups
        $this->storage = sys_get_temp_dir().'/degrade-backup-test-'.uniqid();
        File::makeDirectory($this->storage.'/app', 0755, true);
        $this->app->useStoragePath($this->storage);

        // o comando decide o formato pelo driver da conexão default; forçamos
        // o caminho sqlite (cópia de arquivo) pra rodar igual nas duas engines
        // da suíte. O caminho pg_dump depende do binário e é validado no VPS.
        $this->source = $this->storage.'/fake-database.sqlite';
        file_put_contents($this->source, 'conteudo-do-banco');
        $this->originalDefault = config('database.default');
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', $this->source);
    }

    protected function tearDown(): void
    {
        // devolve a conexão default ANTES de apagar o diretório: o rollback do
        // RefreshDatabase conecta na default e o arquivo sqlite fake já era
        config()->set('database.default', $this->originalDefault);
        if ($this->originalDefault !== 'sqlite') {
            DB::purge('sqlite');
        }
        File::deleteDirectory($this->storage);

        parent::tearDown();
    }

    public function test_backup_local_sem_disk_remoto_configurado(): void
    {
        config()->set('backup.remote_disk', null);

        $this->artisan('db:backup')->assertExitCode(0);

        $files = glob($this->storage.'/app/backups/degrade-*.sqlite');
        $this->assertCount(1, $files);
        $this->assertSame('conteudo-do-banco', file_get_contents($files[0]));
    }

    public function test_backup_envia_copia_externa_quando_remote_disk_setado(): void
    {
        Storage::fake('r2');
        config()->set('backup.remote_disk', 'r2');

        $this->artisan('db:backup')->assertExitCode(0);

        $files = glob($this->storage.'/app/backups/degrade-*.sqlite');
        $this->assertCount(1, $files);
        Storage::disk('r2')->assertExists('db/'.basename($files[0]));
    }

    public function test_prune_mantem_apenas_os_mais_novos(): void
    {
        config()->set('backup.remote_disk', null);

        $dir = $this->storage.'/app/backups';
        File::makeDirectory($dir, 0755, true);
        file_put_contents($dir.'/degrade-20200101-000000.sqlite', 'velho');
        file_put_contents($dir.'/degrade-20200102-000000.sqlite', 'menos velho');

        $this->artisan('db:backup', ['--keep' => 2])->assertExitCode(0);

        $files = glob($dir.'/degrade-*');
        $this->assertCount(2, $files);
        $this->assertFileDoesNotExist($dir.'/degrade-20200101-000000.sqlite');
    }
}
