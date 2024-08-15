<?php

namespace integration\Owncloud\OcisPhpSdk;

use Owncloud\OcisPhpSdk\Notification;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource;
use Owncloud\OcisPhpSdk\SharingRole;
use Owncloud\OcisPhpSdk\User;

require_once __DIR__ . '/OcisPhpSdkTestCase.php';


class NotificationTest extends OcisPhpSdkTestCase
{
    private User $einstein;
    private SharingRole $viewerRole;
    private OcisResource $fileToShare;
    private Ocis $ocis;
    private Ocis $einsteinOcis;

    public function setUp(): void
    {
        parent::setUp();
        $this->einsteinOcis = $this->initUser('einstein', 'relativity');
        $this->ocis = $this->getOcis('admin', 'admin');
        $personalDrive = $this->getPersonalDrive($this->ocis);

        $personalDrive->uploadFile('to-share-test.txt', 'some content');
        $this->createdResources[$personalDrive->getId()][] = 'to-share-test.txt';
        $this->fileToShare = $personalDrive->getResources()[0];

        $this->einstein = $this->ocis->getUsers('einstein')[0];

        $viewerRoleId = self::getPermissionsRoleIdByName('Viewer');
        foreach ($this->fileToShare->getRoles() as $role) {
            if ($role->getId() === $viewerRoleId) {
                $this->viewerRole = $role;
                break;
            }
        }

        $this->fileToShare->invite($this->einstein, $this->viewerRole);
    }

    public function testGetNotifications(): void
    {
        $sharerUser = $this->ocis->getUsers('Admin');

        $notifications = $this->einsteinOcis->getNotifications();
        $this->assertContainsOnlyInstancesOf(
            Notification::class,
            $notifications,
            "Array is not instance of " . Notification::class,
        );
        $this->assertCount(
            1,
            $notifications,
            "Expected one notification but received " . count($notifications),
        );
        $this->assertSame(
            $sharerUser[0]->getDisplayName() .
            " shared to-share-test.txt with you",
            $notifications[0]->getMessage(),
            "Wrong Notification received",
        );
        $this->assertMatchesRegularExpression(
            '/' . $this->getUUIDv4Regex() . '/i',
            $notifications[0]->getId(),
            "Incorrect Format of Notifications received",
        );
    }

    public function testDeleteNotification(): void
    {
        $notifications = $this->einsteinOcis->getNotifications();
        $notifications[0]->delete();
        $notificationsAfterDeletion = $this->einsteinOcis->getNotifications();
        $this->assertCount(
            count($notifications) - 1,
            $notificationsAfterDeletion,
            "Notification should be deleted but exists",
        );
        $this->assertNotEquals($notifications, $notificationsAfterDeletion, "Deleted notification still exists");
    }
}
