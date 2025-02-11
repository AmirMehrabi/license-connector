<?php

namespace LaravelReady\LicenseConnector\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use GuzzleHttp\Client;

use LaravelReady\LicenseConnector\Traits\CacheKeys;
use LaravelReady\LicenseConnector\Exceptions\AuthException;

class ConnectorService
{
    use CacheKeys;

    public $license;

    private $licenseKey;
    private $accessToken;

    public function __construct(string $licenseKey)
    {
        $this->licenseKey = $licenseKey;

        $this->accessToken = $this->getAccessToken($licenseKey);
    }

    /**
     * Check license status
     *
     * @param string $licenseKey
     * @param array $data
     *
     * @return boolean
     */
    public function validateLicense($data = [])
    {

        // if ($this->accessToken) {
            $url = Config::get('license-connector.license_server_url') . '/api/license-server/license';

            if(empty($this->accessToken)){
                $this->accessToken = "test";
            }

            $client = new \GuzzleHttp\Client();

            $response = $client->post( $url, [
                'http_errors' => false,
                'json' => [
                    $data
                ],
                'headers' => [
                    'x-host' => Config::get('app.url'),
                    'x-host-name' => Config::get('app.name'),
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]
            ]);
    
            if ($response->getStatusCode() == 200) {
                
                // logger($response->getBody(). 'is da licence');
                
                $license = json_decode($response->getBody());

                $this->license = $license;

                return $license && $license->status == 'active';
            }
            
        // }

        
        return false;
    }


        /**
     * Check the license 
     *
     * @param string $licenseKey
     * @param array $data
     *
     * @return boolean
     */
    public function checkLicense($data = [])
    {

        if ($this->accessToken) {

            // Cache forget Should be removed after tests
            Cache::forget('current_license');



            $license = Cache::get('current_license');

            if ($license) {
                return $license;
            }

            $url = Config::get('license-connector.license_server_url') . '/api/license-server/license';

            $client = new \GuzzleHttp\Client();

            $response = $client->post( $url, [
                'http_errors' => false,
                'json' => [
                    $data
                ],
                'headers' => [
                    'x-host' => Config::get('app.url'),
                    'x-host-name' => Config::get('app.name'),
                    'Authorization' => "Bearer {$this->accessToken}",
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ]
            ]);

            if ($response->getStatusCode() == 200) {
                
                logger($response->getBody(). 'is da licence');
                
                $license = json_decode($response->getBody());
                Cache::put('current_license', $license, now()->addMinutes(60));
                return $license;


            }
            
        }

        
        return false;
    }

    /**
     * Get access token for the given domain
     *
     * @param string $licenseKey
     *
     * @return string
     */
    private function getAccessToken(string $licenseKey)
    {
        
        $accessTokenCacheKey = $this->getAccessTokenKey($licenseKey);
        // Cache::forget($accessTokenCacheKey);
        $accessToken = Cache::get($accessTokenCacheKey, null);

        if ($accessToken) {
            return $accessToken;
        }

        $url = Config::get('license-connector.license_server_url') . '/api/license-server/auth/login';

        $client = new \GuzzleHttp\Client();

        $response = $client->post( $url, [
            'http_errors' => false,
            'json' => [
                'license_key' => $licenseKey,
                'ls_domain' => Config::get('app.url')
            ],
            'headers' => [
                'x-host' => Config::get('app.url'),
                'x-host-name' => Config::get('app.name'),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ]
        ]);

        $data = json_decode($response->getBody());

        if ($response->getStatusCode() == 200) {

            if ($data->status === true) {
                
                if (!empty($data->access_token)) {
                    $accessToken = $data->access_token;
                    Cache::put($accessTokenCacheKey, $accessToken, now()->addMinutes(60));

                    return $accessToken;
                    
                } else {
                    
                    abort(401, $data->message);
                }
            }
        }

        abort(401, $data->message);
        throw new AuthException($data->message);
    }
}
