<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cópia externa do backup
    |--------------------------------------------------------------------------
    |
    | Nome do disk (ex.: 'r2') pra onde o db:backup manda uma cópia de cada
    | backup gerado. Vazio = backup fica só no disco local. O destino real
    | (conta R2/S3) segue congelado até o dono criar a conta.
    |
    */

    'remote_disk' => env('BACKUP_REMOTE_DISK'),

];
