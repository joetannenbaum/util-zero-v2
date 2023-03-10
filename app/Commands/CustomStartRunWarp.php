<?php

namespace App\Commands;

use Dotenv\Dotenv;
use Illuminate\Support\Collection;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class CustomStartRunWarp extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'custom-start-warp:run';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Run a custom start script for the project';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $dir = exec('pwd');

        $parts = collect(explode('/', $dir));

        $baseDirIndex = $parts->search(fn ($s) => in_array($s, ['projects', 'clients']));

        $dirsForConfigName = $parts->slice($baseDirIndex + 1)->values();

        $configName = Str::slug($dirsForConfigName->join('-'));

        $path = "{$_SERVER['HOME']}/.warp/launch_configurations/{$configName}.yaml";

        if (!$this->createScriptFile($path, $dirsForConfigName)) {
            return;
        }

        exec(base_path('scripts/run-launch-config') . ' ' . $configName);
    }

    protected function createScriptFile($path, Collection $dirsForConfigName): bool
    {
        if (file_exists($path)) {
            return true;
        }

        $this->error("Config missing: {$path}");

        if (!$this->confirm('Create launch configuration file?', true)) {
            return false;
        }

        $dir = exec('pwd');

        $dirs = collect(
            $dirsForConfigName->join('-'),
            $dirsForConfigName->last()
        )->unique()->values();

        $name = $this->choice(
            question: 'Name',
            choices: $dirs->push('Custom')->toArray(),
        );

        if ($name === 'Custom') {
            $name = $this->ask('Name');
        }

        $standard = $this->getStandardCommands();

        $commands = $this->choice(
            question: 'Which commands?',
            choices: $standard->keys()->toArray(),
            multiple: true,
        );

        if (in_array('All', $commands)) {
            $toRun = $standard->filter(fn ($v, $k) => $k !== 'All');
        } else {
            $toRun = $standard->only($commands);
        }

        $longRunning = $toRun->filter(fn ($c) => $c['long_running']);
        $oneOff = $toRun->filter(fn ($c) => !$c['long_running']);

        while ($command = $this->ask('Custom command')) {
            $isLongRunning = $this->confirm('Long running?', false);

            if ($isLongRunning) {
                $title = $this->ask('Title');

                $longRunning->offsetSet($title, [
                    'command' => $command,
                ]);
            } else {
                $oneOff->push([
                    'command' => $command,
                ]);
            }
        }

        $tabs = collect();

        if ($oneOff->count()) {
            $tabs->push([
                'layout' => [
                    'cwd'      => $dir,
                    'commands' => $oneOff->map(fn ($c) => ['exec' =>  $c['command']])->values()->toArray(),
                ]
            ]);
        }

        $longRunning->each(fn ($c, $title) => $tabs->push([
            'title' => $title,
            'layout' => [
                'cwd'      => $dir,
                'commands' => [['exec' => $c['command']]],
            ],
        ]));

        $config = [
            'name' => $name,
            'windows' => [
                [
                    'tabs' => $tabs->toArray(),
                ]
            ]
        ];

        $yaml = Yaml::dump($config, 10);

        file_put_contents($path, $yaml);

        $this->info("File created: {$path}");

        // Still return false, we just created the file and we don't want to execute it right away
        return false;
    }

    protected function getStandardCommands(): Collection
    {
        $dir = exec('pwd');

        if (file_exists($dir . '/metro.config.js')) {
            // React Native project
            return collect([
                'Open in VS Code' => [
                    'command'      => 'dev',
                    'long_running' => false,
                ],
                'Yarn and Pod Install' => [
                    'command'      => 'yarn && npx pod-install',
                    'long_running' => false,
                ],
                'Metro' => [
                    'command'      => "watchman watch-del '{$dir}' && watchman watch-project '{$dir}' && npm run start",
                    'long_running' => true,
                ],
                'API (ngrok)' => [
                    'command'      => 'grok',
                    'long_running' => true,
                ],
                'All'             => [
                    'command'      => 'all',
                    'long_running' => false,
                ],
            ]);
        }

        $appUrl = '';

        if (file_exists($dir . '/.env')) {
            $projectEnv = Dotenv::parse(file_get_contents($dir . '/.env'));
            $appUrl = rtrim($projectEnv['APP_URL'], '/');
        }

        return collect([
            'Open in VS Code' => [
                'command'      => 'dev',
                'long_running' => false,
            ],
            'Vite'            => [
                'command'      => 'yarn dev',
                'long_running' => true,
            ],
            'Cron'            => [
                'command'      => 'php artisan schedule:work',
                'long_running' => true,
            ],
            'Queue'           => [
                'command'      => 'php artisan queue:listen',
                'long_running' => true,
            ],
            'Open Ray'        => [
                'command'      => 'open -a Ray.app',
                'long_running' => false,
            ],
            'Open HELO'       => [
                'command'      => 'open -a HELO.app',
                'long_running' => false,
            ],
            'Stripe Listener' => [
                'command'      => "stripe listen --forward-to {$appUrl}/stripe/webhook",
                'long_running' => true,
            ],
            'All'             => [
                'command'      => 'all',
                'long_running' => false,
            ],
        ]);
    }
}
