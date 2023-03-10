<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class Toggl
{
    protected $client;

    public function __construct()
    {
        $this->client = Http::withBasicAuth(env('TOGGL_API_KEY'), 'api_token')
            ->baseUrl('https://api.track.toggl.com/api/v8/');
    }

    public function stopCurrentlyRunningTimer()
    {
        $currentlyRunning = $this->client->get('time_entries/current')->json();

        if ($currentlyRunning['data'] !== null) {
            $this->client->put(
                sprintf(
                    'time_entries/%d/stop',
                    $currentlyRunning['data']['id']
                )
            );
        }
    }

    public function startTimer($description, $projectId)
    {
        return $this->client->post('time_entries/start', [
            'time_entry' => [
                'description'  => $description,
                'pid'          => $projectId,
                'created_with' => 'Joe Helper',
            ],
        ]);
    }

    public function workspaceProjects($workspaceId): Collection
    {
        return collect($this->client->get(
            sprintf(
                'workspaces/%d/projects',
                $workspaceId
            )
        )->json());
    }

    public function workspaces(): Collection
    {
        return collect($this->client->get('workspaces')->json());
    }

    public function getClientWorkspace()
    {
        return $this->workspaces()->first(function ($workspace) {
            return $workspace['name'] === 'Clients';
        });
    }

    public function getClients()
    {
        return $this->client->get('clients')->json();
    }

    public function createClient($params)
    {
        $workspace = $this->getClientWorkspace();

        return $this->client->post(
            'clients',
            [
                'client' => array_merge(
                    $params,
                    [
                        'wid' => $workspace['id'],
                    ]
                ),
            ],
        )->throw()->json();
    }

    public function createProject($clientId, $params)
    {
        $workspace = $this->getClientWorkspace();

        return $this->client->post('projects', [
            'project' => array_merge(
                $params,
                [
                    'wid' => $workspace['id'],
                    'cid' => $clientId,
                ]
            ),
        ])->json();
    }
}
