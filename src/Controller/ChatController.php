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
use ILIAS\Plugin\LdapPasswordChange\Ui\UiUtils;
use ILIAS\Plugin\Libraries\ControllerHandler\BaseController;
use ILIAS\Plugin\Libraries\ControllerHandler\ControllerHandler;
use ILIAS\Plugin\MatrixChatClient\Form\ChatSettingsForm;
use ILIAS\Plugin\MatrixChatClient\Form\DisableChatIntegrationForm;
use ILIAS\Plugin\MatrixChatClient\Model\CourseSettings;
use ILIAS\Plugin\MatrixChatClient\Model\UserConfig;
use ILIAS\Plugin\MatrixChatClient\Repository\CourseSettingsRepository;
use ilInfoScreenGUI;
use ilMatrixChatClientPlugin;
use ilMatrixChatClientUIHookGUI;
use ilObjCourseGUI;
use ilObject;
use ilObjGroupGUI;
use ilObjUser;
use ilTabsGUI;
use ilUIPluginRouterGUI;
use ReflectionClass;
use ReflectionMethod;

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
    public const CMD_SHOW_CONFIRM_DISABLE_CHAT_INTEGRATION = "showConfirmDisableChatIntegration";
    public const CMD_DISABLE_CHAT_INTEGRATION = "disableChatIntegration";


    public const CMD_SAVE_CHAT_SETTINGS = "saveChatSettings";

    public const TAB_CHAT = "tab_chat";
    public const SUB_TAB_CHAT = "sub_tab_chat";

    public const SUB_TAB_CHAT_SETTINGS = "sub_tab_chat_settings";

    private ilTabsGUI $tabs;
    private ilMatrixChatClientPlugin $plugin;
    private CourseSettingsRepository $courseSettingsRepo;
    private CourseSettings $courseSettings;
    private int $refId;
    private ilAccessHandler $access;

    public function __construct(Container $dic, ControllerHandler $controllerHandler)
    {
        parent::__construct($dic, $controllerHandler);
        $this->tabs = $this->dic->tabs();
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
        $this->refId = (int) $this->controllerHandler->verifyQueryParameterExists("ref_id");
        $this->access = $this->dic->access();

        $this->courseSettingsRepo = CourseSettingsRepository::getInstance($dic->database());
        $this->courseSettings = $this->courseSettingsRepo->read(
            $this->refId
        );
    }

    public function showChat(): void
    {
        $this->checkPermissionOnObject("read");

        $this->injectTabs();

        $this->tabs->activateTab(self::TAB_CHAT);
        $this->tabs->activateSubTab(self::TAB_CHAT);

        $this->renderToMainTemplate($this->plugin->getPluginConfig()->getPageDesignerText());
    }
    public function showChatSettings(?ChatSettingsForm $form = null): void
    {
        $this->checkPermissionOnObject("write");

        $this->injectTabs();

        $this->tabs->activateTab(self::TAB_CHAT);
        $this->tabs->activateSubTab(self::SUB_TAB_CHAT_SETTINGS);

        if (!$form) {
            $form = new ChatSettingsForm($this, $this->refId);

            if (
                $this->courseSettings->isChatIntegrationEnabled()
                && !$this->courseSettings->getMatrixRoom()
            ) {
                $this->uiUtil->sendFailure($this->plugin->txt("matrix.chat.room.notFoundEvenThoughEnabled"), true);
            }

            $form->setValuesByArray([
                "chatIntegrationEnabled" => $this->courseSettings->isChatIntegrationEnabled(),
            ], true);
        }

        $this->renderToMainTemplate($form->getHTML());
    }

    public function saveChatSettings(): void
    {
        $this->checkPermissionOnObject("write");

        $form = new ChatSettingsForm($this, $this->refId);
        $courseSettings = $this->courseSettings;
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->showChatSettings($form);
        }

        $form->setValuesByPost();

        $enableChatIntegration = (bool) $form->getInput("chatIntegrationEnabled");

        $courseSettings->setChatIntegrationEnabled($enableChatIntegration);
/*
        $room = $courseSettings->getMatrixRoom();

        if ($enableChatIntegration && (!$room || !$room->exists())) {
            $room = $this->matrixApi->createRoom(ilObject::_lookupTitle(ilObject::_lookupObjId($courseSettings->getCourseId())));
            $courseSettings->setMatrixRoom($room);
        }

        if ($enableChatIntegration) {
            if ($room) {
                foreach ((ilCourseParticipants::getInstance($courseSettings->getCourseId()))->getParticipants() as $participantId) {
                    $participantId = (int) $participantId;
                    $userConfig = (new UserConfig(new ilObjUser($participantId)))->load();

                    if (!$userConfig->getMatrixUserId()) {
                        continue;
                    }

                    $matrixUser = $this->matrixApi->loginUserWithAdmin(
                        $participantId,
                        $userConfig->getMatrixUserId()
                    );
                    if (!$matrixUser) {
                        continue;
                    }

                    if (!$this->matrixApi->isUserMemberOfRoom($matrixUser, $room)) {
                        $this->matrixApi->addUserToRoom($matrixUser, $room);
                    }

                    $userRoomAddQueue = $this->userRoomAddQueueRepo->read($participantId,
                        $courseSettings->getCourseId());
                    if ($userRoomAddQueue) {
                        $this->userRoomAddQueueRepo->delete($userRoomAddQueue);
                    }
                }
            }
        }
*/
        try {
            $this->courseSettingsRepo->save($courseSettings);
        } catch (Exception $ex) {
            $this->uiUtil->sendFailure($this->plugin->txt("general.update.failed"), true);
            $this->redirectToCommand(self::CMD_SHOW_CHAT_SETTINGS);
        }
/*
        if (!$enableChatIntegration && $room->exists()) {
            $this->redirectToCommand(
                "confirmDisableCourseChatIntegration",
                ["ref_id" => $courseSettings->getCourseId()]
            );
        }
*/
        $this->uiUtil->sendSuccess($this->plugin->txt("general.update.success"), true);
        $this->redirectToCommand(self::CMD_SHOW_CONFIRM_DISABLE_CHAT_INTEGRATION);
    }

    public function showConfirmDisableChatIntegration(?DisableChatIntegrationForm $form = null): void
    {
        $this->checkPermissionOnObject("write");

        if (!$form) {
            $form = new DisableChatIntegrationForm($this, $this->refId);
        }
        $this->mainTpl->setContent($form->getHTML());
        $this->mainTpl->printToStdOut();
    }

    public function disableChatIntegration(): void
    {
        $this->checkPermissionOnObject("write");

        $form = new DisableChatIntegrationForm($this, $this->refId);
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->showConfirmDisableChatIntegration($form);
            return;
        }

        $form->setValuesByPost();
        $deleteRoom = (bool) $form->getInput("deleteChatRoom");

        if (!$deleteRoom) {
            $this->redirectToCommand(self::CMD_SHOW_CHAT_SETTINGS, [
                "ref_id" => $this->refId
            ]);
        }
/*
        if (
            $this->courseSettings->getMatrixRoom()
            && $this->courseSettings->getMatrixRoom()->exists()
            && $this->matrixApi->deleteRoom($this->courseSettings->getMatrixRoom())
        ) {
            $this->courseSettings->setMatrixRoom(null);
            if ($this->courseSettingsRepo->save($this->courseSettings)) {
                $this->uiUtil->sendSuccess($this->plugin->txt("matrix.chat.room.delete.success"), true);
                $this->redirectToCommand("showSettings", ["ref_id" => $this->courseSettings->getCourseId()]);
            }
        }*/

        $this->uiUtil->sendFailure($this->plugin->txt("matrix.chat.room.delete.failed"), true);
        $this->redirectToCommand(self::CMD_SHOW_CHAT_SETTINGS, ["ref_id" => $this->refId]);
    }

    public function checkPermissionOnObject(string $permission, bool $redirectToInfoScreenOnFail = true): bool
    {
        $hasAccess = $this->access->checkAccess($permission, "", $this->refId);
        if (!$hasAccess && $redirectToInfoScreenOnFail) {
            $this->uiUtil->sendFailure($this->dic->language()->txt("permission_denied"), true);
            $this->ctrl->setParameterByClass(ilInfoScreenGUI::class, "ref_id", $this->refId);
            $this->ctrl->redirectByClass(ilInfoScreenGUI::class, "showSummary");
            return false; //Never gets to here
        }

        return $hasAccess;
    }

    protected function injectTabs(): void
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

        $this->tabs->clearSubTabs();
        $this->tabs->addTab(
            self::TAB_CHAT,
            $this->plugin->txt("chat"),
            $this->getCommandLink(self::CMD_SHOW_CHAT, [
                "ref_id" => $this->refId
            ])
        );
        $this->tabs->addSubTab(
            self::TAB_CHAT,
            $this->plugin->txt("chat"),
            $this->getCommandLink(self::CMD_SHOW_CHAT, [
                "ref_id" => $this->refId
            ])
        );

        if ($this->checkPermissionOnObject("write", false)) {
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

    public function getCtrlClassesForCommand(string $cmd): array
    {
        return [ilUIPluginRouterGUI::class, ilMatrixChatClientUIHookGUI::class];
    }
}