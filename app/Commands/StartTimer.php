<?php

namespace App\Commands;

use App\Config\ConfigFile;
use App\Services\Toggl;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Arr;

class StartTimer extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'timer:start';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Start a Toggl timer based on the current branch';

    protected Toggl $toggl;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle(Toggl $toggl)
    {
        $this->toggl = $toggl;

        $config = ConfigFile::get('toggl-config.json', [$this, 'createConfig']);

        if (!$config) {
            // Decided not to create config, abort
            return;
        }

        $timerTitle = null;

        if (Arr::get($config, 'based_on_branch') === false) {
            $timerTitle = $this->ask('What are you timing (leave blank to base on branch)');
        }

        if (!$timerTitle) {
            $timerTitle = Str::of(exec('git rev-parse --abbrev-ref HEAD'))
                ->title()
                ->replace('-', ' ')
                // If the string starts with digits, it's probably an issue number, format it
                ->pipe(fn ($s) => preg_replace('/^(\d+)/', '#$1:', $s))
                ->toString();
        }

        if (Arr::get($config, 'suffix')) {
            $timerTitle = sprintf('%s (%s)', $timerTitle, $config['suffix']);
        }

        $this->toggl->stopCurrentlyRunningTimer();

        $this->toggl->startTimer($timerTitle, $config['project_id']);
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
