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

use Exception;
use ilAccessHandler;
use ilCourseParticipants;
use ILIAS\DI\Container;
use ILIAS\HTTP\Services;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Plugin\Libraries\ControllerHandler\BaseController;
use ILIAS\Plugin\Libraries\ControllerHandler\ControllerHandler;
use ILIAS\Plugin\MatrixChatClient\Api\MatrixApi;
use ILIAS\Plugin\MatrixChatClient\Form\ChatSettingsForm;
use ILIAS\Plugin\MatrixChatClient\Form\ConfirmDeleteRoomForm;
use ILIAS\Plugin\MatrixChatClient\Model\ChatMember;
use ILIAS\Plugin\MatrixChatClient\Model\CourseSettings;
use ILIAS\Plugin\MatrixChatClient\Model\MatrixRoom;
use ILIAS\Plugin\MatrixChatClient\Model\MatrixUserPowerLevel;
use ILIAS\Plugin\MatrixChatClient\Model\UserConfig;
use ILIAS\Plugin\MatrixChatClient\Repository\CourseSettingsRepository;
use ILIAS\Plugin\MatrixChatClient\Repository\UserRoomAddQueueRepository;
use ILIAS\Plugin\MatrixChatClient\Table\ChatMemberTable;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ilLanguage;
use ilLogger;
use ilMatrixChatClientPlugin;
use ilMatrixChatClientUIHookGUI;
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

/**
 * Class ChatController
 *
 * @package ILIAS\Plugin\MatrixChatClient\Controller
 * @author  Marvin Beym <mbeym@databay.de>
 */
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

    public const USER_STATUS_NO_INVITE = "notInvite";
    public const USER_STATUS_INVITE = "invite";
    public const USER_STATUS_JOIN = "join";
    public const USER_STATUS_LEAVE = "leave";
    public const USER_STATUS_BAN = "ban";
    public const USER_STATUS_QUEUE = "queue";

    private ilTabsGUI $tabs;
    private ilMatrixChatClientPlugin $plugin;
    private CourseSettingsRepository $courseSettingsRepo;
    private CourseSettings $courseSettings;
    private int $refId;
    private ilAccessHandler $access;
    private MatrixApi $matrixApi;
    private UserRoomAddQueueRepository $userRoomAddQueueRepo;
    private ilLanguage $lng;
    private Renderer $uiRenderer;
    private Factory $uiFactory;
    private \ILIAS\Refinery\Factory $refinery;
    private WrapperFactory $httpWrapper;
    private Services $http;
    private ilLogger $logger;

    public function __construct(Container $dic, ControllerHandler $controllerHandler)
    {
        parent::__construct($dic, $controllerHandler);
        $this->tabs = $this->dic->tabs();
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
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

        $this->courseSettingsRepo = CourseSettingsRepository::getInstance($dic->database());
        $this->courseSettings = $this->courseSettingsRepo->read($this->refId);
        $this->userRoomAddQueueRepo = UserRoomAddQueueRepository::getInstance();
    }

    public function showChat(): void
    {
        $this->checkPermissionOnObject("read");
        $this->checkChatActivatedForObject();

        $this->injectTabs(self::TAB_CHAT, self::SUB_TAB_CHAT);

        $userConfig = (new UserConfig($this->dic->user()))->load();

        $room = null;
        $matrixRoomId = $this->courseSettings->getMatrixRoomId();
        if ($matrixRoomId) {
            $room = $this->matrixApi->getRoom($matrixRoomId);
        }

        $uiRenderer = $this->dic->ui()->renderer();
        $uiFactory = $this->dic->ui()->factory();

        /**
         * @var LocalUserConfigController $localUserConfigController
         */
        $localUserConfigController = $this->controllerHandler->getController(LocalUserConfigController::class);

        /**
         * @var ExternalUserConfigController $externalUserConfigController
         */
        $externalUserConfigController = $this->controllerHandler->getController(ExternalUserConfigController::class);

        $toChatSettingsButtonLink = $this->dic->user()->getAuthMode() === "local"
            ? $localUserConfigController->getCommandLink(BaseUserConfigController::CMD_SHOW_USER_CHAT_CONFIG)
            : $externalUserConfigController->getCommandLink(BaseUserConfigController::CMD_SHOW_USER_CHAT_CONFIG);

        if ($userConfig->getMatrixUserId()) {
            $matrixUser = $this->matrixApi->getUser($userConfig->getMatrixUserId());
            if (!$matrixUser) {
                $this->uiUtil->sendInfo($this->plugin->txt("matrix.user.account.unknown"), true);
            } elseif ($room) {
                if ($room->isMember($matrixUser)) {
                    $this->uiUtil->sendInfo(sprintf(
                        $this->plugin->txt("matrix.user.account.joined"),
                        $matrixUser->getMatrixUserId()
                    ), true);
                }
                $this->uiUtil->sendInfo(sprintf(
                    $this->plugin->txt("matrix.user.account.invited"),
                    $matrixUser->getMatrixUserId()
                ), true);
            }
            $toChatSettingsButton = $uiFactory->button()->standard(
                $this->plugin->txt("matrix.user.account.changeMatrixAccountSettings"),
                $toChatSettingsButtonLink
            );
        } else {
            $this->uiUtil->sendInfo($this->plugin->txt("matrix.chat.user.account.unconfigured"), true);
            $toChatSettingsButton = $uiFactory->button()->standard(
                $this->plugin->txt("matrix.user.account.setupMatrixAccount"),
                $toChatSettingsButtonLink
            );
        }

        $this->renderToMainTemplate($uiRenderer->render($toChatSettingsButton) . $this->plugin->getPluginConfig()->getPageDesignerText());
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

        $userIds = $this->httpWrapper->post()->retrieve(
            'userId',
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->listOf($this->refinery->kindlyTo()->int()),
                $this->refinery->always([])
            ])
        );

        if ($userIds === []) {
            $this->uiUtil->sendSuccess($this->plugin->txt("matrix.user.account.invite.multiple.success"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        $room = null;
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

        $inviteFailed = false;
        foreach ($userIds as $userId) {
            try {
                $user = new ilObjUser($userId);
            } catch (Throwable $ex) {
                $inviteFailed = true;
                $this->logger->error("Unable to invite user with id '$userId', Not User seems to exist with that id");
                continue;
            }

            $userConfig = (new UserConfig($user))->load();

            $matrixUser = null;
            if ($userConfig->getMatrixUserId()) {
                $matrixUser = $this->matrixApi->getUser($userConfig->getMatrixUserId());
            }

            if (!$matrixUser) {
                $inviteFailed = true;
                $this->logger->error("Unable to get Matrix-User of ilias user with id '$userId'. Not configured or server problem");
                continue;
            }

            if (!$this->matrixApi->inviteUserToRoom($matrixUser, $room)) {
                $inviteFailed = true;
                $this->logger->error("Inviting user '{$matrixUser->getMatrixUserId()}' to room '{$room->getId()}' failed.");
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
            'userId',
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        if (!$userId) {
            $this->uiUtil->sendFailure($this->plugin->txt("general.plugin.requiredParameterMissing"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }


        $room = null;
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
            $this->uiUtil->sendFailure($this->plugin->txt("matrix.user.account.unconfigured"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        if (!$this->matrixApi->inviteUserToRoom($matrixUser, $room)) {
            $this->uiUtil->sendFailure($this->plugin->txt("matrix.user.account.invite.failed"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
        }

        $this->uiUtil->sendSuccess($this->plugin->txt("matrix.user.account.invite.success"), true);
        $this->redirectToCommand(self::CMD_SHOW_CHAT_MEMBERS, ["ref_id" => $this->refId]);
    }

    /**
     * @return ChatMember[]
     */
    protected function getChatMembers(MatrixRoom $room): array
    {
        $chatMembers = [];
        $participants = ilCourseParticipants::getInstance($this->courseSettings->getCourseId());

        foreach ($participants->getParticipants() as $participantId) {
            $participantId = (int) $participantId;
            $user = new ilObjUser($participantId);
            $userConfig = (new UserConfig($user))->load();

            $roleText = $this->lng->txt("il_crs_member");
            if ($participants->isTutor($participantId)) {
                $roleText = $this->lng->txt("il_crs_tutor");
            }
            if ($participants->isAdmin($participantId)) {
                $roleText = $this->lng->txt("il_crs_admin");
            }

            $userRoomAddQueue = $this->userRoomAddQueueRepo->read($user->getId(), $this->refId);
            if ($userRoomAddQueue) {
                $status = "queue";
            } else {
                $status = $this->matrixApi->getStatusOfUserInRoom($room, $userConfig->getMatrixUserId()) ?: "notInvite";
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
                $this->plugin->getPluginConfig()->getRoomPrefix()
                . ilObject::_lookupTitle(ilObject::_lookupObjId($courseSettings->getCourseId())),
                $this->plugin->getPluginConfig()->isEnableRoomEncryption(),
                $space
            );

            $courseSettings->setMatrixRoomId($room->getId());
        }

        if ($room) {
            //ilCourseParticipants won't work for Groups.
            $participants = ilCourseParticipants::getInstance($courseSettings->getCourseId());
            $matrixUserPowerLevelMap = [];
            foreach ($participants->getParticipants() as $participantId) {
                $participantId = (int) $participantId;
                $userConfig = (new UserConfig(new ilObjUser($participantId)))->load();

                if (!$userConfig->getMatrixUserId()) {
                    continue;
                }

                $matrixUser = $this->matrixApi->getUser($userConfig->getMatrixUserId());

                if (!$matrixUser) {
                    continue;
                }

                $this->matrixApi->inviteUserToRoom($matrixUser, $room);
                $this->matrixApi->inviteUserToRoom($matrixUser, $space);


                $matrixUserPowerLevelMap[] = new MatrixUserPowerLevel(
                    $matrixUser->getMatrixUserId(),
                    $this->determinePowerLevelOfParticipant($participants, $participantId)
                );

                $userRoomAddQueue = $this->userRoomAddQueueRepo->read($participantId, $courseSettings->getCourseId());
                if ($userRoomAddQueue) {
                    //Remove user from queue when already on list
                    $this->userRoomAddQueueRepo->delete($userRoomAddQueue);
                }
            }

            $this->matrixApi->setUserPowerLevelOnRoom($room, $matrixUserPowerLevelMap);
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

    protected function determinePowerLevelOfParticipant(ilParticipants $participants, int $participantId): int
    {
        $pluginConfig = $this->plugin->getPluginConfig();
        $powerLevel = $pluginConfig->isModifyParticipantPowerLevel() ? $pluginConfig->getMemberPowerLevel() : 0;
        if ($participants->isTutor($participantId)) {
            $powerLevel = $pluginConfig->isModifyParticipantPowerLevel() ? $pluginConfig->getTutorPowerLevel() : 50;
        }
        if ($participants->isAdmin($participantId)) {
            $powerLevel = $pluginConfig->isModifyParticipantPowerLevel() ? $pluginConfig->getAdminPowerLevel() : 100;
        }
        return $powerLevel;
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
            $deleteSuccess = $this->matrixApi->deleteRoom($room);
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
            $this->plugin->getPluginConfig()->getActivateChat(),
            true
        );

        if (!$activated && $redirectToInfoScreenOnFail) {
            $this->uiUtil->sendFailure($this->dic->language()->txt("permission_denied"), true);
            $this->redirectToInfoTab();
            return false; //Never gets to here
        }

        return $activated;
    }

    public function checkPermissionOnObject(string $permission, bool $redirectToInfoScreenOnFail = true): bool
    {
        $hasAccess = $this->access->checkAccess($permission, "", $this->refId);
        if (!$hasAccess && $redirectToInfoScreenOnFail) {
            $this->uiUtil->sendFailure($this->dic->language()->txt("permission_denied"), true);
            $this->redirectToInfoTab();
            return false; //Never gets to here
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
            $reflectionMethod = new ReflectionMethod($gui, 'setTitleAndDescription');
            $reflectionMethod->setAccessible(true);
            $reflectionMethod->invoke($gui);

            $this->dic['ilLocator']->addRepositoryItems($this->courseSettings->getCourseId());
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
                    $this->plugin->txt("matrix.chat.course.members"),
                    $this->getCommandLink(self::CMD_SHOW_CHAT_MEMBERS, [
                        "ref_id" => $this->courseSettings->getCourseId()
                    ])
                );

                $this->tabs->addSubTab(
                    self::SUB_TAB_CHAT_SETTINGS,
                    $this->plugin->txt("matrix.chat.course.settings"),
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
        return [ilUIPluginRouterGUI::class, ilMatrixChatClientUIHookGUI::class];
    }
}
