<?php

namespace Owncloud\OcisSdkPhp;

class Notification
{
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

    public function __construct(
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
}
