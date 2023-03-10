<?php

namespace App\Console\Commands;

use App\Services\Freshbooks;
use App\Services\Toggl;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Console\Command;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class SetupClient extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'setup:client';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a client in all of the appropriate places';

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

        $client_params = [
            'p_country' => 'United States',
        ];

        $client_questions = [
            'fname'        => 'First Name',
            'lname'        => 'Last Name',
            'organization' => 'Company',
            'email'        => 'Email',
            'home_phone'   => 'Phone',
            'p_street'     => 'Address',
            'p_street2'    => 'Address 2',
            'p_city'       => 'City',
            'p_province'   => 'State',
            'p_code'       => 'Zip Code',
        ];

        foreach ($client_questions as $key => $label) {
            $client_params[$key] = trim($this->ask($label));
        }

        if ($client_params['home_phone'] && !strstr($client_params['home_phone'], '(')) {
            $client_params['home_phone'] = sprintf(
                '(%s) %s-%s',
                substr($client_params['home_phone'], 0, 3),
                substr($client_params['home_phone'], 3, 3),
                substr($client_params['home_phone'], 6, 4)
            );
        }

        foreach ($client_questions as $key => $label) {
            $this->info($label . ': ' . $client_params[$key]);
        }

        if (!$this->confirm('Look good?', true)) {
            return;
        }

        try {
            $fb_client = $this->freshbooks->createClient($client_params);
        } catch (ClientException $e) {
            dd($e->getResponse()->getBody()->getContents());
        }

        $fb_client_id = $fb_client->response->result->client->id;

        $client_name = $client_params['organization'] ?: $client_params['fname'] . ' ' . $client_params['lname'];

        try {
            $toggl_client = $this->toggl->createClient([
                'name' => $client_name,
            ]);

            $toggl_client_id = $toggl_client['data']['id'];
        } catch (RequestException $e) {
            dd($e->getMessage());
        }

        // Http::post('https://hooks.zapier.com/hooks/catch/2949497/oemd2b2/', [
        //     'json' => [
        //         'name'          => $client_name,
        //         'toggl_id'      => $toggl_client_id,
        //         'freshbooks_id' => $fb_client_id,
        //     ],
        // ]);

        if ($this->confirm('Create project?', true)) {
            $this->call('setup:project', [
                '--freshbooks_client' => $fb_client_id,
                '--toggl_client' => $toggl_client_id,
            ]);
        }

        $this->comment('https://my.freshbooks.com/#/client/' . $fb_client_id);
        $this->comment('https://track.toggl.com/927387/clients');
    }
}
