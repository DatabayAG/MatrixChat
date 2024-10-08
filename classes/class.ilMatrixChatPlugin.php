<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *********************************************************************/

declare(strict_types=1);

use ILIAS\DI\Container;
use ILIAS\Plugin\Libraries\ControllerHandler\UiUtils;
use ILIAS\Plugin\MatrixChat\Api\MatrixApi;
use ILIAS\Plugin\MatrixChat\Job\ProcessQueuedInvitesJob;
use ILIAS\Plugin\MatrixChat\Model\CourseSettings;
use ILIAS\Plugin\MatrixChat\Model\MatrixRoom;
use ILIAS\Plugin\MatrixChat\Model\MatrixUser;
use ILIAS\Plugin\MatrixChat\Model\PluginConfig;
use ILIAS\Plugin\MatrixChat\Model\Room\MatrixSpace;
use ILIAS\Plugin\MatrixChat\Model\UserConfig;
use ILIAS\Plugin\MatrixChat\Model\UserRoomAddQueue;
use ILIAS\Plugin\MatrixChat\Repository\CourseSettingsRepository;
use ILIAS\Plugin\MatrixChat\Repository\QueuedInvitesRepository;

require_once __DIR__ . "/../vendor/autoload.php";

class ilMatrixChatPlugin extends ilUserInterfaceHookPlugin implements ilCronJobProvider
{
    /** @var string */
    public const CTYPE = "Services";
    /** @var string */
    public const CNAME = "UIComponent";
    /** @var string */
    public const SLOT_ID = "uihk";

    /** @var string */
    public const PNAME = "MatrixChat";

    private static ?ilMatrixChatPlugin $instance = null;
    private ?PluginConfig $pluginConfig = null;
    private QueuedInvitesRepository $queuedInvitesRepo;
    private CourseSettingsRepository $courseSettingsRepo;
    protected ?MatrixApi $matrixApi = null;
    public Container $dic;
    public ilSetting $settings;
    private UiUtils $uiUtil;
    private ilObjUser $user;
    private ilLogger $logger;

    public function __construct(ilDBInterface $db, ilComponentRepositoryWrite $component_repository, string $id)
    {
        global $DIC;
        $this->dic = $DIC;
        $this->settings = new ilSetting(self::class);
        $this->queuedInvitesRepo = QueuedInvitesRepository::getInstance($this->dic->database());
        $this->courseSettingsRepo = CourseSettingsRepository::getInstance($this->dic->database());
        $this->uiUtil = new UiUtils();
        $this->user = $this->dic->user();
        $this->logger = $this->dic->logger()->root();
        parent::__construct($db, $component_repository, $id);
    }

    public function getPluginName(): string
    {
        return self::PNAME;
    }

    public static function getInstance(): self
    {
        if (self::$instance) {
            return self::$instance;
        }

        global $DIC;

        /** @var ilComponentFactory $componentFactory */
        $componentFactory = $DIC["component.factory"];
        self::$instance = $componentFactory->getPlugin("mcc");
        return self::$instance;
    }

    public function assetsFolder(string $file = ""): string
    {
        return $this->getDirectory() . "/assets/$file";
    }

    public function cssFolder(string $file = ""): string
    {
        return $this->assetsFolder("css/$file");
    }

    public function imagesFolder(string $file = ""): string
    {
        return $this->assetsFolder("images/$file");
    }

    public function templatesFolder(string $file = ""): string
    {
        return $this->assetsFolder("templates/$file");
    }

    public function jsFolder(string $file = ""): string
    {
        return $this->assetsFolder("js/$file");
    }

    public function getUsernameSchemeVariables(): array
    {
        return [
            "CLIENT_ID" => CLIENT_ID,
            "LOGIN" => $this->user->getLogin(),
            "EXTERNAL_ACCOUNT" => $this->user->getExternalAccount()
        ];
    }

    public function getRoomSchemeVariables(): array
    {
        return [
            "CLIENT_ID" => CLIENT_ID
        ];
    }

    public function getObjGUIClassByType(string $type): ?string
    {
        switch ($type) {
            case "crs":
                return ilObjCourseGUI::class;
            case "grp":
                return ilObjGroupGUI::class;
            default:
                return null;
        }
    }

    public function redirectToHome(): void
    {
        global $DIC;
        $DIC->ctrl()->redirectByClass("ilDashboardGUI", "show");
    }

    public function isUserAdmin(?int $userId = null, ?int $roleId = null): bool
    {
        if ($userId === null) {
            $userId = $this->user->getId();
        }

        if ($roleId === null) {
            if (defined("SYSTEM_ROLE_ID")) {
                $roleId = (int) SYSTEM_ROLE_ID;
            } else {
                $roleId = 2;
            }
        }

        $roleIds = [];

        foreach ($this->dic->rbac()->review()->assignedGlobalRoles($userId) as $id) {
            $roleIds[] = (int) $id;
        }

        return in_array($roleId, $roleIds, true);
    }

    public function isAtLeastIlias6(): bool
    {
        return version_compare(ILIAS_VERSION_NUMERIC, "6", ">=");
    }

    public function denyConfigIfPluginNotActive(): void
    {
        if (!$this->isActive()) {
            global $DIC;
            $this->uiUtil->sendFailure($this->txt("general.plugin.notActivated"), true);
            $DIC->ctrl()->redirectByClass(ilObjComponentSettingsGUI::class, "view");
        }
    }

    public function getMatrixApi(): MatrixApi
    {
        if (!$this->matrixApi && $this->isActive()) {
            $this->matrixApi = new MatrixApi(
                $this->getPluginConfig(),
                200,
                $this,
                $this->logger
            );
        }
        return $this->matrixApi;
    }

    protected function beforeUninstall(): bool
    {
        $this->settings->deleteAll();
        return parent::beforeUninstall();
    }

    public function getPluginConfig(): PluginConfig
    {
        if (!$this->pluginConfig && $this->isActive()) {
            $this->pluginConfig = (new PluginConfig($this->settings))->load();
        }
        return $this->pluginConfig;
    }

    public function handleEvent(string $a_component, string $a_event, $a_parameter): void
    {
        if (!in_array($a_component, ["Modules/Course", "Modules/Group"])) {
            return;
        }

        if ($a_event === "delete") {
            try {
                /**
                 * @var ilObjCourse|ilObjGroup $object
                 */
                $object = $a_parameter["object"];

                $refId = $object->getRefId();
                $courseSettings = $this->courseSettingsRepo->read($refId);
                if ($courseSettings->getMatrixRoomId() && !$this->courseSettingsRepo->delete($courseSettings)) {
                    $this->logger->warning("Error occurred trying to delete settings for object with ref_id '$refId' after object was deleted");
                    return;
                }
                foreach ($this->queuedInvitesRepo->readAllByRefId($refId) as $userRoomAddQueue) {
                    if (!$this->queuedInvitesRepo->delete($userRoomAddQueue)) {
                        $this->logger->warning("Error occurred trying to delete queued invited for object with ref_id '$refId' after object was deleted");
                    }
                }
            } catch (Throwable $e) {
                //If refID is undefined for some reason, don't cause a crash. It's not mandatory to clean up left over data.
                return;
            }
        }

        if (!in_array($a_event, ["addParticipant", "deleteParticipant", "update"], true)) {
            return;
        }

        $objId = (int) $a_parameter["obj_id"];

        if ($a_event === "update") {
            /**
             * @var ilObjCourse|ilObjGroup $object
             */
            $object = $a_parameter["object"];

            $oldOfflineStatus = ilObject::lookupOfflineStatus($objId);
            $newOfflineStatus = $object->getOfflineStatus();

            if ($newOfflineStatus !== $oldOfflineStatus && !$newOfflineStatus) {
                $userIds = $object->getMembersObject()->getParticipants();
            } else {
                return;
            }
        } else {
            $userIds = [(int) $a_parameter["usr_id"]];
        }

        $matrixApi = $this->getMatrixApi();

        $matrixSpaceId = $this->pluginConfig->getMatrixSpaceId();
        if (!$matrixSpaceId) {
            $this->logger->warning("Unable to continue handling event '$a_event'. No Matrix-Space-ID found");
            return;
        }

        $space = $matrixApi->getSpace($matrixSpaceId);
        if (!$space) {
            $this->logger->warning("Unable to continue handling event '$a_event'. Matrix-Space-ID '$matrixSpaceId' saved but retrieving space failed");
            return;
        }

        $rooms = $this->findMatrixRoomsLinkedToObjId($objId, $a_event, $matrixApi);
        if($rooms === []) {
            $this->logger->warning("Unable to continue handling event '$a_event'. No room(s) were found using the obj-id '$objId'");
            return;
        }

        foreach ($userIds as $userId) {
            $user = new ilObjUser($userId);
            $userConfig = (new UserConfig($user))->load();

            $matrixUserId = $userConfig->getMatrixUserId();
            if ($matrixUserId) {
                $matrixUser = $this->getMatrixApi()->getUser($matrixUserId);
            } else {
                $matrixUser = null;
            }


            foreach ($rooms as $objRefId => $room) {
                $participants = ilParticipants::getInstance($objRefId);

                if ($a_event === "addParticipant" || $a_event === "update") {
                    $this->inviteParticipant(
                        $user,
                        $objId,
                        $objRefId,
                        $matrixUser,
                        $room,
                        $space,
                        $this->determinePowerLevelOfParticipant($participants, $user->getId()),
                        $a_event === "update" ? $newOfflineStatus : ilObject::lookupOfflineStatus($objId)

                );
                } else {
                    $this->removeParticipant(
                        $user,
                        $objRefId,
                        $matrixUser,
                        $room
                    );
                }
            }
        }
    }

    /**
     * @return array<int, MatrixRoom>
     */
    private function findMatrixRoomsLinkedToObjId(int $objId, string $event, MatrixApi $matrixApi): array
    {
        $rooms = [];
        foreach (ilObject::_getAllReferences($objId) as $objRefId) {
            $objRefId = (int) $objRefId;
            $courseSettings = $this->courseSettingsRepo->read($objRefId);
            $matrixRoomId = $courseSettings->getMatrixRoomId();

            if (!$matrixRoomId) {
                $this->logger->debug("Unable to continue handling event '$event'. No Matrix-Room-ID found in setting of object with ref_id '$objRefId'");
                continue;
            }

            $room = $matrixApi->getRoom($matrixRoomId);

            if (!$room) {
                $this->logger->debug("Unable to continue handling event '$event'. Matrix-Room-ID '$matrixRoomId' saved but retrieving room failed. Skipping");
                continue;
            }

            $rooms[$objRefId] = $room;
        }

        return $rooms;
    }

    private function inviteParticipant(ilObjUser $user, int $objId, int $objRefId, ?MatrixUser $matrixUser, MatrixRoom $room, MatrixSpace $space, int $powerLevel, bool $objectOffline): void
    {
        $addToQueue = false;

        if (!$objectOffline) {
            if (!$matrixUser) {
                $addToQueue = true;
            }
        } else {
            $addToQueue = true;
        }

        if ($addToQueue) {
            $this->queuedInvitesRepo->create(new UserRoomAddQueue($user->getId(), $objRefId));
        } elseif (
            $matrixUser
            && !$room->isMember($matrixUser)
        ) {
            if (!$this->getMatrixApi()->inviteUserToRoom($matrixUser, $space)) {
                $this->logger->warning(sprintf(
                    "Inviting matrix-user '%s' to space '%s' failed",
                    $matrixUser->getId(),
                    $space->getId()
                ));
            }

            if (!$this->getMatrixApi()->inviteUserToRoom(
                $matrixUser,
                $room,
                $powerLevel
            )) {
                $this->logger->warning(sprintf(
                    "Inviting matrix-user '%s' to room '%s' failed",
                    $matrixUser->getId(),
                    $room->getId()
                ));
            }
        }
    }

    private function removeParticipant(ilObjUser $user, int $objRefId, ?MatrixUser $matrixUser, MatrixRoom $room): void
    {
        $userRoomAddQueue = $this->queuedInvitesRepo->read($user->getId(), $objRefId);

        if ($userRoomAddQueue) {
            $this->queuedInvitesRepo->delete($userRoomAddQueue);
        }

        if ($matrixUser) {
            if (!$this->getMatrixApi()->removeUserFromRoom(
                $matrixUser->getId(),
                $room,
                "Removed from course/group"
            )) {
                $this->logger->warning(sprintf(
                    "Removing matrixuser '%s' from room '%s'. with Reason 'Removed from Course/Group object' failed.",
                    $matrixUser->getId(),
                    $room->getId()
                ));
            }

            $this->logger->info(sprintf(
                "Removed matrix user '%s' from room '%s'. Reason: Removed from Course/Group object.",
                $matrixUser->getId(),
                $room->getId()
            ));
        }
    }

    public function determinePowerLevelOfParticipant(ilParticipants $participants, int $participantId): int
    {
        $pluginConfig = $this->getPluginConfig();
        $powerLevel = $pluginConfig->isModifyParticipantPowerLevel() ? $pluginConfig->getMemberPowerLevel() : 0;
        if ($participants->isTutor($participantId)) {
            $powerLevel = $pluginConfig->isModifyParticipantPowerLevel() ? $pluginConfig->getTutorPowerLevel() : 50;
        }
        if ($participants->isAdmin($participantId)) {
            $powerLevel = $pluginConfig->isModifyParticipantPowerLevel() ? $pluginConfig->getAdminPowerLevel() : 100;
        }
        return $powerLevel;
    }

    public function getCronJobInstances(): array
    {
        return [
            new ProcessQueuedInvitesJob($this->dic, $this)
        ];
    }

    /**
     * @throws Exception
     */
    public function getCronJobInstance(string $jobId): ilCronJob
    {
        foreach ($this->getCronJobInstances() as $cronJobInstance) {
            if ($cronJobInstance->getId() === $jobId) {
                return $cronJobInstance;
            }
        }
        throw new Exception("No cron job found with the id '$jobId'.");
    }
}
