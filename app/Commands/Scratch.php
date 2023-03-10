<?php

namespace App\Commands;

use App\Config\ConfigFile;
use App\Services\Toggl;
use LaravelZero\Framework\Commands\Command;

class Scratch extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'tiasdfsadfsdmer:start';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Start a Toggl timer';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(Toggl $toggl)
    {
        $this->toggl = $toggl;

        $dir = exec('pwd');
        $configPath = $dir . '/toggl-config.json';

        $config = ConfigFile::get($configPath, [$this, 'createConfig']);

        if (!$config) {
            $this->error("Config missing: {$configPath}");
            return;
        }
    }

    public function createConfig($path)
    {
        $this->error('Config missing: ' . $path);

        if (!$this->confirm('Create config file?', true)) {
            return;
        }

        $workspaces = $this->toggl->workspaces();

        $workspaceName = $this->choice('Workspace', $workspaces->pluck('name')->toArray());
        $workspace = $workspaces->first(fn ($item) => $item['name'] === $workspaceName);

        $projects = $this->toggl->workspaceProjects($workspace['id']);

        $projectName = $this->choice('Project', $projects->pluck('name')->toArray());
        $project = $projects->first(fn ($item) => $item['name'] === $projectName);

        $suffix = $this->ask('Is this a client with multiple projects? If so, what is this project? (Add a suffix?)');

        $basedOnBranch = $this->confirm('Base on Branch', true);

        return [
            'project_id'      => $project['id'],
            'suffix'          => $suffix ?: null,
            'based_on_branch' => $basedOnBranch,
        ];
    }
}
