<?php

namespace App\Commands;

use App\Config\ConfigFile;
use Dotenv\Dotenv;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Str;

class NgrokStart extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'ngrok:start {--config-check}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Start ngrok with the specified config';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $configName = 'ngrok-config.yaml';
        $config = ConfigFile::getYaml($configName, [$this, 'createConfig']);
        $path = ConfigFile::path($configName);

        if ($this->option('config-check')) {
            // We're just checking the config, move along.
            return;
        }

        if (!$config) {
            return;
        }

        $this->line("ngrok start app --config {$_SERVER['HOME']}/.ngrok2/ngrok.yml --config {$path}");
    }

    public function createConfig($path)
    {
        $this->error("Config missing: {$path}");

        if (!$this->confirm('Create config file?', true)) {
            return;
        }

        $dir = exec('pwd');

        $appUrl = '';
        $defaultHost = '';

        if (file_exists($dir . '/.env')) {
            $projectEnv = Dotenv::parse(file_get_contents($dir . '/.env'));
            $appUrl = rtrim($projectEnv['APP_URL'], '/');
            $defaultHost = parse_url($appUrl, PHP_URL_HOST);
        }

        $host = $this->ask('Host', $defaultHost);
        $port = $this->ask('Port', Str::contains($appUrl, 'https://') ? 443 : 80);
        $subdomain = $this->ask('Subdomain', 'joecodes');

        if (!Str::contains(file_get_contents('/etc/hosts'), $host)) {
            exec("echo '127.0.0.1 {$host}' | sudo tee -a /etc/hosts");
        }

        return [
            'version' => 2,
            'tunnels' => [
                'app' => [
                    'host_header' => 'rewrite',
                    'addr'        => $host . ':' . $port,
                    'subdomain'   => $subdomain,
                    'proto'       => 'http'
                ],
            ],
        ];
    }
}
