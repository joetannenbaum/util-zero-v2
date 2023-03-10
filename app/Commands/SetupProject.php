<?php

namespace App\Console\Commands;

use App\Services\Freshbooks;
use App\Services\Toggl;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class SetupProject extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:project {--freshbooks_client=} {--toggl_client=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a project in all of the appropriate places';

    protected $toggl;

    protected $freshbooks;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Toggl $toggl, Freshbooks $freshbooks)
    {
        parent::__construct();

        $this->toggl = $toggl;
        $this->freshbooks = $freshbooks;
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $fb_client_id = $this->getFreshbooksClientId();
        $toggl_client_id = $this->getTogglClientId();

        $project_params = [
            'client_id' => $fb_client_id,
        ];

        $client_questions = [
            'title' => 'Title',
        ];

        foreach ($client_questions as $key => $label) {
            $project_params[$key] = $this->ask($label);
        }

        $project_params['project_type'] = $this->choice(
            'What type of project is this',
            ['fixed_price', 'hourly_rate']
        );

        if ($project_params['project_type'] === 'fixed_price') {
            $project_params['fixed_price'] = str_replace(
                ',',
                '',
                $this->ask('What is the fixed price of the project')
            );
        }

        if ($project_params['project_type'] === 'hourly_rate') {
            $project_params['rate'] = $this->ask('What is the hourly rate of the project', '150');
        }

        foreach ($project_params as $key => $value) {
            $this->info($label . ': ' . $value);
        }

        if (!$this->confirm('Look good?', true)) {
            return;
        }

        try {
            $fb_project = $this->freshbooks->createProject($project_params);
            $fb_project_id = $fb_project->project->id;
        } catch (ClientException $e) {
            dd($e->getResponse()->getBody()->getContents());
        }

        try {
            $toggl_project = $this->toggl->createProject($toggl_client_id, [
                'name' => $project_params['title'],
            ]);

            $toggl_project_id = $toggl_project['data']['id'];
        } catch (RequestException $e) {
            dd($e->getMessage());
        }

        Http::post('https://hooks.zapier.com/hooks/catch/2949497/oemm3fq/', [
            'json' => [
                'name'          => $project_params['title'],
                'toggl_id'      => $toggl_project_id,
                'freshbooks_id' => $fb_project_id,
            ],
        ]);

        $this->comment('https://my.freshbooks.com/#/project/' . $fb_project_id);
        $this->comment('https://track.toggl.com/projects/927387/list');
    }

    protected function getFreshbooksClientId()
    {
        if ($this->option('freshbooks_client')) {
            return $this->option('freshbooks_client');
        }

        $clients = collect($this->freshbooks->getClients()->response->result->clients);

        $values = $clients->map(function ($client) {
            $label = trim(sprintf(
                '%s - %s %s',
                $client->organization,
                $client->fname,
                $client->lname,
            ), ' -');

            return sprintf('%s [%d]', $label, $client->id);
        })->sort()->toArray();

        $answer = $this->choice('Select a Freshbooks client', $values);

        preg_match('/\[(\d+)\]/', $answer, $matches);

        return (int) $matches[1];
    }

    protected function getTogglClientId()
    {
        if ($this->option('toggl_client')) {
            return $this->option('toggl_client');
        }

        $clients = collect($this->toggl->getClients());

        $values = $clients->map(function ($client) {
            return sprintf('%s [%d]', $client['name'], $client['id']);
        })->sort()->toArray();

        $answer = $this->choice('Select a Toggl client', $values);

        preg_match('/\[(\d+)\]/', $answer, $matches);

        return (int) $matches[1];
    }
}
