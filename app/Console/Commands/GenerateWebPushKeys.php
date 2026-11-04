<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Minishlink\WebPush\VAPID;

#[Signature('webpush:keys {--write : Guarda las claves en el archivo .env}')]
#[Description('Genera un par de claves VAPID para las notificaciones Web Push')]
class GenerateWebPushKeys extends Command
{
    public function handle(): int
    {
        if (config('safety.webpush.public_key') && ! $this->option('write')) {
            $this->warn('Ya hay claves VAPID configuradas. Cambiarlas invalida todas las suscripciones existentes.');
        }

        $keys = VAPID::createVapidKeys();

        if (! $this->option('write')) {
            $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
            $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);

            return self::SUCCESS;
        }

        $path = base_path('.env');
        $contents = File::exists($path) ? File::get($path) : '';

        if (str_contains($contents, 'VAPID_PRIVATE_KEY=') && ! $this->confirm('Ya existen claves en .env. ¿Reemplazarlas e invalidar las suscripciones actuales?')) {
            return self::FAILURE;
        }

        foreach (['VAPID_PUBLIC_KEY' => $keys['publicKey'], 'VAPID_PRIVATE_KEY' => $keys['privateKey']] as $name => $value) {
            $contents = preg_match("/^{$name}=.*$/m", $contents)
                ? preg_replace("/^{$name}=.*$/m", "{$name}={$value}", $contents)
                : rtrim($contents)."\n{$name}={$value}\n";
        }

        File::put($path, $contents);
        $this->info('Claves VAPID guardadas en .env. Ejecuta `php artisan config:clear` si usas caché de configuración.');

        return self::SUCCESS;
    }
}
