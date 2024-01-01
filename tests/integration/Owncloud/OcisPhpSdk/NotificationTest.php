<?php

namespace integration\Owncloud\OcisPhpSdk;

use Owncloud\OcisPhpSdk\DriveOrder;
use Owncloud\OcisPhpSdk\DriveType;
use Owncloud\OcisPhpSdk\Notification;
use Owncloud\OcisPhpSdk\Ocis;
use Owncloud\OcisPhpSdk\OcisResource;
use Owncloud\OcisPhpSdk\OrderDirection;
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
        $personalDrive = $this->ocis->getMyDrives(
            DriveOrder::NAME,
            OrderDirection::ASC,
            DriveType::PERSONAL
        )[0];

        $personalDrive->uploadFile('to-share-test.txt', 'some content');
        $this->createdResources[$personalDrive->getId()][] = 'to-share-test.txt';
        $this->fileToShare = $personalDrive->getResources()[0];

        $this->einstein = $this->ocis->getUsers('einstein')[0];

        /**
         * @var SharingRole $role
         */
        foreach ($this->fileToShare->getRoles() as $role) {
            if ($role->getDisplayName() === 'Viewer') {
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
        $this->assertContainsOnlyInstancesOf(Notification::class, $notifications);
        $this->assertCount(1, $notifications);
        $this->assertSame($sharerUser[0]->getDisplayName(). " shared to-share-test.txt with you", $notifications[0]->getMessage());
        $this->assertMatchesRegularExpression(
            '/' . $this->getUUIDv4Regex() . '/i',
            $notifications[0]->getId()
        );
    }

    public function testDeleteNotification(): void
    {
        $notifications = $this->einsteinOcis->getNotifications();
        $notifications[0]->delete();
        $notificationsAfterDeletion = $this->einsteinOcis->getNotifications();
        $this->assertCount(count($notifications) - 1, $notificationsAfterDeletion);
        $this->assertNotEquals($notifications, $notificationsAfterDeletion, "Deleted notification still exists");
    }
}
