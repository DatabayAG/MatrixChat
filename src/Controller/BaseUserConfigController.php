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

namespace ILIAS\Plugin\MatrixChat\Controller;

use ilAuthUtils;
use ILIAS\DI\Container;
use ILIAS\Filesystem\Stream\Streams;
use ILIAS\HTTP\Services;
use ILIAS\Plugin\Libraries\ControllerHandler\BaseController;
use ILIAS\Plugin\Libraries\ControllerHandler\ControllerHandler;
use ILIAS\Plugin\MatrixChat\Api\MatrixApi;
use ILIAS\Plugin\MatrixChat\Api\MatrixApiException;
use ILIAS\Plugin\MatrixChat\Form\BaseUserConfigForm;
use ILIAS\Plugin\MatrixChat\Model\Room\MatrixSpace;
use ILIAS\Plugin\MatrixChat\Model\UserConfig;
use ILIAS\Plugin\MatrixChat\Model\UserRoomAddQueue;
use ILIAS\Plugin\MatrixChat\Repository\CourseSettingsRepository;
use ILIAS\Plugin\MatrixChat\Repository\MatrixUserHistoryRepository;
use ILIAS\Plugin\MatrixChat\Repository\QueuedInvitesRepository;
use ILIAS\Plugin\MatrixChat\Utils\UiUtil;
use ilLanguage;
use ilLink;
use ilLogger;
use ilMatrixChatPlugin;
use ilMatrixChatUIHookGUI;
use ilObject;
use ilObjUser;
use ilParticipants;
use ilPersonalSettingsGUI;
use ilRepositoryGUI;
use ilTabsGUI;
use ilUIPluginRouterGUI;
use ReflectionMethod;

abstract class BaseUserConfigController extends BaseController
{
    public const PERMANENT_LINK_ID = "matrixChatConfig";

    public const TAB_USER_CHAT_CONFIG = "user-chat-config";
    public const CMD_SHOW_USER_CHAT_CONFIG = "showUserChatConfig";
    public const CMD_SAVE_USER_CHAT_CONFIG = "saveUserChatConfig";
    public const CMD_RESET_ACCOUNT_SETTINGS = "resetAccountSettings";
    public const AJAX_CMD_CHECK_EXTERNAL_ACCOUNT = "ajaxCheckExternalAccount";
    protected UserConfig $userConfig;
    protected ilObjUser $user;
    protected ilTabsGUI $tabs;
    protected ilMatrixChatPlugin $plugin;
    protected MatrixApi $matrixApi;
    protected ilLogger $logger;
    private readonly Services $http;
    protected QueuedInvitesRepository $queuedInvitesRepo;
    protected CourseSettingsRepository $courseSettingsRepo;
    private readonly ilLanguage $lng;
    protected MatrixUserHistoryRepository $matrixUserHistoryRepo;
    protected UiUtil $uiUtil;

    public function __construct(Container $dic, ControllerHandler $controllerHandler)
    {
        parent::__construct($dic, $controllerHandler);

        $this->lng = $this->dic->language();
        $this->user = $this->dic->user();
        $this->tabs = $this->dic->tabs();
        $this->plugin = ilMatrixChatPlugin::getInstance();
        $this->userConfig = (new UserConfig($this->user))->load();
        $this->matrixApi = $this->plugin->getMatrixApi();
        $this->logger = $this->dic->logger()->root();
        $this->http = $this->dic->http();
        $this->queuedInvitesRepo = QueuedInvitesRepository::getInstance($this->dic->database());
        $this->courseSettingsRepo = CourseSettingsRepository::getInstance($this->dic->database());
        $this->matrixUserHistoryRepo = MatrixUserHistoryRepository::getInstance($this->dic->database());
        $this->uiUtil = new UiUtil($this->dic);
    }

    protected function verifyCorrectController(): void
    {
        if ($this instanceof LocalUserConfigController && (int) $this->user->getAuthMode(true) !== ilAuthUtils::AUTH_LOCAL) {
            $externalUserConfigController = ExternalUserConfigController::getInstance($this->controllerHandler);
            $externalUserConfigController->redirectToCommand(self::CMD_SHOW_USER_CHAT_CONFIG);
        }

        if ($this instanceof ExternalUserConfigController && (int) $this->user->getAuthMode(true) === ilAuthUtils::AUTH_LOCAL) {
            $localUserConfigController = LocalUserConfigController::getInstance($this->controllerHandler);
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

        $resultText = $this->plugin->txt("matrix.user.queue.inviteProcessResult");

        $processResults = [];

        /** @var array<string, MatrixSpace> $spaceCache */
        $spaceCache = [];
        foreach ($this->queuedInvitesRepo->readAllByUserId($user->getId()) as $userRoomAddQueue) {
            if (!ilObject::_exists($userRoomAddQueue->getRefId(), true)) {
                $this->logger->warning(sprintf(
                    "Unable to continue processing queue entry of user with id '%s' to object with ref-id '%s'. Object does not exist",
                    $user->getId(),
                    $userRoomAddQueue->getRefId()
                ));
                $this->queuedInvitesRepo->delete($userRoomAddQueue);
                continue;
            }

            if (ilObject::lookupOfflineStatus(ilObject::_lookupObjId($userRoomAddQueue->getRefId()))) {
                //Don't process queue entries for objects that are offline
                continue;
            }

            $courseSettings = $this->courseSettingsRepo->read($userRoomAddQueue->getRefId());
            $participants = ilParticipants::getInstance($userRoomAddQueue->getRefId());

            if (!ilParticipants::_isParticipant($userRoomAddQueue->getRefId(), $user->getId())) {
                $this->logger->warning(sprintf(
                    "Unable to continue processing queue entry of user with id '%s' to object with ref-id '%s'. User is not a participant of this object",
                    $user->getId(),
                    $userRoomAddQueue->getRefId()
                ));
                $this->queuedInvitesRepo->delete($userRoomAddQueue);
                continue;
            }

            if ($courseSettings->getMatrixRoomId()) {
                $room = $this->matrixApi->getRoom($courseSettings->getMatrixRoomId());

                $space = null;
                if ($courseSettings->getMatrixSpaceId()) {
                    if (isset($spaceCache[$courseSettings->getMatrixSpaceId()])) {
                        $space = $spaceCache[$courseSettings->getMatrixSpaceId()];
                    } else {
                        $space = $this->matrixApi->getSpace($courseSettings->getMatrixSpaceId());
                        $spaceCache[$courseSettings->getMatrixSpaceId()] = $space;
                    }

                    if (!$space) {
                        $this->logger->error("Unable to get space for object with ref-id '{$courseSettings->getCourseId()}'");
                        continue;
                    }
                }

                if (!$room) {
                    continue;
                }

                if (!$room->isMember($matrixUser)) {
                    $invited = $this->plugin->inviteParticipant(
                        $user,
                        $userRoomAddQueue->getRefId(),
                        $matrixUser,
                        $room,
                        $space,
                        $this->plugin->determinePowerLevelOfParticipant($participants, $user->getId()),
                        false
                    );

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
                    $this->queuedInvitesRepo->delete($userRoomAddQueue);
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

        foreach ($this->courseSettingsRepo->readAll() as $courseSetting) {
            if (!$courseSetting->getMatrixRoomId()) {
                //No need to remove user from room because no room configured
                continue;
            }

            $matrixRoom = $this->matrixApi->getRoom($courseSetting->getMatrixRoomId());
            if (!$matrixRoom) {
                //No need to remove user from room because no room found
                continue;
            }

            if ($matrixRoom->isMember($matrixUser)) {
                $reason = "Removed Matrix-Account from ILIAS-Plattform";
                if (!$this->matrixApi->removeUserFromRoom($matrixUser->getId(), $matrixRoom, $reason)) {
                    $this->logger->warning(sprintf(
                        "Removing user '%s' from room '%s' for reason '%s' failed.",
                        $matrixUser->getId(),
                        $matrixRoom->getId(),
                        $reason
                    ));
                }

                //If no entry in the queue exists anymore,
                //create a new one so the user gets re-added to the matrix room once the matrix-account is configured again
                if (
                    !$this->queuedInvitesRepo->exists($this->user->getId(), $courseSetting->getCourseId())
                    && !$this->queuedInvitesRepo->create(new UserRoomAddQueue(
                        $this->user->getId(),
                        $courseSetting->getCourseId()
                    ))
                ) {
                    $this->logger->warning(sprintf(
                        "ILIAS-User with id '%s' (matrix: '%s') could not be added back to queue after removing user from room '%s' when user reset matrix-account settings",
                        $this->user->getId(),
                        $matrixUser->getId(),
                        $matrixRoom->getId()
                    ));
                }
            }

            $statusOfUserInRoom = $this->matrixApi->getStatusOfUserInRoom(
                $matrixRoom,
                $matrixUser->getId()
            );

            if ($statusOfUserInRoom === ChatController::USER_STATUS_INVITE && !$this->matrixApi->removeUserFromRoom(
                    $matrixUser->getId(),
                    $matrixRoom,
                    "Invite redacted because Matrix-Account of user was reset"
                )) {
                $this->logger->warning(sprintf(
                    "Error occurred while trying to remove invited user '%s' from room '%s' after matrix-account of user was reset",
                    $matrixUser->getId(),
                    $matrixRoom->getId()
                ));
            }
        }

        $this->userConfig
            ->setMatrixUserId("")
            ->setAuthMethod("")
            ->save();

        $this->uiUtil->sendSuccess($this->plugin->txt("config.user.resetAccountSettings.success"));
        $this->redirectToCommand(self::CMD_SHOW_USER_CHAT_CONFIG);
    }

    public function injectTabs(string $selectedTabId): void
    {
        $gui = new ilPersonalSettingsGUI();
        $initSubTabsMethod = new ReflectionMethod($gui, "initSubTabs");
        $initSubTabsMethod->invoke($gui, "showPersonalData");
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

    public static function buildPermanentLink(bool $addToGlobalPage = false): string
    {
        if ($addToGlobalPage) {
            global $DIC;
            $DIC->ui()->mainTemplate()->setPermanentLink(self::PERMANENT_LINK_ID, null);
        }

        return ilLink::_getStaticLink(null, self::PERMANENT_LINK_ID);
    }

    protected function buildMatrixUserId(): string
    {
        return "@{$this->buildUsername()}:{$this->plugin->getPluginConfig()->getMatrixServerName()}";
    }

    public function getCtrlClassesForCommand(string $cmd): array
    {
        return [ilUIPluginRouterGUI::class, ilMatrixChatUIHookGUI::class];
    }
}
