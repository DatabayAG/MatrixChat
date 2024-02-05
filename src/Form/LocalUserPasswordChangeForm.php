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

namespace ILIAS\Plugin\MatrixChat\Form;

use ilGlobalPageTemplate;
use ILIAS\DI\Container;
use ILIAS\Plugin\MatrixChat\Controller\BaseUserConfigController;
use ILIAS\Plugin\MatrixChat\Controller\LocalUserConfigController;
use ILIAS\Plugin\MatrixChat\Model\UserConfig;
use ilMatrixChatPlugin;
use ilPasswordInputGUI;
use ilPropertyFormGUI;
use ilTextInputGUI;

class LocalUserPasswordChangeForm extends ilPropertyFormGUI
{
    protected ilMatrixChatPlugin $plugin;
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
        $this->plugin = ilMatrixChatPlugin::getInstance();
        $this->mainTpl = $this->dic->ui()->mainTemplate();
        $this->controller = $controller;

        $this->setTitle($this->plugin->txt("config.user.changeLocalUserPassword.title"));
        $this->setFormAction($controller->getCommandLink(
            BaseUserConfigController::CMD_SHOW_USER_CHAT_CONFIG,
            [],
            true
        ));

        $matrixAccount = new ilTextInputGUI(
            $this->plugin->txt("matrix.user.account"),
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
