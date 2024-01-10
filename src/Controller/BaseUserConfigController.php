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

use ILIAS\DI\Container;
use ILIAS\Plugin\Libraries\ControllerHandler\BaseController;
use ILIAS\Plugin\Libraries\ControllerHandler\ControllerHandler;
use ILIAS\Plugin\MatrixChatClient\Api\MatrixApiCommunicator;
use ILIAS\Plugin\MatrixChatClient\Form\BaseUserConfigForm;
use ILIAS\Plugin\MatrixChatClient\Model\UserConfig;
use ilMatrixChatClientPlugin;
use ilMatrixChatClientUIHookGUI;
use ilObjUser;
use ilPersonalSettingsGUI;
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


    protected UserConfig $userConfig;
    protected ilObjUser $user;
    protected ilTabsGUI $tabs;
    protected ilMatrixChatClientPlugin $plugin;
    protected MatrixApiCommunicator $matrixApi;

    public function __construct(Container $dic, ControllerHandler $controllerHandler)
    {
        parent::__construct($dic, $controllerHandler);

        $this->user = $this->dic->user();
        $this->tabs = $this->dic->tabs();
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
        $this->userConfig = (new UserConfig($this->user))->load();
        $this->matrixApi = $this->plugin->getMatrixCommunicator();
    }

    abstract public function showUserChatConfig(?BaseUserConfigForm $form = null): void;

    abstract public function saveUserChatConfig(): void;

    public function resetAccountSettings(): void
    {
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

    public function getCtrlClassesForCommand(string $cmd): array
    {
        return [ilUIPluginRouterGUI::class, ilMatrixChatClientUIHookGUI::class];
    }
}