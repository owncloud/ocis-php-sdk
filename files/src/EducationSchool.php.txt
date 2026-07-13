<?php

namespace Owncloud\OcisPhpSdk;

use DateTime;
use GuzzleHttp\Client;
use OpenAPI\Client\Api\EducationSchoolApi;
use OpenAPI\Client\Configuration;
use OpenAPI\Client\ApiException;
use OpenAPI\Client\Model\EducationUserReference;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use OpenAPI\Client\Model\EducationSchool as OpenApiEducationSchool;
use Owncloud\OcisPhpSdk\Exception\InvalidResponseException;

/**
 * Class representing an Education School in ownCloud Infinite Scale
 *
 * @phpstan-import-type ConnectionConfig from Ocis
 */
class EducationSchool
{
    private ?string $id;
    private ?string $displayName;
    private ?string $number;
    private ?DateTime $terminationDate;
    private string $serviceUrl;
    private string $accessToken;
    private Client $guzzle;
    private Configuration $graphApiConfig;

    /**
     * @phpstan-var ConnectionConfig
     */
    private array $connectionConfig;

    /**
     * @param OpenApiEducationSchool $school
     * @param string $serviceUrl
     * @phpstan-param ConnectionConfig $connectionConfig
     * @param string $accessToken
     */
    public function __construct(
        OpenApiEducationSchool $school,
        string $serviceUrl,
        array $connectionConfig,
        string &$accessToken,
    ) {
        $this->id = $school->getId();
        $this->displayName = $school->getDisplayName();
        $this->number = $school->getSchoolNumber();
        $this->terminationDate = $school->getTerminationDate();
        $this->serviceUrl = $serviceUrl;
        $this->connectionConfig = $connectionConfig;
        $this->accessToken = $accessToken;
        $this->graphApiConfig = Configuration::getDefaultConfiguration()
            ->setHost($this->serviceUrl . '/graph');
        $this->guzzle = new Client(
            Ocis::createGuzzleConfig($this->connectionConfig, $this->accessToken),
        );
    }

    /**
     * Get the value of id
     */
    public function getId(): string
    {
        return (($this->id === null) || ($this->id === '')) ?
            throw new InvalidResponseException(
                "Invalid id returned for school '" . print_r($this->id, true) . "'",
            ) : $this->id;
    }

    /**
     * Get the value of displayName
     */
    public function getDisplayName(): string
    {
        return (($this->displayName === null) || ($this->displayName === '')) ?
            throw new InvalidResponseException(
                "Invalid displayName returned for school '" . print_r($this->displayName, true) . "'",
            ) : (string)$this->displayName;
    }

    /**
     * Get the value of number
     */
    public function getNumber(): string
    {
        return (($this->number === null) || ($this->number === '')) ?
            throw new InvalidResponseException(
                "Invalid number returned for school '" . print_r($this->number, true) . "'",
            ) : (string)$this->number;
    }

    /**
     * Get the value of terminationDate
     */
    public function getTerminationDate(): ?DateTime
    {
        return $this->terminationDate;
    }

    /**
     * Add education user to a school
     *
     * @param EducationUser $user
     * @return void
     */
    public function addUser(EducationUser $user): void
    {
        $apiInstance = new EducationSchoolApi(
            $this->guzzle,
            $this->graphApiConfig,
        );
        $educationUserReference = new EducationUserReference(
            ['at_odata_id' => trim($this->serviceUrl, '/') . "/graph/v1.0/education/users/" . $user->getId()],
        );
        try {
            $apiInstance->addUserToSchool($this->getId(), $educationUserReference);
        } catch (ApiException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
    }
}
