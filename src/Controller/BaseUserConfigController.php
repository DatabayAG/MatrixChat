<?php

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

declare(strict_types=1);

namespace ILIAS\Plugin\MatrixChatClient\Controller;

use ilAuthUtils;
use ILIAS\DI\Container;
use ILIAS\Filesystem\Stream\Streams;
use ILIAS\HTTP\Services;
use ILIAS\Plugin\Libraries\ControllerHandler\BaseController;
use ILIAS\Plugin\Libraries\ControllerHandler\ControllerHandler;
use ILIAS\Plugin\MatrixChatClient\Api\MatrixApi;
use ILIAS\Plugin\MatrixChatClient\Api\MatrixApiException;
use ILIAS\Plugin\MatrixChatClient\Form\BaseUserConfigForm;
use ILIAS\Plugin\MatrixChatClient\Model\CourseSettings;
use ILIAS\Plugin\MatrixChatClient\Model\UserConfig;
use ILIAS\Plugin\MatrixChatClient\Model\UserRoomAddQueue;
use ILIAS\Plugin\MatrixChatClient\Repository\CourseSettingsRepository;
use ILIAS\Plugin\MatrixChatClient\Repository\UserRoomAddQueueRepository;
use ilLanguage;
use ilLogger;
use ilMatrixChatClientPlugin;
use ilMatrixChatClientUIHookGUI;
use ilObject;
use ilObjUser;
use ilParticipants;
use ilPersonalSettingsGUI;
use ilRepositoryGUI;
use ilTabsGUI;
use ilUIPluginRouterGUI;

/**
 * Class BaseUserConfigController
 *
 * @package ILIAS\Plugin\MatrixChatClient\Controller
 * @author  Marvin Beym <mbeym@databay.de>
 */
abstract class BaseUserConfigController extends BaseController
{
    public const TAB_USER_CHAT_CONFIG = "user-chat-config";
    public const CMD_SHOW_USER_CHAT_CONFIG = "showUserChatConfig";
    public const CMD_SAVE_USER_CHAT_CONFIG = "saveUserChatConfig";
    public const CMD_RESET_ACCOUNT_SETTINGS = "resetAccountSettings";
    public const AJAX_CMD_CHECK_EXTERNAL_ACCOUNT = "ajaxCheckExternalAccount";
    protected UserConfig $userConfig;
    protected ilObjUser $user;
    protected ilTabsGUI $tabs;
    protected ilMatrixChatClientPlugin $plugin;
    protected MatrixApi $matrixApi;
    protected ilLogger $logger;
    private Services $http;
    protected UserRoomAddQueueRepository $userRoomAddQueueRepo;
    protected CourseSettingsRepository $courseSettingsRepo;
    private ilLanguage $lng;

    public function __construct(Container $dic, ControllerHandler $controllerHandler)
    {
        parent::__construct($dic, $controllerHandler);

        $this->lng = $this->dic->language();
        $this->user = $this->dic->user();
        $this->tabs = $this->dic->tabs();
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
        $this->userConfig = (new UserConfig($this->user))->load();
        $this->matrixApi = $this->plugin->getMatrixApi();
        $this->logger = $this->dic->logger()->root();
        $this->http = $this->dic->http();
        $this->userRoomAddQueueRepo = UserRoomAddQueueRepository::getInstance($this->dic->database());
        $this->courseSettingsRepo = CourseSettingsRepository::getInstance();
    }

    protected function verifyCorrectController(): void
    {
        if ($this instanceof LocalUserConfigController && (int) $this->user->getAuthMode(true) !== ilAuthUtils::AUTH_LOCAL) {
            /**
             * @var ExternalUserConfigController $externalUserConfigController
             */
            $externalUserConfigController = $this->controllerHandler->getController(ExternalUserConfigController::class);
            $externalUserConfigController->redirectToCommand(self::CMD_SHOW_USER_CHAT_CONFIG);
        }

        if ($this instanceof ExternalUserConfigController && (int) $this->user->getAuthMode(true) === ilAuthUtils::AUTH_LOCAL) {
            /**
             * @var LocalUserConfigController $localUserConfigController
             */
            $localUserConfigController = $this->controllerHandler->getController(LocalUserConfigController::class);
            $localUserConfigController->redirectToCommand(self::CMD_SHOW_USER_CHAT_CONFIG);
        }
    }

    public function processUserRoomAddQueue(ilObjUser $user): ?string
    {
        $userConfig = (new UserConfig($user))->load();

        if (!$userConfig->getMatrixUserId()) {
            return null;
        }

        $matrixUser = $this->matrixApi->getUser($userConfig->getMatrixUserId());
        if (!$matrixUser) {
            return null;
        }

        /**
         * @var array<int, CourseSettings> $courseSettingsCache
         */
        $courseSettingsCache = [];

        $resultText = $this->plugin->txt("matrix.user.queue.inviteProcessResult");

        $processResults = [];

        $space = null;
        $matrixSpaceId = $this->plugin->getPluginConfig()->getMatrixSpaceId();
        if ($matrixSpaceId) {
            $space = $this->matrixApi->getSpace($matrixSpaceId);
        }
        if (!$space) {
            $this->logger->error("Unable to get space with id '$matrixSpaceId'");
            return "";
        }

        foreach ($this->userRoomAddQueueRepo->readAllByUserId($user->getId()) as $userRoomAddQueue) {
            if (!ilObject::_exists($userRoomAddQueue->getRefId(), true)) {
                $this->logger->error(sprintf(
                    "Unable to continue processing queue entry of user with id '%s' to object with ref-id '%s'. Object does not exist",
                    $user->getId(),
                    $userRoomAddQueue->getRefId()
                ));
                $this->userRoomAddQueueRepo->delete($userRoomAddQueue);
                continue;
            }
            if (!array_key_exists($userRoomAddQueue->getRefId(), $courseSettingsCache)) {
                $courseSettingsCache[$userRoomAddQueue->getRefId()] = $this->courseSettingsRepo->read($userRoomAddQueue->getRefId());
            }

            if (!ilParticipants::_isParticipant($userRoomAddQueue->getRefId(), $user->getId())) {
                $this->logger->error(sprintf(
                    "Unable to continue processing queue entry of user with id '%s' to object with ref-id '%s'. User is not a participant of this object",
                    $user->getId(),
                    $userRoomAddQueue->getRefId()
                ));
                $this->userRoomAddQueueRepo->delete($userRoomAddQueue);
                continue;
            }

            $courseSettings = $courseSettingsCache[$userRoomAddQueue->getRefId()];

            if ($courseSettings->getMatrixRoomId()) {
                $room = $this->matrixApi->getRoom($courseSettings->getMatrixRoomId());
                if (!$room) {
                    continue;
                }

                if (!$room->isMember($matrixUser)) {
                    if (!$this->matrixApi->inviteUserToRoom($matrixUser, $space)) {
                        $this->logger->error("Inviting matrix-user '{$matrixUser->getId()}' to space '{$space->getId()}' failed");
                    }
                    if (!$this->matrixApi->inviteUserToRoom($matrixUser, $room)) {
                        $this->logger->error("Inviting matrix-user '{$matrixUser->getId()}' to room '{$room->getId()}' failed");
                    }
                    $this->ctrl->setParameterByClass(ilRepositoryGUI::class, "ref_id", $courseSettings->getCourseId());
                    $objectLink = $this->ctrl->getLinkTargetByClass(ilRepositoryGUI::class, "view");

                    $processResults[] = sprintf(
                        "<tr><td>%s</td><td><a href='%s'>%s</a></td><td>%s</td></tr>",
                        $this->lng->txt(ilObject::_lookupType($courseSettings->getCourseId(), true)),
                        $objectLink,
                        ilObject::_lookupTitle(ilObject::_lookupObjId($courseSettings->getCourseId())),
                        $room->getName()
                    );
                } else {
                    $this->userRoomAddQueueRepo->delete($userRoomAddQueue);
                }
            }
        }
        if ($processResults === []) {
            return "";
        }
        return sprintf($resultText, implode("\n", $processResults));
    }

    abstract public function showUserChatConfig(?BaseUserConfigForm $form = null): void;

    abstract public function saveUserChatConfig(): void;

    abstract public function buildUsername(): string;

    public function resetAccountSettings(): void
    {
        $oldMatrixUserId = $this->userConfig->getMatrixUserId();
        $matrixUser = $this->matrixApi->getUser($oldMatrixUserId);
        if ($matrixUser) {
            foreach ($this->courseSettingsRepo->readAll() as $courseSetting) {
                if (!$courseSetting->getMatrixRoomId()) {
                    //No need to remove user from room because no room configured
                    continue;
                }

                $matrixRoom = $this->matrixApi->getRoom($courseSetting->getMatrixRoomId());
                if (!$matrixRoom) {
                    //No need to remove user from room because no room found/configured
                    continue;
                }

                if ($matrixRoom->isMember($matrixUser)) {
                    $reason = "Removed Matrix-Account from ILIAS-Plattform";
                    if (!$this->matrixApi->removeUserFromRoom($matrixUser->getId(), $matrixRoom, $reason)) {
                        $this->logger->error(sprintf(
                            "Removing user '%s' from room '%s' for reason '%s' failed.",
                            $matrixUser->getId(),
                            $matrixRoom->getId(),
                            $reason
                        ));
                    }

                    //If no entry in the queue exists anymore,
                    //create a new one so the user gets re-added to the matrix room once the matrix-account is configured again
                    if (
                        !$this->userRoomAddQueueRepo->exists($this->user->getId(), $courseSetting->getCourseId())
                        && !$this->userRoomAddQueueRepo->create(new UserRoomAddQueue(
                            $this->user->getId(),
                            $courseSetting->getCourseId()
                        ))
                    ) {
                        $this->logger->error(sprintf(
                            "ILIAS-User with id '%s' (matrix: '%s') could not be added back to queue after removing user from room '%s' when user reset matrix-account settings",
                            $this->user->getId(),
                            $matrixUser->getId(),
                            $matrixRoom->getId()
                        ));
                    }
                }

                $statusOfUserInRoom = $this->matrixApi->getStatusOfUserInRoom($matrixRoom,
                    $matrixUser->getId());

                if ($statusOfUserInRoom === ChatController::USER_STATUS_INVITE
                    && !$this->matrixApi->removeUserFromRoom(
                        $matrixUser->getId(),
                        $matrixRoom, "Invite redacted because Matrix-Account of user was reset")
                ) {
                    $this->logger->error(sprintf(
                        "Error occurred while trying to remove invited user '%s' from room '%s' after matrix-account of user was reset",
                        $matrixUser->getId(),
                        $matrixRoom->getId()
                    ));
                }
            }
        }


        $this->userConfig
            ->setMatrixUserId("")
            ->setAuthMethod("")
            ->save();

        $this->uiUtil->sendSuccess($this->plugin->txt("config.user.resetAccountSettings.success"), true);
        $this->redirectToCommand(self::CMD_SHOW_USER_CHAT_CONFIG);
    }

    public function injectTabs(string $selectedTabId): void
    {
        $gui = new ilPersonalSettingsGUI();
        $gui->__initSubTabs("showPersonalData");
        $gui->setHeader();

        $this->tabs->setForcePresentationOfSingleTab(true);

        $this->tabs->addTab(
            self::TAB_USER_CHAT_CONFIG,
            $this->plugin->txt("config.user.title"),
            $this->getCommandLink(self::CMD_SHOW_USER_CHAT_CONFIG)
        );

        $this->tabs->activateTab($selectedTabId);
    }

    public function ajaxCheckExternalAccount(): void
    {
        $post = json_decode(file_get_contents("php://input"), true);

        $matrixUserId = $post["matrixUserId"] ?? null;
        $content = [
            "message" => [
                "failure" => "",
                "success" => "",
                "info" => ""
            ],
            "result" => "success"
        ];
        if (!$matrixUserId) {
            $content["message"]["failure"] = $this->plugin->txt("config.user.externalMatrixUserLookup.missingMatrixUserIdInPost");
            $response = $this->http->response()->withBody(Streams::ofString(json_encode(
                $content,
                JSON_THROW_ON_ERROR
            )));
            $this->http->saveResponse($response);
            $this->http->sendResponse();
            $this->http->close();
        }
        try {
            $profileData = $this->matrixApi->getMatrixUserProfile($matrixUserId);
            $content["message"]["success"] = $this->plugin->txt("config.user.externalMatrixUserLookup.success");
        } catch (MatrixApiException $e) {
            switch ($e->getErrorCode()) {
                case "M_FORBIDDEN":
                    $content["message"]["failure"] = $this->plugin->txt("config.user.externalMatrixUserLookup.failure.lookupDisabled");
                    $this->logger->info("Unable to lookup profile for user {$matrixUserId}. Federation lookup disabled");
                    break;
                case "M_NOT_FOUND":
                    $content["message"]["failure"] = sprintf(
                        $this->plugin->txt("config.user.externalMatrixUserLookup.failure.notExist"),
                        $matrixUserId
                    );
                    $this->logger->info("Unable to lookup profile for user $matrixUserId. Profile does not exist");
                    break;
                default:
                    $content["message"]["failure"] = $this->plugin->txt("config.user.externalMatrixUserLookup.failure.unknown");
                    $this->logger->info(sprintf(
                        "Unable to lookup profile for user %s. Unexpected exception. Error-Code: %s. Ex.: %s",
                        $matrixUserId,
                        $e->getErrorCode(),
                        $e->getMessage()
                    ));
                    break;
            }

            $content["message"]["info"] = sprintf(
                $this->plugin->txt("config.user.externalMatrixUserLookup.info"),
                $this->plugin->txt("config.user.resetAccountSettings")
            );
        }

        $content["result"] = $content["message"]["failure"] === "" ? "success" : "failure";

        $response = $this->http->response()->withBody(Streams::ofString(json_encode($content, JSON_THROW_ON_ERROR)));
        $this->http->saveResponse($response);
        $this->http->sendResponse();
        $this->http->close();
    }

    protected function buildMatrixUserId(): string
    {
        return "@{$this->buildUsername()}:{$this->plugin->getPluginConfig()->getMatrixServerName()}";
    }

    public function getCtrlClassesForCommand(string $cmd): array
    {
        return [ilUIPluginRouterGUI::class, ilMatrixChatClientUIHookGUI::class];
    }
}
