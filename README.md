[![Build Status](https://drone.owncloud.com/api/badges/owncloud/ocis-php-sdk/status.svg?ref=refs/heads/main)](https://drone.owncloud.com/owncloud/ocis-php-sdk)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=owncloud_ocis-php-sdk&metric=coverage)](https://sonarcloud.io/summary/new_code?id=owncloud_ocis-php-sdk)
[![Code Smells](https://sonarcloud.io/api/project_badges/measure?project=owncloud_ocis-php-sdk&metric=code_smells)](https://sonarcloud.io/summary/new_code?id=owncloud_ocis-php-sdk)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=owncloud_ocis-php-sdk&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=owncloud_ocis-php-sdk)
[![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=owncloud_ocis-php-sdk&metric=security_rating)](https://sonarcloud.io/summary/new_code?id=owncloud_ocis-php-sdk)


# ocis-php-sdk
This SDK allows you to interact with [ownCloud Infinite Scale (oCIS)](https://github.com/owncloud/ocis/) storage using PHP.

## Documentation
You can find a rendered version of the [API documentation](https://owncloud.dev/ocis-php-sdk/) in our dev docs.

To render the documentation locally, use the [phpDocumentor](https://www.phpdoc.org/) to run it in the local repo. E.g.:
```
docker run --rm -v ${PWD}:/data phpdoc/phpdoc:3
```

After that you will find the documentation inside the `docs` folder.

:exclamation: This SDK is still under heavy development and is not yet ready for production use, the API might change!

## Getting started
Create an Ocis object using the service Url and an access token:
```php
$ocis = new Ocis('https://example.ocis.com', $accessToken);
```

Acquiring an access token is out of scope of this SDK, but you can find [examples for that below](#acquiring-an-access-token).

Also refreshing tokens is not part of the SDK, but after you got a new token, you can update the Ocis object:
```php
$ocis->setAccessToken($newAccessToken);
```

## Drives (spaces)

Drives can be listed using the `listAllDrives` or the `listMyDrives` method.

The Drive class is responsible for most file/folder related actions, like listing files, creating folders, uploading files, etc.

```php
// get the personal drive of the authorized user
// `listMyDrives` returns all drives that the user is a member of
// but in this example the result is filtered to only return
// the personal drive (parameter 3 = DriveType::PERSONAL)
$drives = $ocis->listMyDrives(
    DriveOrder::NAME,
    OrderDirection::ASC,
    DriveType::PERSONAL
);

// get the drive id
$id = $drives[0]->getId();

// get the name of the drive
$name = $drives[0]->getName();

// get a link to the drive that can be opened in a browser and will lead the user to the web interface 
$webUrl = $drives[0]->getWebUrl();

// create a folder inside the drive
$drives[0]->createFolder("/documents");

// upload a file to the drive
$drives[0]->uploadFile("/documents/myfile.txt", "Hello World!");

// get an array of all resources of the "/documents" folder inside the drive
$resources = $drives[0]->listResources("/documents");
```

## Notifications
Notifications can be listed using the `listNotifications` method, which will return an array of `Notification` objects representing all active notifications.

The `Notification` object can retrieve details of the corresponding notification and mark it as read (delete).

## Requirements
- PHP 8.1 or higher
- oCIS 4.0.0 or higher

## Acquiring an Access Token
For an easier experience in acquiring an access token, several PHP OIDC client libraries are available. The following code snippet showcases how to retrieve an access token with the `facile-it/php-openid-client` library.

### Install PHP dependencies
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


## Development

### Integration tests
The integration tests start a full oCIS server with keycloak and other services using docker.
To run the tests locally
1. Install and setup `docker` (min version 24) and `docker compose` (min version 2.21).
2. add these lines to your `/etc/hosts` file:
   ```
   127.0.0.1	ocis.owncloud.test
   127.0.0.1	keycloak.owncloud.test
   ```
3. run `make test-php-integration`

If something goes wrong, use `make clean` to clean the created containers and volumes. 

