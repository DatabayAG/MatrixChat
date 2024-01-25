<?php

declare(strict_types=1);

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

use ILIAS\DI\Container;
use ILIAS\Plugin\Libraries\ControllerHandler\UiUtils;
use ILIAS\Plugin\MatrixChatClient\Api\MatrixApi;
use ILIAS\Plugin\MatrixChatClient\Model\CourseSettings;
use ILIAS\Plugin\MatrixChatClient\Model\MatrixRoom;
use ILIAS\Plugin\MatrixChatClient\Model\PluginConfig;
use ILIAS\Plugin\MatrixChatClient\Model\UserConfig;
use ILIAS\Plugin\MatrixChatClient\Model\UserRoomAddQueue;
use ILIAS\Plugin\MatrixChatClient\Repository\CourseSettingsRepository;
use ILIAS\Plugin\MatrixChatClient\Repository\UserRoomAddQueueRepository;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Class ilMatrixChatClientPlugin
 */
class ilMatrixChatClientPlugin extends ilUserInterfaceHookPlugin
{
    /** @var string */
    public const CTYPE = "Services";
    /** @var string */
    public const CNAME = "UIComponent";
    /** @var string */
    public const SLOT_ID = "uihk";

    /** @var string */
    public const PNAME = "MatrixChatClient";

    private static ?ilMatrixChatClientPlugin $instance = null;
    private ?PluginConfig $pluginConfig = null;
    private UserRoomAddQueueRepository $userRoomAddQueueRepo;
    private CourseSettingsRepository $courseSettingsRepo;
    protected ?MatrixApi $matrixApi = null;
    public Container $dic;
    private ilCtrl $ctrl;
    public ilSetting $settings;
    private UiUtils $uiUtil;

    public function __construct(ilDBInterface $db, ilComponentRepositoryWrite $component_repository, string $id)
    {
        global $DIC;
        $this->dic = $DIC;
        $this->ctrl = $this->dic->ctrl();
        $this->settings = new ilSetting(self::class);
        $this->userRoomAddQueueRepo = UserRoomAddQueueRepository::getInstance($this->dic->database());
        $this->courseSettingsRepo = CourseSettingsRepository::getInstance($this->dic->database());
        $this->uiUtil = new UiUtils();

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

        /**
         * @var ilComponentFactory $componentFactory
         */
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
            "LOGIN" => $this->dic->user()->getLogin(),
            "EXTERNAL_ACCOUNT" => $this->dic->user()->getExternalAccount()
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
        if ($this->isAtLeastIlias6()) {
            $this->ctrl->redirectByClass("ilDashboardGUI", "show");
        } else {
            $this->ctrl->redirectByClass("ilPersonalDesktopGUI");
        }
    }

    public function isUserAdmin(?int $userId = null, ?int $roleId = null): bool
    {
        if ($userId === null) {
            $userId = $this->dic->user->getId();
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
            $this->uiUtil->sendFailure($this->txt("general.plugin.notActivated"), true);
            $this->dic->ctrl()->redirectByClass(ilObjComponentSettingsGUI::class, "view");
        }
    }

    public function getMatrixApi(): MatrixApi
    {
        if (!$this->matrixApi && $this->isActive()) {
            $this->matrixApi = new MatrixApi(
                $this->getPluginConfig()->getmatrixServerUrl(),
                200,
                $this,
                $this->dic->logger()->root()
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

    public function processUserRoomAddQueue(ilObjUser $user): void
    {
        $matrixApi = $this->getMatrixApi();
        $userConfig = (new UserConfig($user))->load();

        $matrixUser = $this->getMatrixApi()->getUser($userConfig->getMatrixUserId());
        if (!$matrixUser) {
            return;
        }

        /**
         * @var array<int, CourseSettings> $courseSettingsCache
         */
        $courseSettingsCache = [];

        foreach ($this->userRoomAddQueueRepo->readAllByUserId($user->getId()) as $userRoomAddQueue) {
            if (!array_key_exists($userRoomAddQueue->getRefId(), $courseSettingsCache)) {
                $courseSettingsCache[$userRoomAddQueue->getRefId()] = $this->courseSettingsRepo->read($userRoomAddQueue->getRefId());
            }

            $courseSettings = $courseSettingsCache[$userRoomAddQueue->getRefId()];

            if ($courseSettings->getMatrixRoomId()) {
                $room = $matrixApi->getRoom($courseSettings->getMatrixRoomId());

                if (!$room) {
                    continue;
                }

                if (!$room->isMember($matrixUser)) {
                    if (!$this->getMatrixApi()->inviteUserToRoom($matrixUser, $room)) {
                        $this->dic->logger()->root()->error("Inviting matrix-user '{$matrixUser->getMatrixUserId()}' to room '{$room->getId()}' failed");
                    }
                } else {
                    $this->userRoomAddQueueRepo->delete($userRoomAddQueue);
                }
            }
        }
    }

    public function handleEvent(string $a_component, string $a_event, $a_parameter): void
    {
        if (!in_array($a_event, ["addParticipant", "deleteParticipant"], true)) {
            return;
        }
        $matrixApi = $this->getMatrixApi();

        $objId = $a_parameter["obj_id"];
        $user = new ilObjUser($a_parameter["usr_id"]);
        if ($a_event === "addParticipant") {
            $roleId = $a_parameter["role_id"];
        }

        $userConfig = (new UserConfig($user))->load();

        $addToQueue = false;

        $matrixUser = null;
        if (!$userConfig->getMatrixUserId()) {
            $addToQueue = true;
        } else {
            $matrixUser = $this->getMatrixApi()->getUser($userConfig->getMatrixUserId());

            if (!$matrixUser) {
                $addToQueue = true;
            }
        }

        /**
         * @var array<int, CourseSettings> $courseSettingsCache
         */
        $courseSettingsCache = [];

        /**
         * @var array<string, MatrixRoom> $roomCache
         */
        $roomCache = [];

        foreach (ilObjCourse::_getAllReferences($objId) as $objRefId) {
            $objRefId = (int) $objRefId;

            if (!array_key_exists($objRefId, $courseSettingsCache)) {
                $courseSettingsCache[$objRefId] = $this->courseSettingsRepo->read($objRefId);
            }

            $courseSettings = $courseSettingsCache[$objRefId];
            $matrixRoomId = $courseSettings->getMatrixRoomId();

            if (!$matrixRoomId) {
                continue;
            }

            if (!array_key_exists($matrixRoomId, $roomCache)) {
                $matrixRoom = $matrixApi->getRoom($matrixRoomId);
                if ($matrixRoom) {
                    $roomCache[$matrixRoomId] = $matrixRoom;
                }
            }

            $room = $roomCache[$matrixRoomId] ?? null;

            if (!$room) {
                $this->dic->logger()->root()->error("Unable to process event '$a_event'. Matrix-Room-ID '$matrixRoomId' saved but retrieving room failed. Skipping");
                continue;
            }

            if ($a_event === "addParticipant") {
                //Add participant

                if ($addToQueue) {
                    $this->userRoomAddQueueRepo->create(new UserRoomAddQueue($user->getId(), $objRefId));
                } elseif (
                    $matrixUser
                    && !$room->isMember($matrixUser)
                ) {
                    if (!$this->getMatrixApi()->inviteUserToRoom($matrixUser, $room)) {
                        $this->dic->logger()->root()->error(sprintf(
                            "Inviting matrix-user '%s' to room '%s' failed",
                            $matrixUser->getMatrixUserId(),
                            $room->getId()
                        ));
                    }
                }
            } else {
                //Remove participant
                $userRoomAddQueue = $this->userRoomAddQueueRepo->read($user->getId(), $objRefId);

                if ($userRoomAddQueue) {
                    $this->userRoomAddQueueRepo->delete($userRoomAddQueue);
                }

                if ($matrixUser) {
                    $this->getMatrixApi()->removeUserFromRoom(
                        $matrixUser,
                        $room,
                        "Removed from course/group"
                    );
                }
            }
        }
    }
}
