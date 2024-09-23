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

## Installation via Composer
Add "owncloud/ocis-php-sdk" to the `require` block in your composer.json and then run composer install.
> [!WARNING]  
> The ocis-php-sdk currently relies on a development version of the "owncloud/libre-graph-api-php" package. To ensure proper dependency resolution, it is necessary to set "minimum-stability": "dev" and "prefer-stable": true in your composer.json file.

```json
{
    "minimum-stability": "dev",
    "prefer-stable": true,
    "require": {
        "owncloud/ocis-php-sdk": "^1.0"
    }
}
```
Alternatively, you can simply run the following from the command line:
```bash
composer config minimum-stability dev
composer config prefer-stable true
composer require owncloud/ocis-php-sdk
```

## Getting started
Ocis has two types of access token, one which is used to interact with drive, group, user, shares etc.(OICD access token) and another which can be used to interact with education endpoints (education access token).

Create an Ocis object using the service Url and an OIDC access token:
```php
$ocis = new Ocis('https://example.ocis.com', $accessToken);
```
Or create an Ocis object to interact with education endpoints using the service Url and an education access token:
```php
$ocis = new Ocis('https://education.ocis.com', null, [], $educationAccessToken);
```

At least one access token should be provided to use the SKD.

Acquiring an OICD access token is out of scope of this SDK, but you can find [examples for that below](#acquiring-an-access-token).

Also refreshing OICD tokens is not part of the SDK, but after you got a new token, you can update the Ocis object:
```php
$ocis->setAccessToken($newAccessToken);
```

Education access token is set when starting the ocis graph server. You can get it using following snippet
```php
$educationAccessToken = getenv("GRAPH_HTTP_API_TOKEN")
```

## Drives (spaces)

Drives can be listed using the `getAllDrives` or the `getMyDrives` method.

The Drive class is responsible for most file/folder related actions, like listing files, creating folders, uploading files, etc.

```php
// get the personal drive of the authorized user
// `getMyDrives` returns all drives that the user is a member of
// but in this example the result is filtered to only return
// the personal drive (parameter 3 = DriveType::PERSONAL)
$drives = $ocis->getMyDrives(
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
$resources = $drives[0]->getResources("/documents");
```

### Drive Permission
Users/Groups can be invited to drives by specifying permissions. The **Drive** class has methods to invite Users/Groups, update permission roles and expiration dates, remove users and groups, etc.

Drive invitations are only possible on **project drives**.

```php
// find all users with a specific surname
$users = $ocis->getUsers("einstein")[0];

// get all drives of type project
$drives = $ocis->getMyDrives(
    DriveOrder::NAME,
    OrderDirection::ASC,
    DriveType::PROJECT
);

// get the drive named 'game'
foreach ($drives as $drive) {
    if ($drive->getName) === 'game' {
        $gameDrive = $drive;
        break;
    }
}

// get all roles that are possible for that drive
$driveRoles = $gameDrive->getRoles();

// get the role that is allowed to view, download, upload, edit, add, delete and manage members
foreach ($driveRoles as $role) {
    if ($role->getDisplayName() === 'Manager') {
        $managerRole = $role;
        break;
    }
}

// invite user einstein on project drive 'game' with manager permission
$gameDrive->invite($users, $managerRole);
```

## Notifications
Notifications can be listed using the `getNotifications` method, which will return an array of `Notification` objects representing all active notifications.

The `Notification` object can retrieve details of the corresponding notification and mark it as read (delete).

## Sharing
Given the correct permissions, an `OcisResource` can be shared with a group or a user. To define the access permissions of the receiver every share has to set `SharingRole`(s).

```php
// get the resources of a subfolder inside a drive
$resources = $drive->getResources("/documents");

// get all roles that are possible for that particular resource
$roles = $resources[0]->getRoles();

// find the role that is allowed to read and write the shared file or folder 
for ($roles as $role) {
    if ($role->getDisplayName() === 'Can edit') {
        $editorRole = $role;
        break;
    }
}

// find all users with a specific surname
$users = $ocis->getUsers("einstein")[0];

// share the resource with the users
$resources[0]->invite($users, $editorRole);
```

## Education User

Education Users can only be created, listed and deleted using education access token. If you want to use other APIs you need to use the OICD access token.

```php
// create education user
$educationUsers = $ocis->createEducationUser()
// list all education user
$educationUsers = $ocis->getEducationUsers()
// list education user by id
$educationUsers = $ocis->getEducationUserById()
// delete education user
$educationUser[0]->delete()
```

## Requirements
- PHP 8.1 or higher
- oCIS 5.0.0 or higher

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
To run the tests locally
1. Install and setup `docker` (min version 24) and `docker compose` (min version 2.21).
2. Ensure that the following php dependencies are installed for executing the integration tests:
   ```
   - php-curl
   - php-dom
   - php-phpdbg
   - php-mbstring
   - php-ast
   ``` 
3. add these lines to your `/etc/hosts` file:
   ```
   127.0.0.1	ocis.owncloud.test
   127.0.0.1	keycloak.owncloud.test
   ```
4. run whole tests 
   ```
   make test-php-integration        // start a full oCIS server with keycloak and other services using docker before running tests
   ```
5. run single test 
   ```
   make test-php-integration testGetResources   // start a full oCIS server with keycloak and other services using docker before running single test
   ```

   If something goes wrong, use `make clean` to clean the created containers and volumes. 

