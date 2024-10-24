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

use Exception;
use ilAccessHandler;
use ilAuthUtils;
use ilCourseParticipants;
use ILIAS\DI\Container;
use ILIAS\HTTP\Services;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Plugin\Libraries\ControllerHandler\BaseController;
use ILIAS\Plugin\Libraries\ControllerHandler\ControllerHandler;
use ILIAS\Plugin\MatrixChat\Api\MatrixApi;
use ILIAS\Plugin\MatrixChat\Form\ChatSettingsForm;
use ILIAS\Plugin\MatrixChat\Form\ConfirmDeleteRoomForm;
use ILIAS\Plugin\MatrixChat\Model\ChatMember;
use ILIAS\Plugin\MatrixChat\Model\CourseSettings;
use ILIAS\Plugin\MatrixChat\Model\MatrixRoom;
use ILIAS\Plugin\MatrixChat\Model\MatrixUserPowerLevel;
use ILIAS\Plugin\MatrixChat\Model\UserConfig;
use ILIAS\Plugin\MatrixChat\Repository\CourseSettingsRepository;
use ILIAS\Plugin\MatrixChat\Repository\QueuedInvitesRepository;
use ILIAS\Plugin\MatrixChat\Table\ChatMemberTable;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ilLanguage;
use ilLogger;
use ilMatrixChatPlugin;
use ilMatrixChatUIHookGUI;
use ilObjCourseGUI;
use ilObject;
use ilObjGroupGUI;
use ilObjUser;
use ilParticipants;
use ilRepositoryGUI;
use ilTabsGUI;
use ilUIPluginRouterGUI;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class ChatController extends BaseController
{
    public const CMD_SHOW_CHAT = "showChat";
    public const CMD_SHOW_CHAT_SETTINGS = "showChatSettings";
    public const CMD_CREATE_ROOM = "createRoom";
    public const CMD_SHOW_CONFIRM_DELETE_ROOM = "showConfirmDeleteRoom";
    public const CMD_DELETE_ROOM = "deleteRoom";
    public const CMD_SHOW_CHAT_MEMBERS = "showChatMembers";
    public const CMD_INVITE_SELECTED_PARTICIPANTS = "inviteSelectedParticipants";
    public const CMD_INVITE_PARTICIPANT = "inviteParticipant";
    public const CMD_APPLY_MEMBER_TABLE_FILTER = "applyMemberTableFilter";
    public const CMD_RESET_MEMBER_TABLE_FILTER = "resetMemberTableFilter";

    public const TAB_CHAT = "tab_chat";
    public const SUB_TAB_CHAT = "sub_tab_chat";
    public const SUB_TAB_CHAT_SETTINGS = "sub_tab_chat_settings";
    public const SUB_TAB_MEMBERS = "sub_tab_chat_members";

    public const USER_STATUS_UNKNOWN = "unknown";
    public const USER_STATUS_NO_INVITE = "notInvite";
    public const USER_STATUS_INVITE = "invite";
    public const USER_STATUS_JOIN = "join";
    public const USER_STATUS_LEAVE = "leave";
    public const USER_STATUS_BAN = "ban";
    public const USER_STATUS_QUEUE = "queue";

    private ilTabsGUI $tabs;
    private ilMatrixChatPlugin $plugin;
    private CourseSettingsRepository $courseSettingsRepo;
    private CourseSettings $courseSettings;
    private int $refId;
    private ilAccessHandler $access;
    private MatrixApi $matrixApi;
    private QueuedInvitesRepository $queuedInvitesRepo;
    private ilLanguage $lng;
    private Renderer $uiRenderer;
    private Factory $uiFactory;
    private \ILIAS\Refinery\Factory $refinery;
    private WrapperFactory $httpWrapper;
    private Services $http;
    private ilLogger $logger;
    private ilObjUser $user;

    public function __construct(Container $dic, ControllerHandler $controllerHandler)
    {
        parent::__construct($dic, $controllerHandler);
        $this->tabs = $this->dic->tabs();
        $this->plugin = ilMatrixChatPlugin::getInstance();
        $this->refId = (int) $this->controllerHandler->verifyQueryParameterExists("ref_id");
        $this->access = $this->dic->access();
        $this->matrixApi = $this->plugin->getMatrixApi();
        $this->lng = $this->dic->language();
        $this->uiRenderer = $this->dic->ui()->renderer();
        $this->uiFactory = $this->dic->ui()->factory();
        $this->httpWrapper = $this->dic->http()->wrapper();
        $this->refinery = $this->dic->refinery();
        $this->http = $this->dic->http();
        $this->logger = $this->dic->logger()->root();
        $this->user = $this->dic->user();

        $this->courseSettingsRepo = CourseSettingsRepository::getInstance($dic->database());
        $this->courseSettings = $this->courseSettingsRepo->read($this->refId);
        $this->queuedInvitesRepo = QueuedInvitesRepository::getInstance();
    }

    public function showChat(): void
    {
        $this->checkPermissionOnObject("read");
        $this->checkChatActivatedForObject();

        $this->injectTabs(self::TAB_CHAT, self::SUB_TAB_CHAT);

        $userConfig = (new UserConfig($this->user))->load();

        $room = null;
        $matrixRoomId = $this->courseSettings->getMatrixRoomId();
        if ($matrixRoomId) {
            $room = $this->matrixApi->getRoom($matrixRoomId);
        }

        if (!$matrixRoomId && $this->checkPermissionOnObject("write", false)) {
            $this->uiUtil->sendInfo(sprintf(
                $this->plugin->txt("matrix.room.notYetConfigured"),
                $this->lng->txt(ilObject::_lookupType($this->refId)),
                $this->getCommandLink(self::CMD_SHOW_CHAT_SETTINGS, [
                    "ref_id" => $this->courseSettings->getCourseId()
                ]),
                $this->plugin->txt("matrix.chat.settings")
            ), true);
        }

        /** @var LocalUserConfigController $localUserConfigController */
        $localUserConfigController = $this->controllerHandler->getController(LocalUserConfigController::class);

        /** @var ExternalUserConfigController $externalUserConfigController */
        $externalUserConfigController = $this->controllerHandler->getController(ExternalUserConfigController::class);

        $this->ctrl->clearParameterByClass(ilMatrixChatUIHookGUI::class, "ref_id");
        $this->ctrl->clearParameterByClass(ilUIPluginRouterGUI::class, "ref_id");

        $toChatSettingsButtonLink = (int) $this->user->getAuthMode(true) === ilAuthUtils::AUTH_LOCAL
            ? $localUserConfigController->getCommandLink(BaseUserConfigController::CMD_SHOW_USER_CHAT_CONFIG)
            : $externalUserConfigController->getCommandLink(BaseUserConfigController::CMD_SHOW_USER_CHAT_CONFIG);

        if ($userConfig->getMatrixUserId()) {
            $matrixUser = $this->matrixApi->getUser($userConfig->getMatrixUserId());
            if (!$matrixUser->isExists()) {
                $this->uiUtil->sendInfo($this->plugin->txt("matrix.user.account.unknown"), true);
            } elseif ($room) {
                if ($room->isMember($matrixUser)) {
                    $this->uiUtil->sendInfo(sprintf(
                        $this->plugin->txt("matrix.user.account.joined"),
                        $matrixUser->getId()
                    ), true);
                } else {
                    $this->uiUtil->sendInfo(sprintf(
                        $this->plugin->txt("matrix.user.account.invited"),
                        $matrixUser->getId()
                    ), true);
                }
            }
            $toChatSettingsButton = $this->uiFactory->button()->standard(
                $this->plugin->txt("matrix.user.account.changeMatrixAccountSettings"),
                $toChatSettingsButtonLink
            );
        } else {
            $this->uiUtil->sendInfo($this->plugin->txt("matrix.user.account.unconfigured"), true);
            $toChatSettingsButton = $this->uiFactory->button()->standard(
                $this->plugin->txt("matrix.user.account.setupMatrixAccount"),
                $toChatSettingsButtonLink
            );
        }

        $this->renderToMainTemplate($this->uiRenderer->render($toChatSettingsButton) . $this->plugin->getPluginConfig()->getPageDesignerText());
    }

    public function applyMemberTableFilter(): void
    {
        $table = new ChatMemberTable($this->refId, $this);
        $table->writeFilterToSession();
        $table->resetOffset();
        $this->showChatMembers();
    }

    public function resetMemberTableFilter(): void
    {
        $table = new ChatMemberTable($this->refId, $this);
        $table->resetOffset();
        $table->resetFilter();
        $this->showChatMembers();
    }

    public function showChatSettings(?ChatSettingsForm $form = null): void
    {
        $this->checkPermissionOnObject("write");
        $this->checkChatActivatedForObject();

        $this->injectTabs(self::TAB_CHAT, self::SUB_TAB_CHAT_SETTINGS);

        $matrixRoomId = $this->courseSettings->getMatrixRoomId();

        if (!$form) {
            $form = new ChatSettingsForm($this, $this->refId, $matrixRoomId);
        }

        $this->renderToMainTemplate($form->getHTML());
    }

    public function showChatMembers(): void
    {
        $this->checkPermissionOnObject("write");
        $this->checkChatActivatedForObject();

        $this->injectTabs(self::TAB_CHAT, self::SUB_TAB_MEMBERS);

        $table = new ChatMemberTable($this->refId, $this);

        $room = null;
        if ($this->courseSettings->getMatrixRoomId()) {
            $room = $this->matrixApi->getRoom($this->courseSettings->getMatrixRoomId());
        }

        if (!$room) {
            $this->uiUtil->sendFailure($this->plugin->txt("matrix.chat.room.notFound"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_SETTINGS, ["ref_id" => $this->refId]);
        }

        $table->setData($table->buildTableData($this->getChatMembers($room)));

        $this->renderToMainTemplate($table->getHTML());
    }

    public function inviteSelectedParticipants(): void
    {
        $this->checkPermissionOnObject("write");
        $this->checkChatActivatedForObject();

        if (ilObject::lookupOfflineStatus(ilObject::_lookupObjId($this->refId))) {
            $this->uiUtil->sendFailure($this->plugin->txt("matrix.chat.invite.notPossible.objectOffline"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        $userIds = $this->httpWrapper->post()->retrieve(
            "userId",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->listOf($this->refinery->kindlyTo()->int()),
                $this->refinery->always([])
            ])
        );

        if ($userIds === []) {
            $this->uiUtil->sendSuccess($this->plugin->txt("matrix.user.account.invite.multiple.success"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        $space = null;
        $room = null;

        if ($this->plugin->getPluginConfig()->getMatrixSpaceId()) {
            $space = $this->matrixApi->getSpace($this->plugin->getPluginConfig()->getMatrixSpaceId());
        } else {
            $this->uiUtil->sendFailure($this->plugin->txt("matrix.user.account.invite.multiple.failure"), true);
            $this->uiUtil->sendInfo($this->plugin->txt("config.space.status.disconnected"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        if (!$space) {
            $this->uiUtil->sendFailure($this->plugin->txt("matrix.user.account.invite.multiple.failure"), true);
            $this->uiUtil->sendInfo($this->plugin->txt("config.space.status.faulty"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        if ($this->courseSettings->getMatrixRoomId()) {
            $room = $this->matrixApi->getRoom($this->courseSettings->getMatrixRoomId());
        } else {
            $this->uiUtil->sendFailure($this->plugin->txt("matrix.user.account.invite.multiple.failure"), true);
            $this->uiUtil->sendInfo($this->plugin->txt("config.room.status.disconnected"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        if (!$room) {
            $this->uiUtil->sendFailure($this->plugin->txt("matrix.user.account.invite.multiple.failure"), true);
            $this->uiUtil->sendInfo($this->plugin->txt("config.room.status.faulty"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        $participants = ilParticipants::getInstance($this->refId);

        $inviteFailed = false;
        foreach ($userIds as $userId) {
            if (!ilParticipants::_isParticipant($this->refId, $userId)) {
                $this->logger->warning("Unable to invite user with id '$userId' to room '{$room->getId()}'. User is not a member of the Course/Group with id '{$this->refId}'");
                continue;
            }
            try {
                $user = new ilObjUser($userId);
            } catch (Throwable $ex) {
                $inviteFailed = true;
                $this->logger->warning("Unable to invite user with id '$userId', No User seems to exist with that id");
                continue;
            }

            $userConfig = (new UserConfig($user))->load();

            $matrixUser = null;
            if ($userConfig->getMatrixUserId()) {
                $matrixUser = $this->matrixApi->getUser($userConfig->getMatrixUserId());
            }

            if (!$matrixUser) {
                $inviteFailed = true;
                $this->logger->warning("Unable to get Matrix-User of ilias user with id '$userId'. Not configured or server problem");
                continue;
            }

            if (!$this->matrixApi->inviteUserToRoom($matrixUser, $space)) {
                $inviteFailed = true;
                $this->logger->warning("Inviting user '{$matrixUser->getId()}' to space '{$space->getId()}' failed.");
            }

            if (!$this->matrixApi->inviteUserToRoom($matrixUser, $room, $this->plugin->determinePowerLevelOfParticipant($participants, $user->getId()))) {
                $inviteFailed = true;
                $this->logger->warning("Inviting user '{$matrixUser->getId()}' to room '{$room->getId()}' failed.");
            }
        }

        if ($inviteFailed) {
            $this->uiUtil->sendFailure($this->plugin->txt("matrix.user.account.invite.multiple.failure"), true);
        } else {
            $this->uiUtil->sendSuccess($this->plugin->txt("matrix.user.account.invite.multiple.success"), true);
        }
        $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
    }

    public function inviteParticipant(): void
    {
        $this->checkPermissionOnObject("write");
        $this->checkChatActivatedForObject();

        $userId = $this->httpWrapper->query()->retrieve(
            "userId",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        if (!$userId) {
            $this->uiUtil->sendFailure(sprintf(
                $this->plugin->txt("general.plugin.requiredParameterMissing"),
                "userId"
            ), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        if (!ilParticipants::_isParticipant($this->refId, $userId)) {
            $this->uiUtil->sendFailure($this->plugin->txt("matrix.user.account.invite.failed.userNotMember"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        if (ilObject::lookupOfflineStatus(ilObject::_lookupObjId($this->refId))) {
            $this->uiUtil->sendFailure($this->plugin->txt("matrix.chat.invite.notPossible.objectOffline"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        $space = null;
        $room = null;

        if ($this->plugin->getPluginConfig()->getMatrixSpaceId()) {
            $space = $this->matrixApi->getSpace($this->plugin->getPluginConfig()->getMatrixSpaceId());
        } else {
            $this->uiUtil->sendFailure($this->plugin->txt("config.space.status.disconnected"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        if (!$space) {
            $this->uiUtil->sendFailure($this->plugin->txt("config.space.status.faulty"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        if ($this->courseSettings->getMatrixRoomId()) {
            $room = $this->matrixApi->getRoom($this->courseSettings->getMatrixRoomId());
        } else {
            $this->uiUtil->sendFailure($this->plugin->txt("config.room.status.disconnected"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        if (!$room) {
            $this->uiUtil->sendFailure($this->plugin->txt("config.room.status.faulty"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        try {
            $user = new ilObjUser($userId);
        } catch (Throwable $ex) {
            $this->uiUtil->sendFailure($this->plugin->txt("matrix.user.account.invite.failed"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
            return;
        }

        $userConfig = (new UserConfig($user))->load();

        $matrixUser = null;
        if ($userConfig->getMatrixUserId()) {
            $matrixUser = $this->matrixApi->getUser($userConfig->getMatrixUserId());
        }

        if (!$matrixUser) {
            $this->logger->info("Unable to invite user to room '{$room->getId()}'. User to be invited has not configured a matrix user yet.");
            $this->uiUtil->sendFailure($this->plugin->txt("matrix.user.account.invite.failed"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        $participants = ilParticipants::getInstance($this->refId);

        if (!$this->matrixApi->inviteUserToRoom($matrixUser, $space)) {
            $this->uiUtil->sendFailure($this->plugin->txt("matrix.user.account.invite.failed"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        if (!$this->matrixApi->inviteUserToRoom($matrixUser, $room, $this->plugin->determinePowerLevelOfParticipant($participants, $user->getId()))) {
            $this->uiUtil->sendFailure($this->plugin->txt("matrix.user.account.invite.failed"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        $this->uiUtil->sendSuccess($this->plugin->txt("matrix.user.account.invite.success"), true);
        $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
    }

    /** @return ChatMember[] */
    protected function getChatMembers(MatrixRoom $room): array
    {
        $chatMembers = [];
        $participants = ilParticipants::getInstance($this->courseSettings->getCourseId());
        $isCrs = $participants instanceof ilCourseParticipants;
        foreach ($participants->getParticipants() as $participantId) {
            $participantId = (int) $participantId;
            $user = new ilObjUser($participantId);
            $userConfig = (new UserConfig($user))->load();

            $roleText = $this->lng->txt($isCrs ? "il_crs_member" : "il_grp_member");
            if ($participants->isTutor($participantId)) {
                $roleText = $this->lng->txt("il_crs_tutor");
            }
            if ($participants->isAdmin($participantId)) {
                $roleText = $this->lng->txt($isCrs ? "il_crs_admin" : "il_grp_admin");
            }

            if ($room->isMember($userConfig->getMatrixUserId())) {
                $status = self::USER_STATUS_JOIN;
            } else {
                $status = $this->matrixApi->getStatusOfUserInRoom(
                    $room,
                    $userConfig->getMatrixUserId()
                );
            }

            if (in_array(
                $status,
                [
                    self::USER_STATUS_NO_INVITE,
                    self::USER_STATUS_LEAVE,
                ],
                true
            )
            ) {
                $userRoomAddQueue = $this->queuedInvitesRepo->read($user->getId(), $this->refId);
                if ($userRoomAddQueue) {
                    $status = self::USER_STATUS_QUEUE;
                }
            }


            $chatMembers[] = new ChatMember(
                $user->getId(),
                $user->getFullname(),
                $user->getLogin(),
                $roleText,
                $status,
                $userConfig->getMatrixUserId()
            );
        }
        return $chatMembers;
    }

    public function createRoom(): void
    {
        $this->checkPermissionOnObject("write");
        $this->checkChatActivatedForObject();

        $pluginConfig = $this->plugin->getPluginConfig();
        $courseSettings = $this->courseSettings;
        $form = new ChatSettingsForm($this, $this->refId);
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->showChatSettings($form);
        }

        $form->setValuesByPost();

        $matrixRoomId = $courseSettings->getMatrixRoomId();
        $matrixSpaceId = $pluginConfig->getMatrixSpaceId();
        $room = null;
        $space = null;

        if ($matrixRoomId) {
            $room = $this->matrixApi->getRoom($matrixRoomId);
        }

        if ($matrixSpaceId) {
            $space = $this->matrixApi->getSpace($matrixSpaceId);
        }

        if (!$space) {
            $this->uiUtil->sendFailure($this->plugin->txt("matrix.space.notFound"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_SETTINGS, ["ref_id" => $this->refId]);
        }

        if (!$room) {
            $room = $this->matrixApi->createRoom(
                $this->buildRoomPrefix($courseSettings->getCourseId()),
                $this->plugin->getPluginConfig()->isEnableRoomEncryption(),
                $space
            );
            if (!$room) {
                $this->uiUtil->sendFailure($this->plugin->txt("matrix.space.creation.failure"), true);
                $this->redirectToCommand(self::CMD_SHOW_CHAT_SETTINGS, ["ref_id" => $this->refId]);
            }

            $courseSettings->setMatrixRoomId($room->getId());
        }

        if ($room) {
            $participants = ilParticipants::getInstance($courseSettings->getCourseId());
            $matrixUserPowerLevelMap = [];

            if (!ilObject::lookupOfflineStatus(ilObject::_lookupObjId($courseSettings->getCourseId()))) {
                foreach ($participants->getParticipants() as $participantId) {
                    $participantId = (int) $participantId;
                    $userConfig = (new UserConfig(new ilObjUser($participantId)))->load();

                    if (!$userConfig->getMatrixUserId()) {
                        continue;
                    }

                    $matrixUser = $this->matrixApi->getUser($userConfig->getMatrixUserId());

                    if (!$this->matrixApi->inviteUserToRoom($matrixUser, $space)) {
                        $this->logger->warning(sprintf(
                            "Inviting matrix-user '%s' to space '%s' failed.",
                            $matrixUser->getId(),
                            $space->getId()
                        ));
                    }
                    if (!$this->matrixApi->inviteUserToRoom($matrixUser, $room, $this->plugin->determinePowerLevelOfParticipant($participants, $participantId))) {
                        $this->logger->warning(sprintf(
                            "Inviting matrix-user '%s' to room '%s' failed.",
                            $matrixUser->getId(),
                            $room->getId()
                        ));
                    }

                    $matrixUserPowerLevelMap[] = new MatrixUserPowerLevel(
                        $matrixUser->getId(),
                        $this->plugin->determinePowerLevelOfParticipant($participants, $participantId)
                    );
                }

                $this->matrixApi->setUserPowerLevelOnRoom($room, $matrixUserPowerLevelMap);
            }
        }
        try {
            $this->courseSettingsRepo->save($courseSettings);
        } catch (Exception $ex) {
            $this->uiUtil->sendFailure($this->plugin->txt("general.update.failed"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_SETTINGS, ["ref_id" => $this->refId]);
        }

        $this->uiUtil->sendSuccess($this->plugin->txt("general.update.success"), true);
        $this->redirectToCommand(self::CMD_SHOW_CHAT_SETTINGS, ["ref_id" => $this->refId]);
    }

    protected function buildRoomPrefix(int $objRefId): string
    {
        $objTitle = ilObject::_lookupTitle(ilObject::_lookupObjId($objRefId));
        $roomPrefix = $this->plugin->getPluginConfig()->getRoomPrefix();

        foreach ($this->plugin->getRoomSchemeVariables() as $key => $value) {
            $roomPrefix = str_replace("{" . $key . "}", $value, $roomPrefix);
        }
        return $roomPrefix . $objTitle;
    }

    public function showConfirmDeleteRoom(?ConfirmDeleteRoomForm $form = null): void
    {
        $this->checkPermissionOnObject("write");
        $this->checkChatActivatedForObject();

        if (!$form) {
            $form = new ConfirmDeleteRoomForm($this, $this->refId);
        }
        $this->mainTpl->setContent($form->getHTML());
        $this->mainTpl->printToStdOut();
    }

    public function deleteRoom(): void
    {
        $this->checkPermissionOnObject("write");
        $this->checkChatActivatedForObject();

        $form = new ConfirmDeleteRoomForm($this, $this->refId);
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->showConfirmDeleteRoom($form);
            return;
        }

        $form->setValuesByPost();

        $purge = (bool) $form->getInput("purge");
        $block = (bool) $form->getInput("block");

        $room = $this->matrixApi->getRoom($this->courseSettings->getMatrixRoomId());

        if (!$room) {
            $this->courseSettings->setMatrixRoomId(null);
            if ($this->courseSettingsRepo->save($this->courseSettings)) {
                $this->uiUtil->sendSuccess(
                    $this->plugin->txt("matrix.chat.room.delete.success"),
                    true
                );
                $this->redirectToCommand(
                    self::CMD_SHOW_CHAT_SETTINGS,
                    ["ref_id" => $this->refId]
                );
            }
        }

        if ($room) {
            $deleteSuccess = $this->matrixApi->deleteRoom($room, "", $purge, $block);
            if ($deleteSuccess) {
                $this->courseSettings->setMatrixRoomId(null);
            }

            if ($this->courseSettingsRepo->save($this->courseSettings)) {
                if ($deleteSuccess) {
                    $this->uiUtil->sendSuccess($this->plugin->txt("matrix.chat.room.delete.success"), true);
                } else {
                    $this->uiUtil->sendFailure($this->plugin->txt("matrix.chat.room.delete.failed"), true);
                }
                $this->redirectToCommand(
                    self::CMD_SHOW_CHAT_SETTINGS,
                    ["ref_id" => $this->refId]
                );
            }
        }

        $this->uiUtil->sendFailure($this->plugin->txt("matrix.chat.room.delete.failed"), true);
        $this->redirectToCommand(self::CMD_SHOW_CHAT_SETTINGS, ["ref_id" => $this->refId]);
    }

    public function checkChatActivatedForObject(bool $redirectToInfoScreenOnFail = true): bool
    {
        $activated = in_array(
            ilObject::_lookupType($this->refId, true),
            $this->plugin->getPluginConfig()->getSupportedObjectTypes(),
            true
        );

        if (!$activated && $redirectToInfoScreenOnFail) {
            $this->uiUtil->sendFailure($this->lng->txt("permission_denied"), true);
            $this->redirectToInfoTab();
        }

        return $activated;
    }

    public function checkPermissionOnObject(string $permission, bool $redirectToInfoScreenOnFail = true): bool
    {
        $hasAccess = $this->access->checkAccess($permission, "", $this->refId);
        if (!$hasAccess && $redirectToInfoScreenOnFail) {
            $this->uiUtil->sendFailure($this->lng->txt("permission_denied"), true);
            $this->redirectToInfoTab();
        }

        return $hasAccess;
    }

    public function redirectToInfoTab(): void
    {
        $this->ctrl->setParameterByClass(ilRepositoryGUI::class, "ref_id", $this->refId);
        $this->ctrl->redirectByClass(ilRepositoryGUI::class, "view");
    }

    protected function injectTabs(string $activeTab = null, string $activeSubTab = null): void
    {
        $this->ctrl->setParameterByClass(ilUIPluginRouterGUI::class, "ref_id", $this->courseSettings->getCourseId());
        $gui = null;
        switch (ilObject::_lookupType($this->courseSettings->getCourseId(), true)) {
            case "crs":
                $gui = new ilObjCourseGUI([], $this->courseSettings->getCourseId(), true);
                $gui->prepareOutput();
                $gui->setSubTabs("properties");
                break;
            case "grp":
                $gui = new ilObjGroupGUI([], $this->courseSettings->getCourseId(), true);
                $gui->prepareOutput();
                $guiRefClass = new ReflectionClass($gui);
                $setSubTabsMethod = $guiRefClass->getMethod("setSubTabs");
                $setSubTabsMethod->setAccessible(true);
                $setSubTabsMethod->invoke($gui, "settings");
                break;
        }

        if ($gui) {
            $reflectionMethod = new ReflectionMethod($gui, "setTitleAndDescription");
            $reflectionMethod->setAccessible(true);
            $reflectionMethod->invoke($gui);
        }

        if ($this->checkChatActivatedForObject()) {
            $this->tabs->clearSubTabs();

            $this->tabs->addTab(
                self::TAB_CHAT,
                $this->plugin->txt("chat"),
                $this->getCommandLink(self::CMD_SHOW_CHAT, [
                    "ref_id" => $this->refId
                ])
            );
            $this->tabs->addSubTab(
                self::SUB_TAB_CHAT,
                $this->plugin->txt("chat"),
                $this->getCommandLink(self::CMD_SHOW_CHAT, [
                    "ref_id" => $this->refId
                ])
            );

            if ($this->checkPermissionOnObject("write", false)) {
                $this->tabs->addSubTab(
                    self::SUB_TAB_MEMBERS,
                    $this->plugin->txt("matrix.chat.members"),
                    $this->getCommandLink(self::CMD_SHOW_CHAT_MEMBERS, [
                        "ref_id" => $this->courseSettings->getCourseId()
                    ])
                );

                $this->tabs->addSubTab(
                    self::SUB_TAB_CHAT_SETTINGS,
                    $this->plugin->txt("matrix.chat.settings"),
                    $this->getCommandLink(self::CMD_SHOW_CHAT_SETTINGS, [
                        "ref_id" => $this->courseSettings->getCourseId()
                    ])
                );
            }

            $this->tabs->setForcePresentationOfSingleTab(true);
        }

        if ($activeTab) {
            $this->tabs->activateTab($activeTab);
        }

        if ($activeSubTab) {
            $this->tabs->activateSubTab($activeSubTab);
        }
    }

    public function getCtrlClassesForCommand(string $cmd): array
    {
        return [ilUIPluginRouterGUI::class, ilMatrixChatUIHookGUI::class];
    }
}
