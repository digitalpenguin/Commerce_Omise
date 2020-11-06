<?php
namespace DigitalPenguin\Commerce_Omise\API;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class OmiseClient {

    /** @var Client */
    private $client;

    public function __construct(string $secretKey, bool $testMode = true)
    {
        $this->client = new Client([
            'headers' => [
                'Content-Type'  => 'application/json'
            ],
            'base_uri'      =>  'https://api.omise.co/charges',
            'http_errors'   =>  false,
            'auth'          =>  [$secretKey,null], // must be null, not an empty string!
            'livemode'      =>  $testMode
        ]);
    }

    /**
     * Creates an API request and actions it
     * @param string $resource
     * @param array $data
     * @param string $method
     * @return Response
     */
    public function request(string $resource, array $data, string $method = 'POST'): Response
    {
        try {
            $response = $this->client->request($method, $resource, [
                'json' => $data,
            ]);
            return Response::from($response);
        } catch (GuzzleException $e) {
            $errorResponse = new Response(false, 0);
            $errorResponse->addError(get_class($e), $e->getMessage());
            return $errorResponse;
        }
    }
}