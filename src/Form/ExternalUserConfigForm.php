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

use ILIAS\Plugin\MatrixChatClient\Controller\BaseUserConfigController;
use ILIAS\Plugin\MatrixChatClient\Controller\ExternalUserConfigController;
use ilObjUser;
use ilPasswordInputGUI;
use ilRadioOption;

class ExternalUserConfigForm extends BaseUserConfigForm
{
    private bool $passwordEnterNeeded;

    public function __construct(
        ExternalUserConfigController $controller,
        ilObjUser $user,
        ?string $matrixAccountId = null,
        ?string $selectedAccountOption = null,
        bool $passwordEnterNeeded = false
    ) {
        $this->passwordEnterNeeded = $passwordEnterNeeded;
        parent::__construct($controller, $user, $matrixAccountId, $selectedAccountOption);
    }

    protected function showCommandButton(bool $reset = false): void
    {
        if ($reset) {
            $this->addCommandButton(
                ExternalUserConfigController::getCommand(BaseUserConfigController::CMD_RESET_ACCOUNT_SETTINGS),
                $this->plugin->txt("config.user.resetAccountSettings")
            );
        } else {
            $this->addCommandButton(
                ExternalUserConfigController::getCommand(
                    BaseUserConfigController::CMD_SAVE_USER_CHAT_CONFIG
                ),
                $this->lng->txt("save")
            );
        }
    }

    public function getCreateOnConfiguredHomeserverOption(): ilRadioOption
    {
        $radioOption = new ilRadioOption(
            $this->plugin->txt("config.user.method.createOnConfiguredHomeserver"),
            PluginConfigForm::CREATE_ON_CONFIGURED_HOMESERVER
        );

        $radioOption->addSubItem($this->getConnectedMatrixHomeserverInput());
        $matrixAccountInput = $this->getMatrixAccountInput(
            "config.user.method.createOnConfiguredHomeserver.detectedMatrixAccount",
            "matrixUsername",
            true
        );
        $matrixAccountInput->setInfo($this->plugin->txt("matrix.user.name.info"));
        $radioOption->addSubItem($matrixAccountInput);

        if ($this->passwordEnterNeeded) {
            $matrixUserPassword = new ilPasswordInputGUI(
                $this->plugin->txt("matrix.user.password"),
                "matrixUserPassword"
            );
            $matrixUserPassword->setInfo($this->plugin->txt("matrix.user.password.external.required"));
            $matrixUserPassword->setRequired(true);

            $radioOption->addSubItem($matrixUserPassword);
        }

        return $radioOption;
    }

    public function getSpecifyOtherMatrixAccountOption(): ilRadioOption
    {
        $radioOption = new ilRadioOption(
            $this->plugin->txt("config.user.method.specifyOtherMatrixAccount"),
            PluginConfigForm::SPECIFY_OTHER_MATRIX_ACCOUNT
        );

        $radioOption->addSubItem($this->getMatrixAccountInput(
            "config.user.method.specifyOtherMatrixAccount.accountName",
            "matrixAccount"
        ));
        return $radioOption;
    }

    protected function onAuthenticated(string $selectedAccountOption): bool
    {
        $this->addItem($this->getConnectedMatrixHomeserverInput());
        $this->addItem($this->getMatrixAccountInput("matrix.user.account", "matrixAccount", true));

        $this->showCommandButton(true);
        return false;
    }
}
