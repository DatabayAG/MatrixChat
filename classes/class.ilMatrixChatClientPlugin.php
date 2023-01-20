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
use ILIAS\Plugin\MatrixChatClient\Libs\JsonTranslationLoader\JsonTranslationLoader;
use ILIAS\Plugin\MatrixChatClient\Model\PluginConfig;
use ILIAS\Plugin\MatrixChatClient\Api\MatrixApiCommunicator;
use ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Exception\ConfigLoadException;
use ILIAS\Plugin\MatrixChatClient\Model\UserConfig;
use ILIAS\Plugin\MatrixChatClient\Repository\CourseSettingsRepository;
use ILIAS\Plugin\MatrixChatClient\Repository\UserRoomAddQueueRepository;
use ILIAS\Plugin\MatrixChatClient\Model\UserRoomAddQueue;
use ILIAS\Plugin\MatrixChatClient\Model\CourseSettings;

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

    /**
     * @var ilMatrixChatClientPlugin|null
     */
    private static $instance = null;
    /**
     * @var PluginConfig
     */
    private $pluginConfig;
    /**
     * @var UserRoomAddQueueRepository
     */
    private $userRoomAddQueueRepo;
    /**
     * @var CourseSettingsRepository
     */
    private $courseSettingsRepo;
    /**
     * @var MatrixApiCommunicator
     */
    public $matrixApi;
    /**
     * @var Container
     */
    public $dic;
    /**
     * @var ilCtrl
     */
    private $ctrl;
    /**
     * @var ilSetting
     */
    public $settings;

    /**
     * @throws ConfigLoadException
     */
    public function __construct()
    {
        global $DIC;
        $this->dic = $DIC;
        $this->ctrl = $this->dic->ctrl();
        $this->settings = new ilSetting(self::class);
        $this->pluginConfig = (new PluginConfig($this->settings))->load();
        $this->matrixApi = new MatrixApiCommunicator($this, $this->pluginConfig->getmatrixServerUrl());
        $this->userRoomAddQueueRepo = UserRoomAddQueueRepository::getInstance($this->dic->database());
        $this->courseSettingsRepo = CourseSettingsRepository::getInstance($this->dic->database());

        parent::__construct();
    }

    public function getPluginName() : string
    {
        return self::PNAME;
    }

    /**
     * @return ilMatrixChatClientPlugin
     * @noinspection PhpIncompatibleReturnTypeInspection
     */
    public static function getInstance() : ilMatrixChatClientPlugin
    {
        return self::$instance ?? (self::$instance = ilPluginAdmin::getPluginObject(
            self::CTYPE,
            self::CNAME,
            self::SLOT_ID,
            self::PNAME
        ));
    }

    public function assetsFolder(string $file = "") : string
    {
        return $this->getDirectory() . "/assets/$file";
    }

    public function cssFolder(string $file = "") : string
    {
        return $this->assetsFolder("css/$file");
    }

    public function imagesFolder(string $file = "") : string
    {
        return $this->assetsFolder("images/$file");
    }

    public function templatesFolder(string $file = "") : string
    {
        return $this->assetsFolder("templates/$file");
    }

    public function jsFolder(string $file = "") : string
    {
        return $this->assetsFolder("js/$file");
    }

    public function getUsernameSchemeVariables() : array
    {
        return [
            "CLIENT_ID" => CLIENT_ID,
            "LOGIN" => $this->dic->user()->getLogin(),
            "EXTERNAL_ACCOUNT" => $this->dic->user()->getExternalAccount()
        ];
    }

    public function getObjGUIClassByType(string $type) : ?string
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

    public function redirectToHome() : void
    {
        if ($this->isAtLeastIlias6()) {
            $this->ctrl->redirectByClass("ilDashboardGUI", "show");
        } else {
            $this->ctrl->redirectByClass("ilPersonalDesktopGUI");
        }
    }

    public function isUserAdmin(?int $userId = null, ?int $roleId = null) : bool
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

    public function isAtLeastIlias6() : bool
    {
        return version_compare(ILIAS_VERSION_NUMERIC, "6", ">=");
    }

    public function denyConfigIfPluginNotActive() : void
    {
        if (!$this->isActive()) {
            ilUtil::sendFailure($this->txt("general.plugin.notActivated"), true);
            $this->ctrl->redirectByClass(ilObjComponentSettingsGUI::class, "view");
        }
    }

    protected function beforeUninstall() : bool
    {
        $this->settings->deleteAll();
        return parent::beforeUninstall();
    }

    public function updateLanguages($a_lang_keys = null) : void
    {
        try {
            $jsonTranslationLoader = new JsonTranslationLoader($this->getDirectory() . "/lang");
            $jsonTranslationLoader->load();
        } catch (Exception $e) {
        }
        parent::updateLanguages($a_lang_keys);
    }

    public function getPluginConfig() : PluginConfig
    {
        return $this->pluginConfig;
    }

    public function processUserRoomAddQueue(ilObjUser $user) : void
    {
        $userConfig = (new UserConfig($user))->load();

        $matrixUser = $this->matrixApi->admin->loginUserWithAdmin($user->getId(), $userConfig->getMatrixUserId());
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

            if ($courseSettings->isChatIntegrationEnabled() && $courseSettings->getMatrixRoomId()) {
                $room = $this->matrixApi->admin->getRoom($courseSettings->getMatrixRoomId());

                if (!$room) {
                    continue;
                }

                if (!$room->isMember($matrixUser)) {
                    if ($this->matrixApi->admin->addUserToRoom($matrixUser, $room)) {
                        $this->userRoomAddQueueRepo->delete($userRoomAddQueue);
                    }
                } else {
                    $this->userRoomAddQueueRepo->delete($userRoomAddQueue);
                }
            }
        }
    }

    /**
     * Called by ilias event system to hook into afterLogin event.
     *
     * @param string $a_component
     * @param string $a_event
     * @param mixed  $a_parameter
     * @return void
     * @throws ConfigLoadException
     * @throws ReflectionException
     */
    public function handleEvent($a_component, $a_event, $a_parameter) : void
    {
        if (!in_array($a_event, ["addParticipant", "deleteParticipant"], true)) {
            return;
        }

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
            $matrixUser = $this->matrixApi->admin->loginUserWithAdmin(
                $user->getId(),
                $userConfig->getMatrixUserId(),
            );

            if (!$matrixUser) {
                $addToQueue = true;
            }
        }

        /**
         * @var array<int, CourseSettings> $courseSettingsCache
         */
        $courseSettingsCache = [];

        foreach (ilObjCourse::_getAllReferences($objId) as $objRefId) {
            $objRefId = (int) $objRefId;

            if (!array_key_exists($objRefId, $courseSettingsCache)) {
                $courseSettingsCache[$objRefId] = $this->courseSettingsRepo->read($objRefId);
            }

            $courseSettings = $courseSettingsCache[$objRefId];

            if ($a_event === "addParticipant") {
                //Add participant
                if (
                    !$courseSettings->isChatIntegrationEnabled()
                    || !$courseSettings->getMatrixRoomId()
                ) {
                    continue;
                }

                $room = $this->matrixApi->admin->getRoom($courseSettings->getMatrixRoomId());

                if (!$room) {
                    continue;
                }

                if ($addToQueue) {
                    $this->userRoomAddQueueRepo->create(new UserRoomAddQueue($user->getId(), $objRefId));
                } elseif (
                    $matrixUser
                    && !$room->isMember($matrixUser)
                ) {
                    if ($this->matrixApi->admin->addUserToRoom($matrixUser, $room)) {
                        $this->userRoomAddQueueRepo->delete(new UserRoomAddQueue($user->getId(), $objRefId));
                    }
                }
            } else {
                //Remove participant
                $userRoomAddQueue = $this->userRoomAddQueueRepo->read($user->getId(), $objRefId);

                if ($userRoomAddQueue) {
                    $this->userRoomAddQueueRepo->delete($userRoomAddQueue);
                }

                if (
                    $courseSettings->getMatrixRoomId()
                    && $matrixUser
                ) {
                    $room = $this->matrixApi->admin->getRoom($courseSettings->getMatrixRoomId());

                    if ($room) {
                        $this->matrixApi->admin->removeUserFromRoom(
                            $matrixUser,
                            $room,
                            "Removed from course/group"
                        );
                    }
                }
            }
        }
    }
}
