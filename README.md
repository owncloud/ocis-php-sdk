# ocis-sdk-php
This SDK allows you to interact with ocis storage using PHP.

## Acquiring an Access Token
For an easier experience in acquiring an access token, several PHP OIDC client libraries are available. The following code snippet showcases how to retrieve an access token with the `facile-it/php-openid-client` library.

### Install PHP dependendies
You can install the [facile-it/php-openid-client](https://github.com/facile-it/php-openid-client) library using composer:
```
composer require facile-it/php-openid-client
composer require nyholm/psr7
```

### Required PHP Libraries
- php-bcmath
- php-gmp

### Code Snippet to Fetch an Access Token
```php
<?php
	
	require __DIR__ . '/path/to/vendor/autoload.php';
	
	use Facile\OpenIDClient\Client\ClientBuilder;
	use Facile\OpenIDClient\Issuer\IssuerBuilder;
	use Facile\OpenIDClient\Client\Metadata\ClientMetadata;
	use Facile\OpenIDClient\Service\Builder\AuthorizationServiceBuilder;
	use Nyholm\Psr7\ServerRequest;
	
	$issuer = (new IssuerBuilder())
		->build('https://example.ocis.com');
	
	$clientMetadata = ClientMetadata::fromArray([
		'client_id' => 'client_id',
		'client_secret' => 'client_secret',
		'redirect_uris' => [
			'http://url-of-this-file',
		],
	]);
	$client = (new ClientBuilder())
		->setIssuer($issuer)
		->setClientMetadata($clientMetadata)
		->build();
	
	
	$authorizationService = (new AuthorizationServiceBuilder())->build();
	$redirectAuthorizationUri = $authorizationService->getAuthorizationUri(
		$client,
		['scope'=>'openid profile email offline_access']
	);
	
	
	if(!isset($_REQUEST["code"])) {
		header('Location: ' . $redirectAuthorizationUri);
	} else {
		$serverRequest = new ServerRequest('GET', $_SERVER['REQUEST_URI']);
		
		$callbackParams = $authorizationService->getCallbackParams($serverRequest, $client);
		
		$tokenSet = $authorizationService->callback($client, $callbackParams);
		
		// store access and refresh token in database
		$accessToken = $tokenSet->getAccessToken();
		echo 'AccessToken : ' . $accessToken;
		echo '<hr>';
		
		$refreshToken = $tokenSet->getRefreshToken();
		echo 'RefreshToken : ' . $refreshToken;
		echo '<hr>';
		
		// use this code to get new access token when expired
		$tokenSet = $authorizationService->refresh($client, $refreshToken);
		$accessToken = $tokenSet->getAccessToken();
		echo 'NewAccessToken : ' . $accessToken;
	}
```

If you're working in a development environment where you might need to bypass SSL verification (though this is not advised for production environments), here's how:

```php
<?php
	...
	use Facile\OpenIDClient\Issuer\Metadata\Provider\MetadataProviderBuilder;
	use Facile\JoseVerifier\JWK\JwksProviderBuilder;
	use Symfony\Component\HttpClient\Psr18Client;
	use Symfony\Component\HttpClient\CurlHttpClient;
	
	$symHttpClient = new CurlHttpClient([
		'verify_peer' => false,
		'verify_host' => false
	]);
	$httpClient  = new Psr18Client($symHttpClient);
	
	$metadataProviderBuilder = (new MetadataProviderBuilder())
		->setHttpClient($httpClient);
	$jwksProviderBuilder = (new JwksProviderBuilder())
		->setHttpClient($httpClient);
	$issuer = (new IssuerBuilder())
		->setMetadataProviderBuilder($metadataProviderBuilder)
		->setJwksProviderBuilder($jwksProviderBuilder)
		->build('https://example.ocis.com');
	...
	$client = (new ClientBuilder())
		->setHttpClient($httpClient)
		->setIssuer($issuer)
		->setClientMetadata($clientMetadata)
		->build();
	
	$authorizationService = (new AuthorizationServiceBuilder())
		->setHttpClient($httpClient)
		->build();
	...
```

To test, simply open a browser and head to http://url-of-this-file.
