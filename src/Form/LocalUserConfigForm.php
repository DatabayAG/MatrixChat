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
use ILIAS\Plugin\MatrixChatClient\Controller\LocalUserConfigController;
use ilObjUser;
use ilPasswordInputGUI;
use ilRadioOption;

/**
 * Class LocalAuthModeRegisterForm
 *
 * @package ILIAS\Plugin\MatrixChatClient\Form
 * @author  Marvin Beym <mbeym@databay.de>
 */
class LocalUserConfigForm extends BaseUserConfigForm
{
    public function __construct(
        LocalUserConfigController $controller,
        ilObjUser $user,
        ?string $matrixAccountId = null,
        ?string $selectedAccountOption = null
    ) {
        parent::__construct($controller, $user, $matrixAccountId, $selectedAccountOption);
    }

    protected function showCommandButton(bool $reset = false): void
    {
        if ($reset) {
            $this->addCommandButton(
                LocalUserConfigController::getCommand(BaseUserConfigController::CMD_RESET_ACCOUNT_SETTINGS),
                $this->plugin->txt("config.user.resetAccountSettings")
            );
        } else {
            $this->addCommandButton(LocalUserConfigController::getCommand(
                BaseUserConfigController::CMD_SAVE_USER_CHAT_CONFIG),
                $this->lng->txt("save")
            );
        }
    }

    public function getCreateOnConfiguredHomeserverOption(): ilRadioOption
    {
        $radioOption = new ilRadioOption(
            $this->plugin->txt("config.user.method.createOnConfiguredHomeserver"),
            "createOnConfiguredHomeserver"
        );


        $radioOption->addSubItem($this->getConnectedMatrixHomeserverInput());
        $radioOption->addSubItem($this->getMatrixAccountInput(
            "matrix.user.name",
            "matrixUsername",
            true
        ));

        $matrixUserPassword = new ilPasswordInputGUI(
            $this->plugin->txt("matrix.user.password"),
            "matrixUserPassword"
        );
        $matrixUserPassword->setRequired(true);
        $matrixUserPassword->setRetype(false);

        $radioOption->addSubItem($matrixUserPassword);

        return $radioOption;
    }

    public function getSpecifyOtherMatrixAccountOption(): ilRadioOption
    {
        $radioOption = new ilRadioOption(
            $this->plugin->txt("config.user.method.specifyOtherMatrixAccount"),
            "specifyOtherMatrixAccount"
        );


        $radioOption->addSubItem($this->getMatrixAccountInput(
            "config.user.method.specifyOtherMatrixAccount.accountName",
            "matrixAccount"
        ));
        return $radioOption;
    }

    protected function onAuthenticated(): bool
    {
        $this->addItem($this->getConnectedMatrixHomeserverInput());
        $this->addItem($this->getMatrixAccountInput("matrix.user.account", "matrixAccount", true));

        $this->showCommandButton(true);
        $this->addCommandButton(LocalUserConfigController::getCommand(
            LocalUserConfigController::CMD_SHOW_PASSWORD_CHANGE),
            $this->plugin->txt("config.user.changeLocalUserPassword.title")
        );
        return false;
    }
}
