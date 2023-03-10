<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use League\OAuth2\Client\Grant\RefreshToken;
use League\OAuth2\Client\Token\AccessToken;
use ZEROSPAM\OAuth2\Client\Provider\FreshBooks as FreshbooksProvider;
use Zttp\Zttp;

class Freshbooks
{
    public $provider;

    protected $client;

    protected $account_id;

    protected $business_id;

    public function __construct()
    {
        $this->provider = new FreshbooksProvider([
            'clientId'     => config('services.freshbooks.client_id'),
            'clientSecret' => config('services.freshbooks.client_secret'),
            'redirectUri'  => config('services.freshbooks.redirect_uri'),
        ]);

        $this->client = new Client([
            'base_uri' => 'https://api.freshbooks.com',
        ]);

        // if ($this->getFreshbooksToken()) {
        //     $this->hydrate();
        // }
    }

    protected function getFreshbooksToken()
    {
        if (!Storage::exists('freshbooks-token.json')) {
            return null;
        }

        $token = new AccessToken(json_decode(Storage::get('freshbooks-token.json'), true));

        if (!$token->hasExpired()) {
            return $token;
        }

        $grant = new RefreshToken();
        $token = $this->provider->getAccessToken($grant, ['refresh_token' => $token->getRefreshToken()]);
        $this->saveFreshbooksToken($token);

        return $token;
    }

    protected function hydrate()
    {
        $response = $this->request('GET', 'auth/api/v1/users/me');

        $this->account_id = $response->response->business_memberships[0]->business->account_id;
        $this->business_id = $response->response->business_memberships[0]->business->id;
    }

    public function getClients()
    {
        return $this->request('GET', 'accounting/account/' . $this->account_id . '/users/clients', [
            'query' => [
                'per_page' => 100,
            ],
        ]);
    }

    public function createClient($params)
    {

        return $this->request('POST', 'accounting/account/' . $this->account_id . '/users/clients', [
            'json' => [
                'client' => $params,
            ],
        ]);
    }

    public function createProject($params)
    {
        return $this->request('POST', 'projects/business/' . $this->business_id . '/projects', [
            'json' => [
                'project' => $params,
            ],
        ]);
    }


    public function saveFreshbooksToken(AccessToken $token)
    {
        Storage::put('freshbooks-token.json', json_encode($token->jsonSerialize()));
    }

    protected function request($method, $url, $options = [])
    {
        $request = $this->provider->getAuthenticatedRequest(
            $method,
            $url,
            $this->getFreshbooksToken(),
            $options
        );

        $response = $this->client->send($request, $options);

        return json_decode((string) $response->getBody());
    }
}
