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

namespace ILIAS\Plugin\MatrixChatClient\Form;

use ilGlobalPageTemplate;
use ILIAS\DI\Container;
use ILIAS\Plugin\MatrixChatClient\Controller\BaseUserConfigController;
use ILIAS\Plugin\MatrixChatClient\Controller\LocalUserConfigController;
use ILIAS\Plugin\MatrixChatClient\Model\UserConfig;
use ilMatrixChatClientPlugin;
use ilPasswordInputGUI;
use ilPropertyFormGUI;
use ilTextInputGUI;

/**
 * Class LocalUserPasswordChangeForm
 *
 * @package ILIAS\Plugin\MatrixChatClient\Form
 * @author  Marvin Beym <mbeym@databay.de>
 */
class LocalUserPasswordChangeForm extends ilPropertyFormGUI
{
    protected ilMatrixChatClientPlugin $plugin;
    protected ilGlobalPageTemplate $mainTpl;
    protected Container $dic;
    protected BaseUserConfigController $controller;

    public function __construct(
        LocalUserConfigController $controller,
        UserConfig $userConfig
    ) {
        global $DIC;
        parent::__construct();
        $this->dic = $DIC;
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
        $this->mainTpl = $this->dic->ui()->mainTemplate();
        $this->controller = $controller;

        $this->setTitle($this->plugin->txt("config.user.changeLocalUserPassword.title"));
        $this->setFormAction($controller->getCommandLink(
            BaseUserConfigController::CMD_SHOW_USER_CHAT_CONFIG,
            [],
            true
        ));

        $matrixAccount = new ilTextInputGUI(
            $this->plugin->txt("matrixAccount"),
            "matrixAccount"
        );
        $matrixAccount->setDisabled(true);
        $matrixAccount->setRequired(true);
        $matrixAccount->setValue($userConfig->getMatrixUserId());
        $this->addItem($matrixAccount);

        $newPassword = new ilPasswordInputGUI($this->lng->txt("desired_password"), "newPassword");
        $newPassword->setRequired(true);
        $this->addItem($newPassword);

        $this->addCommandButton(
            LocalUserConfigController::getCommand(LocalUserConfigController::CMD_SAVE_PASSWORD_CHANGE),
            $this->lng->txt("save")
        );
    }
}
