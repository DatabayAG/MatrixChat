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
use ILIAS\Plugin\MatrixChatClient\Form\ExternalUserConfigForm;
use ILIAS\Plugin\MatrixChatClient\Form\PluginConfigForm;

/**
 * Class ExternalUserConfigController
 *
 * @package ILIAS\Plugin\MatrixChatClient\Controller
 * @author  Marvin Beym <mbeym@databay.de>
 */
class ExternalUserConfigController extends BaseUserConfigController
{
    public function showUserChatConfig(?BaseUserConfigForm $form = null): void
    {
        $this->injectTabs(self::TAB_USER_CHAT_CONFIG);
        $this->mainTpl->loadStandardTemplate();

        if (!$form) {
            $form = new ExternalUserConfigForm(
                $this,
                $this->user,
                $this->userConfig->getMatrixUserId(),
                $this->userConfig->getAuthMethod(),
                !$this->matrixApi->userExists($this->buildMatrixUserId())
            );

            $form->setValuesByArray(array_merge(
                $this->userConfig->toArray(),
                [
                    "connectedHomeserver" => $this->plugin->getPluginConfig()->getMatrixServerUrl(),
                    "matrixUsername" => $this->buildMatrixUserId(),
                    "matrixAccount" => $this->userConfig->getMatrixUserId()
                ]
            ), true);
        }

        $this->renderToMainTemplate($form->getHTML());
    }

    public function saveUserChatConfig(): void
    {
        $form = new ExternalUserConfigForm(
            $this,
            $this->user,
            null,
            null,
            !$this->matrixApi->userExists($this->buildMatrixUserId())
        );

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->showUserChatConfig($form);
            return;
        }

        $form->setValuesByPost();

        $authMethod = $form->getInput("authMethod");
        $matrixUserPassword = $form->getInput("matrixUserPassword");
        $matrixUserId = $this->buildMatrixUserId();
        $this->userConfig->setAuthMethod($form->getInput("authMethod"));

        if ($authMethod === PluginConfigForm::CREATE_ON_CONFIGURED_HOMESERVER) {
            if ($this->matrixApi->userExists($matrixUserId)) {
                $matrixUser = $this->matrixApi->loginUserWithAdmin($this->user->getId(), $matrixUserId);

                if (!$matrixUser) {
                    //Logging into user failed, even though it exists (should never happen)
                    $this->uiUtil->sendFailure($this->plugin->txt("config.user.auth.failure"), true);
                    $this->redirectToCommand(self::CMD_SHOW_USER_CHAT_CONFIG);
                }

                $this->userConfig
                    ->setMatrixUserId($matrixUser->getMatrixUserId())
                    ->save();

                $this->uiUtil->sendSuccess($this->plugin->txt("config.user.auth.success"), true);
                $this->redirectToCommand(self::CMD_SHOW_USER_CHAT_CONFIG);
            } else {
                //Register new account
                $username = $this->buildUsername();

                if (!$this->matrixApi->usernameAvailable($username)) {
                    //Should only ever happen if a user was register in the same moment this code ran (unlikely)
                    $this->uiUtil->sendFailure($this->plugin->txt("config.user.register.failure.usernameAlreadyUsed"));
                    $this->redirectToCommand(self::CMD_SHOW_USER_CHAT_CONFIG);
                }

                if (!$matrixUserPassword) {
                    //Missing password (should never happen as form check should already fail before
                    $this->uiUtil->sendFailure($this->plugin->txt("config.user.register.failure"), true);
                    $this->redirectToCommand(self::CMD_SHOW_USER_CHAT_CONFIG);
                }

                $matrixUser = $this->matrixApi->createUser(
                    $username,
                    $matrixUserPassword,
                    $this->user->getFullname()
                );

                if (!$matrixUser) {
                    //Creation failed, unknown cause.
                    $this->uiUtil->sendFailure($this->plugin->txt("config.user.register.failure"), true);
                    $this->redirectToCommand(self::CMD_SHOW_USER_CHAT_CONFIG);
                }

                $this->userConfig
                    ->setMatrixUserId($matrixUser->getMatrixUserId())
                    ->save();

                $this->uiUtil->sendSuccess($this->plugin->txt("config.user.register.success"), true);
                $this->redirectToCommand(self::CMD_SHOW_USER_CHAT_CONFIG);
            }
        } else {
            $this->userConfig->setMatrixUserId($form->getInput("matrixAccount"))->save();
        }
    }

    public function buildUsername(): string
    {
        $username = $this->plugin->getPluginConfig()->getExternalUserScheme();
        foreach ($this->plugin->getUsernameSchemeVariables() as $key => $value) {
            $username = str_replace("{" . $key . "}", $value, $username);
        }
        return $username;
    }
}