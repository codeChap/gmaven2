<?php

namespace codechap\Gmaven2;

class Call
{
	public $key;

	/**
	 * Setup config
	 * 
	 * @param Array Config array
	 */
	public function __construct($config)
	{
		$this->key = $config['key'];
	}

	/**
	 * Get data via Guzzle
	 *
	 * @param String The endpoint we calling against
	 */
	public function get($endPoint)
	{
		// Set and filter post data
		$clientDataArray = [
			'base_uri' => 'https://www.gmaven.com/api/'
		];

		// Setup Guzzle
		$client = new \GuzzleHttp\Client($clientDataArray);
		$response = $client->request('get', $endPoint, [
			'headers' => [
				'gmaven.apiKey' => $this->key,
				'Content-Type'  => 'application/json'
			]
		]);

		// Return response data
		return $this->getResponse($response);
	}

	/**
	 * Post data via Guzzle
	 *
	 * @param String The endpoint we calling against
	 * @param Array Extra data to send to Gmaven
	 */
	public function post($endPoint, $postFields = [])
	{
		// Clean array
		$postFields = array_filter($postFields);

		//print(json_encode($postFields, JSON_PRETTY_PRINT)); die();

		// Set and filter post data
		$clientDataArray = [
			'base_uri' => 'https://www.gmaven.com/api/',
			'json'     => $postFields
		];

		// Setup Guzzle
		$client = new \GuzzleHttp\Client($clientDataArray);
		$response = $client->request('post', $endPoint, [
			'debug'   => false,
			'headers' => [
				'gmaven.apiKey' => $this->key,
				'Content-Type'  => 'application/json'
			]
		]);

		// Return response data
		return $this->getResponse($response);
	}

	/**
	 * Format and return a request
	 *
	 * @param Object Guzzle Response Object
	 */
	private function getResponse($response)
	{
		// Get returned status code of request
		$s = $response->getStatusCode();

		// Normal response
		if($s == 200){

			// Clean content type
			$contentType = strtolower($response->getHeader('Content-Type')[0]);

			// Action by content type
			switch($contentType){

				// Json
				case 'application/json; charset=utf-8' :
				return json_decode($response->getBody()->getContents(), false);

				// Unknown
				default :
				return $response->getBody()->getContents();
			}
		}

		else{

			print \yii\helpers\BaseConsole::renderColoredString("%rCall error!");
		}
	}
}