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

use ilAccessHandler;
use ILIAS\DI\Container;
use ILIAS\Plugin\LdapPasswordChange\Ui\UiUtils;
use ILIAS\Plugin\Libraries\ControllerHandler\BaseController;
use ILIAS\Plugin\Libraries\ControllerHandler\ControllerHandler;
use ILIAS\Plugin\MatrixChatClient\Model\CourseSettings;
use ILIAS\Plugin\MatrixChatClient\Repository\CourseSettingsRepository;
use ilInfoScreenGUI;
use ilMatrixChatClientPlugin;
use ilMatrixChatClientUIHookGUI;
use ilObjCourseGUI;
use ilObject;
use ilObjGroupGUI;
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
    public const CMD_SAVE_CHAT_SETTINGS = "saveChatSettings";

    public const TAB_CHAT = "tab_chat";
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
        $this->tabs->clearSubTabs();
        $this->tabs->addTab(
            self::TAB_CHAT,
            $this->plugin->txt("chat"),
            $this->getCommandLink(self::CMD_SHOW_CHAT, [
                "ref_id" => $this->refId
            ])
        );

        if (!$this->checkPermissionOnObject("write", false)) {
            $this->tabs->addSubTab(
                self::SUB_TAB_CHAT_SETTINGS,
                $this->plugin->txt("matrix.chat.course.settings"),
                $this->getCommandLink(self::CMD_SHOW_CHAT_SETTINGS, [
                    "ref_id" => $this->courseSettings->getCourseId()
                ])
            );
        }


        $this->tabs->activateTab(self::TAB_CHAT);

        $this->renderToMainTemplate("");
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

        $this->tabs->setForcePresentationOfSingleTab(true);
    }

    public function getCtrlClassesForCommand(string $cmd): array
    {
        return [ilUIPluginRouterGUI::class, ilMatrixChatClientUIHookGUI::class];
    }
}