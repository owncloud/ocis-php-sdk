<?php

namespace Owncloud\OcisPhpSdk;

use GuzzleHttp\Exception\GuzzleException;
use Owncloud\OcisPhpSdk\Exception\BadRequestException;
use Owncloud\OcisPhpSdk\Exception\ExceptionHelper;
use Owncloud\OcisPhpSdk\Exception\ForbiddenException;
use Owncloud\OcisPhpSdk\Exception\HttpException;
use Owncloud\OcisPhpSdk\Exception\InternalServerErrorException;
use Owncloud\OcisPhpSdk\Exception\NotFoundException;
use Owncloud\OcisPhpSdk\Exception\UnauthorizedException;

/**
 * Class representing a single notification emitted in ownCloud Infinite Scale
 */
class Notification
{
    private string $accessToken;
    private string $id;
    private string $app;
    private string $user;
    private string $datetime;
    private string $objectId;
    private string $objectType;
    private string $subject;
    private string $subjectRich;
    private string $message;
    private string $messageRich;

    /**
     * @var array<mixed>
     */
    private array $messageRichParameters;
    private string $serviceUrl;

    /**
     * @phpstan-var array{'headers'?:array<string, mixed>, 'verify'?:bool}
     */
    private array $connectionConfig;

    /**
     * @phpstan-param array{
     *                        'headers'?:array<string, mixed>,
     *                        'verify'?:bool,
     *                        'webfinger'?:bool,
     *                        'guzzle'?:\GuzzleHttp\Client
     *                        } $connectionConfig
     * @phpstan-param object{
     *    app: string,
     *    user: string,
     *    datetime: string,
     *    object_id: string,
     *    object_type: string,
     *    subject: string,
     *    subjectRich: string,
     *    message: string,
     *    messageRich: string,
     *    messageRichParameters:array{int, mixed}
     * } $notificationContent
     * @throws \InvalidArgumentException
     * @ignore The developer using the SDK does not need to create notifications manually, but should use the Ocis class
     *         to retrieve them, so this constructor should not be listed in the documentation.
     */
    public function __construct(
        string &$accessToken,
        array $connectionConfig,
        string $serviceUrl,
        string $id,
        $notificationContent
    ) {
        $this->id = $id;
        $this->app = $notificationContent->app;
        $this->user = $notificationContent->user;
        $this->datetime = $notificationContent->datetime;
        $this->objectId = $notificationContent->object_id;
        $this->objectType = $notificationContent->object_type;
        $this->subject = $notificationContent->subject;
        $this->subjectRich = $notificationContent->subjectRich;
        $this->message = $notificationContent->message;
        $this->messageRich = $notificationContent->messageRich;
        $this->messageRichParameters = $notificationContent->messageRichParameters;
        $this->accessToken = &$accessToken;
        $this->serviceUrl = $serviceUrl;
        if (!Ocis::isConnectionConfigValid($connectionConfig)) {
            throw new \InvalidArgumentException('connection configuration not valid');
        }
        $this->connectionConfig = $connectionConfig;
    }

    /**
     * @ignore This function is mainly for unit tests and should not be shown in the documentation
     */
    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getApp(): string
    {
        return $this->app;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function getDatetime(): string
    {
        return $this->datetime;
    }

    public function getObjectId(): string
    {
        return $this->objectId;
    }

    public function getObjectType(): string
    {
        return $this->objectType;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getSubjectRich(): string
    {
        return $this->subjectRich;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getMessageRich(): string
    {
        return $this->messageRich;
    }

    /**
     * @return array<mixed>
     */
    public function getMessageRichParameters(): array
    {
        return $this->messageRichParameters;
    }

    /**
     * Delete (mark as read) the notification
     *
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     * @throws HttpException
     * @throws \InvalidArgumentException
     * @throws InternalServerErrorException
     */
    public function delete(): void
    {
        $guzzle = new \GuzzleHttp\Client(
            Ocis::createGuzzleConfig(
                $this->connectionConfig,
                $this->accessToken
            )
        );
        try {
            $guzzle->delete(
                $this->serviceUrl . '/ocs/v2.php/apps/notifications/api/v1/notifications/',
                ['body' => json_encode(["ids" => [$this->id]])]
            );
        } catch (GuzzleException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
    }
}
