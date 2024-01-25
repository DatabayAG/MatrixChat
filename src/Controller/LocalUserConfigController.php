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

use ILIAS\Plugin\MatrixChatClient\Form\BaseUserConfigForm;
use ILIAS\Plugin\MatrixChatClient\Form\LocalUserConfigForm;
use ILIAS\Plugin\MatrixChatClient\Form\LocalUserPasswordChangeForm;
use ILIAS\Plugin\MatrixChatClient\Form\PluginConfigForm;

/**
 * Class LocalUserConfigController
 *
 * @package ILIAS\Plugin\MatrixChatClient\Controller
 * @author  Marvin Beym <mbeym@databay.de>
 */
class LocalUserConfigController extends BaseUserConfigController
{
    public const CMD_SHOW_PASSWORD_CHANGE = "showPasswordChange";
    public const CMD_SAVE_PASSWORD_CHANGE = "savePasswordChange";

    public function showUserChatConfig(?BaseUserConfigForm $form = null): void
    {
        $this->verifyCorrectController();
        $this->injectTabs(self::TAB_USER_CHAT_CONFIG);
        $this->mainTpl->loadStandardTemplate();

        if (!$form) {
            $username = $this->buildUsername();

            $form = new LocalUserConfigForm(
                $this,
                $this->user,
                $this->userConfig->getMatrixUserId(),
                $this->userConfig->getAuthMethod(),
                $this->matrixApi->usernameAvailable($username)
            );

            $form->setValuesByArray(array_merge(
                $this->userConfig->toArray(),
                [
                    "connectedHomeserver" => $this->plugin->getPluginConfig()->getMatrixServerUrl(),
                    "matrixUsername" => $this->buildUsername(),
                    "matrixAccount" => $this->userConfig->getMatrixUserId()
                ]
            ), true);
        }

        $this->renderToMainTemplate($form->getHTML());
    }

    public function saveUserChatConfig(): void
    {
        $this->verifyCorrectController();
        $matrixUsername = $this->buildUsername();

        $usernameAvailable = $this->matrixApi->usernameAvailable($matrixUsername);
        $form = new LocalUserConfigForm(
            $this,
            $this->user,
            null,
            null,
            $usernameAvailable
        );

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->showUserChatConfig($form);
            return;
        }

        $form->setValuesByPost();

        $authMethod = $form->getInput("authMethod");
        $matrixUserPassword = $form->getInput("matrixUserPassword");

        $this->userConfig->setAuthMethod($form->getInput("authMethod"));

        if ($authMethod === PluginConfigForm::CREATE_ON_CONFIGURED_HOMESERVER) {
            if ($usernameAvailable) {
                //user needs to be created
                $matrixUser = $this->matrixApi->createUser(
                    $matrixUsername,
                    $matrixUserPassword,
                    $this->user->getFullname()
                );

                if (!$matrixUser) {
                    //Creation failed.
                    $this->uiUtil->sendFailure($this->plugin->txt("config.user.register.failure"), true);
                    $this->redirectToCommand(self::CMD_SHOW_USER_CHAT_CONFIG);
                }

                $this->uiUtil->sendSuccess($this->plugin->txt("config.user.register.success"), true);
            } else {
                $matrixUser = $this->matrixApi->loginUserWithAdmin($this->buildMatrixUserId());

                if (!$matrixUser) {
                    $this->uiUtil->sendFailure($this->plugin->txt("config.user.auth.failure"), true);
                    $this->redirectToCommand(self::CMD_SHOW_USER_CHAT_CONFIG);
                }

                $this->uiUtil->sendSuccess($this->plugin->txt("config.user.auth.success"), true);
            }

            $this->userConfig
                ->setMatrixUserId($matrixUser->getMatrixUserId())
                ->setMatrixUsername($matrixUsername)
                ->save();
        } else {
            $this->userConfig->setMatrixUserId($form->getInput("matrixAccount"))->save();
        }

        $this->redirectToCommand(self::CMD_SHOW_USER_CHAT_CONFIG);
    }

    public function showPasswordChange(?LocalUserPasswordChangeForm $form = null): void
    {
        $this->verifyCorrectController();

        $this->injectTabs(self::TAB_USER_CHAT_CONFIG);
        $this->mainTpl->loadStandardTemplate();

        if (!$form) {
            $form = new LocalUserPasswordChangeForm($this, $this->userConfig);
        }

        $this->renderToMainTemplate($form->getHTML());
    }

    public function savePasswordChange(): void
    {
        $this->verifyCorrectController();

        $form = new LocalUserPasswordChangeForm($this, $this->userConfig);

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->showPasswordChange($form);
            return;
        }

        $form->setValuesByPost();

        $newPassword = $form->getInput("newPassword");
        $matrixUserId = $this->userConfig->getMatrixUserId();

        if (!$this->matrixApi->userExists($matrixUserId)) {
            $this->uiUtil->sendFailure(
                $this->plugin->txt("config.user.changeLocalUserPassword.failure.userNotExist"),
                true
            );
            $this->redirectToCommand(self::CMD_SHOW_USER_CHAT_CONFIG);
        }

        if (!$this->matrixApi->changePassword($matrixUserId, $newPassword)) {
            $this->uiUtil->sendFailure($this->plugin->txt("config.user.changeLocalUserPassword.failure.general"), true);
            $this->redirectToCommand(self::CMD_SHOW_USER_CHAT_CONFIG);
        }

        $this->uiUtil->sendSuccess($this->plugin->txt("config.user.changeLocalUserPassword.success"), true);
        $this->redirectToCommand(self::CMD_SHOW_USER_CHAT_CONFIG);
    }

    public function buildUsername(): string
    {
        $username = $this->plugin->getPluginConfig()->getLocalUserScheme();
        foreach ($this->plugin->getUsernameSchemeVariables() as $key => $value) {
            $username = str_replace("{" . $key . "}", $value, $username);
        }
        return $username;
    }
}
