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

namespace ILIAS\Plugin\MatrixChatClient\Controller;

use ilUIPluginRouterGUI;
use ilPersonalSettingsGUI;
use ReflectionMethod;
use ReflectionException;
use ilMatrixChatClientUIHookGUI;
use ILIAS\Plugin\MatrixChatClient\Form\UserConfigForm;
use ilUtil;
use ILIAS\DI\Container;
use ILIAS\Plugin\MatrixChatClient\Model\UserConfig;
use ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Exception\ConfigLoadException;

/**
 * Class UserConfigController
 *
 * @package ILIAS\Plugin\MatrixChatClient\Controller
 * @author  Marvin Beym <mbeym@databay.de>
 */
class UserConfigController extends BaseController
{
    /**
     * @var UserConfig
     */
    private $userConfig;
    public function __construct(Container $dic)
    {
        parent::__construct($dic);

        $this->userConfig = (new UserConfig($this->dic->user()))->load();
    }

    public function showGeneralConfig(?UserConfigForm $form = null) : void
    {
        $this->injectTabs("chat-user-config");
        $this->tabs->activateSubTab("chat-user-config-general");

        $this->mainTpl->loadStandardTemplate();

        if (!$form) {
            $form = new UserConfigForm();
            $form->setValuesByArray($this->userConfig->toArray(), true);
        }

        $this->mainTpl->setContent($form->getHTML());
        $this->mainTpl->printToStdOut();
    }

    /**
     * @throws ConfigLoadException
     */
    public function saveGeneralConfig() : void
    {
        $form = new UserConfigForm();

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->showGeneralConfig($form);
            return;
        }

        $form->setValuesByPost();

        $this->userConfig->setAuthMethod($form->getInput("authMethod"));
        $this->userConfig->save();

        ilUtil::sendSuccess($this->plugin->txt("general.update.success"), true);
        $this->redirectToCommand("showGeneralConfig");
    }

    public function injectTabs(string $selectedTabId) : void
    {
        $gui = new ilPersonalSettingsGUI();
        $gui->__initSubTabs("showPersonalData");
        $gui->setHeader();

        $this->tabs->setForcePresentationOfSingleTab(true);

        $this->tabs->addTab(
            "chat-user-config",
            $this->plugin->txt("config.user.title"),
            $this->dic->ctrl()->getLinkTargetByClass([
                ilUIPluginRouterGUI::class,
                ilMatrixChatClientUIHookGUI::class,
            ], self::getCommand("showUserConfig"))
        );

        $this->tabs->activateTab($selectedTabId);

        if ($selectedTabId === "chat-user-config") {
            $this->tabs->addSubTab(
                "chat-user-config-general",
                $this->plugin->txt("config.user.general.title"),
                $this->getCommandLink("showGeneralConfig")
            );
        }
    }
}
