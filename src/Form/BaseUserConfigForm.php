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

namespace ILIAS\Plugin\MatrixChatClient\Form;

use ilGlobalPageTemplate;
use ILIAS\DI\Container;
use ILIAS\Plugin\MatrixChatClient\Controller\BaseUserConfigController;
use ilMatrixChatClientPlugin;
use ilObjUser;
use ilPropertyFormGUI;
use ilRadioGroupInputGUI;
use ilRadioOption;
use ilTextInputGUI;

abstract class BaseUserConfigForm extends ilPropertyFormGUI
{
    protected ilMatrixChatClientPlugin $plugin;
    protected ilGlobalPageTemplate $mainTpl;
    protected Container $dic;
    protected BaseUserConfigController $controller;

    public function __construct(
        BaseUserConfigController $controller,
        ilObjUser $user,
        ?string $matrixAccountId = null,
        ?string $selectedAccountOption = null
    ) {
        global $DIC;
        parent::__construct();
        $this->dic = $DIC;
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
        $this->mainTpl = $this->dic->ui()->mainTemplate();
        $this->mainTpl->addCss($this->plugin->cssFolder("userConfigForm.css"));
        $this->controller = $controller;

        $this->setTitle($this->plugin->txt("config.user.generalSettings"));
        $this->setFormAction($controller->getCommandLink(
            BaseUserConfigController::CMD_SHOW_USER_CHAT_CONFIG,
            [],
            true
        ));

        $this->mainTpl->addOnLoadCode(
            "window.userConfigFormConfig = " . json_encode([
                "actions" => [
                    "checkAccountOnMatrixServer" => $this->controller->getCommandLink(
                        BaseUserConfigController::AJAX_CMD_CHECK_EXTERNAL_ACCOUNT
                    ) . "&cmdMode=asynch"
                ],
                "translation" => [
                    "checkAccountOnMatrixServer" => $this->plugin->txt("config.user.externalMatrixUserLookup.checkAccountOnMatrixServer")
                ]
            ], JSON_THROW_ON_ERROR)
        );

        $this->mainTpl->addJavaScript($this->plugin->jsFolder("userConfigForm.js"));

        if ($matrixAccountId && !$this->onAuthenticated($selectedAccountOption)) {
            return;
        }

        $matrixAuthMethod = new ilRadioGroupInputGUI($this->plugin->txt("config.user.authMethod"), "authMethod");
        $matrixAuthMethod->setRequired(true);

        if (in_array(
            PluginConfigForm::CREATE_ON_CONFIGURED_HOMESERVER,
            $this instanceof LocalUserConfigForm
                ? $this->plugin->getPluginConfig()->getLocalUserOptions()
                : $this->plugin->getPluginConfig()->getExternalUserOptions(),
            true
        )) {
            $matrixAuthMethod->addOption($this->getCreateOnConfiguredHomeserverOption());
        }

        if (in_array(
            PluginConfigForm::SPECIFY_OTHER_MATRIX_ACCOUNT,
            $this instanceof LocalUserConfigForm
                ? $this->plugin->getPluginConfig()->getLocalUserOptions()
                : $this->plugin->getPluginConfig()->getExternalUserOptions(),
            true
        )) {
            $matrixAuthMethod->addOption($this->getSpecifyOtherMatrixAccountOption());
        }

        $this->addItem($matrixAuthMethod);

        $this->showCommandButton();
    }

    public function getConnectedMatrixHomeserverInput(): ilTextInputGUI
    {
        $connectedMatrixHomeserver = new ilTextInputGUI(
            $this->plugin->txt("config.user.method.createOnConfiguredHomeserver.connectedHomeserver"),
            "connectedHomeserver"
        );
        $connectedMatrixHomeserver->setDisabled(true);
        return $connectedMatrixHomeserver;
    }

    public function getMatrixAccountInput(
        string $translationKey,
        string $postVar,
        bool $disabled = false
    ): ilTextInputGUI {
        $matrixAccount = new ilTextInputGUI(
            $this->plugin->txt($translationKey),
            $postVar
        );
        $matrixAccount->setDisabled($disabled);
        $matrixAccount->setRequired(true);
        return $matrixAccount;
    }

    abstract protected function showCommandButton(bool $reset = false): void;

    abstract protected function getCreateOnConfiguredHomeserverOption(): ilRadioOption;

    abstract protected function getSpecifyOtherMatrixAccountOption(): ilRadioOption;

    abstract protected function onAuthenticated(string $selectedAccountOption): bool;
}
