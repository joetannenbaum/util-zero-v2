<?php

namespace App\Commands;

use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use LaravelZero\Framework\Commands\Command;

class BackupForge extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'backup:forge';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Command description';

    protected string $dropBoxBaseDir;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // https://developer.1password.com/docs/cli/config-directories
        // https://developer.1password.com/docs/cli/sign-in-manually

        $this->dropBoxBaseDir = $_SERVER['HOME'] . '/Dropbox/Dev/backups/laravel-forge';

        Http::macro(
            'forge',
            fn () => Http::baseUrl('https://forge.laravel.com/api/v1')
                ->withToken(env('FORGE_TOKEN'))
                ->acceptJson()
                ->retry(3, 1000, function (Exception $exception, PendingRequest $request) {
                    if ($exception instanceof RequestException && $exception->response->status() === 429) {
                        sleep($exception->response->header('retry-after') + 1);

                        return true;
                    }

                    return false;
                })
                ->asJson()
        );

        exec('op vaults list --format json', $vaults);

        $privateVault = collect(json_decode(implode('', $vaults), true))->first(fn ($vault) => $vault['name'] === 'Private');

        $servers = collect(Http::forge()->get('servers')->json()['servers'])->filter(fn ($server) => !$server['revoked'])->values();

        $servers->each(function ($server) use ($privateVault) {
            $this->syncServerInfo($server);

            collect(Http::forge()->get("servers/{$server['id']}/sites")->json()['sites'])->each(function ($site) use ($privateVault, $server) {
                $this->syncSiteInfo($server, $site);
                $this->syncEnv($server, $site,  $privateVault);
            });
        });
    }

    protected function syncServerInfo($server)
    {
        $this->info('Syncing ' . $server['name'] . '...');

        $daemons = Http::forge()->get("servers/{$server['id']}/daemons")->json()['daemons'];
        $jobs = Http::forge()->get("servers/{$server['id']}/jobs")->json()['jobs'];

        $dir = $this->dropBoxBaseDir . '/' . $server['name'];

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($dir . '/server.json', json_encode($server, JSON_PRETTY_PRINT));
        file_put_contents($dir . '/daemons.json', json_encode($daemons, JSON_PRETTY_PRINT));
        file_put_contents($dir . '/jobs.json', json_encode($jobs, JSON_PRETTY_PRINT));
    }

    protected function syncSiteInfo($server, $site)
    {
        $this->info('Syncing ' . $server['name'] . ' - ' . $site['name'] . '...');

        $script = Http::forge()->get("servers/{$server['id']}/sites/{$site['id']}/deployment/script")->body();
        $nginx = Http::forge()->get("servers/{$server['id']}/sites/{$site['id']}/nginx")->body();
        $workers = Http::forge()->get("servers/{$server['id']}/sites/{$site['id']}/workers")->json()['workers'];

        $dir = $this->dropBoxBaseDir . '/' . $server['name'] . '/' . str_replace('.', '-', $site['name']);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($dir . '/site.json', json_encode($site, JSON_PRETTY_PRINT));
        file_put_contents($dir . '/deploy-script.sh', $script);
        file_put_contents($dir . '/nginx.conf', $nginx);
        file_put_contents($dir . '/workers.json', json_encode($workers, JSON_PRETTY_PRINT));
    }

    protected function syncEnv($server, $site, $privateVault)
    {
        $env = Http::forge()->get("servers/{$server['id']}/sites/{$site['id']}/env")->body();

        $documentName = $server['name'] . ' - ' . $site['name'] . ' .env';

        exec(
            'op document get "' . $documentName . '" --vault "' . $privateVault['id'] . '" --format json',
            $existingItem,
            $existingItemResult
        );

        if ($existingItemResult === 1) {
            $this->info('Adding ' . $documentName . '...');

            return $this->withEnvFile(
                $documentName,
                $env,
                fn ($path) => exec(
                    sprintf(
                        'op document create "%s" --vault %s --title "%s" --tags "Site Backup,Env Backup" --file-name env.ini --format json',
                        $path,
                        $privateVault['id'],
                        $documentName,
                    ),
                    $newItem,
                    $newItemResult
                )
            );
        }

        if (trim(collect($existingItem)->implode(PHP_EOL)) !== trim($env)) {
            $this->info('Updating ' . $documentName . '...');

            return $this->withEnvFile(
                $documentName,
                $env,
                fn ($path) => exec(
                    sprintf(
                        'op document edit "%s" "%s" --file-name env.ini --format json',
                        $documentName,
                        $path,
                    ),
                    $updatedItem,
                    $updatedItemResult
                )
            );
        }

        $this->info('Skipping ' . $documentName . ', good go.');
    }

    protected function withEnvFile($documentName, $env, $callback)
    {
        $path = storage_path('app/' . $documentName . '.env');
        file_put_contents($path, $env);

        $callback($path);

        unlink($path);
    }
}
