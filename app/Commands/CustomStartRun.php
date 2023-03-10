<?php

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

class CustomStartRun extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'custom-start:run';

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
        $path = $dir . '/joe-custom-start.py';

        if (!$this->createScriptFile($path)) {
            return;
        }

        exec('./joe-custom-start.py');
    }

    protected function createScriptFile($path): bool
    {
        if (file_exists($path)) {
            return true;
        }

        $this->error("Script missing: {$path}");

        if (!$this->confirm('Create script file?', true)) {
            return false;
        }

        file_put_contents(
            $path,
            file_get_contents(base_path('templates/joe-custom-start.py'))
        );

        exec("chmod +x {$path}");

        $this->info("File created: {$path}");

        // Still return false, we just created the file and we don't want to execute it right away
        return false;
    }
}
