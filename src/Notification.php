<?php

namespace Owncloud\OcisSdkPhp;

use GuzzleHttp\Exception\GuzzleException;

class Notification
{
    private \GuzzleHttp\Client $guzzle;
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
    private array $messageRichParameters;
    private $serviceUrl;

    public function __construct(
        \GuzzleHttp\Client &$guzzle,
        string $serviceUrl,
        string $id,
        string $app,
        string $user,
        string $datetime,
        string $objectId,
        string $objectType,
        string $subject,
        string $subjectRich,
        string $message,
        string $messageRich,
        array $messageRichParameters
    ) {
        $this->id = $id;
        $this->app = $app;
        $this->user = $user;
        $this->datetime = $datetime;
        $this->objectId = $objectId;
        $this->objectType = $objectType;
        $this->subject = $subject;
        $this->subjectRich = $subjectRich;
        $this->message = $message;
        $this->messageRich = $messageRich;
        $this->messageRichParameters = $messageRichParameters;
        $this->guzzle = $guzzle;
        $this->serviceUrl = $serviceUrl;
    }

    public function setGuzzle(\GuzzleHttp\Client $guzzle)
    {
        $this->guzzle = $guzzle;
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

    public function getMessageRichParameters(): array
    {
        return $this->messageRichParameters;
    }


    /**
     * @throws BadRequestException
     * @throws ForbiddenException
     * @throws NotFoundException
     * @throws UnauthorizedException
     */
    public function delete(): void
    {
        try {
            $this->guzzle->delete(
                $this->serviceUrl . '/ocs/v2.php/apps/notifications/api/v1/notifications/',
                ['body' =>  json_encode(["ids" => [$this->id]])]
            );
        } catch (GuzzleException $e) {
            throw ExceptionHelper::getHttpErrorException($e);
        }
    }
}
