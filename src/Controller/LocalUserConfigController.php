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

namespace ILIAS\Plugin\MatrixChat\Controller;

use ILIAS\Plugin\MatrixChat\Form\BaseUserConfigForm;
use ILIAS\Plugin\MatrixChat\Form\LocalUserConfigForm;
use ILIAS\Plugin\MatrixChat\Form\LocalUserPasswordChangeForm;
use ILIAS\Plugin\MatrixChat\Form\PluginConfigForm;
use ILIAS\Plugin\MatrixChat\Model\MatrixUser;
use ILIAS\Plugin\MatrixChat\Model\MatrixUserHistory;

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
            $form = new LocalUserConfigForm(
                $this,
                $this->user,
                $this->userConfig->getMatrixUserId(),
                $this->userConfig->getAuthMethod(),
                !$this->matrixApi->usernameAvailable($this->buildUsername())
            );

            $form->setValuesByArray(array_merge(
                $this->userConfig->toArray(),
                [
                    "connectedHomeserver" => $this->plugin->getPluginConfig()->getMatrixServerUrl(),
                    "matrixUsername" => $this->buildUsername(),
                    "matrixAccount" => $this->userConfig->getMatrixUserId(),
                    "authMethod" => PluginConfigForm::CREATE_ON_CONFIGURED_HOMESERVER
                ]
            ), true);
        }

        $this->renderToMainTemplate($form->getHTML());
    }

    public function saveUserChatConfig(): void
    {
        $this->verifyCorrectController();

        $form = new LocalUserConfigForm(
            $this,
            $this->user,
            null,
            null,
            !$this->matrixApi->usernameAvailable($this->buildUsername())
        );

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->showUserChatConfig($form);
            return;
        }

        $form->setValuesByPost();

        $authMethod = $form->getInput("authMethod");

        $this->userConfig->setAuthMethod($form->getInput("authMethod"));

        if ($authMethod === PluginConfigForm::CREATE_ON_CONFIGURED_HOMESERVER) {
            $matrixUserId = $this->buildMatrixUserId();
        } else {
            $matrixUserId = $form->getInput("matrixAccount");
            $this->userConfig->setMatrixUserId($matrixUserId)->save();
        }

        $this->userConfig
            ->setMatrixUserId($matrixUserId)
            ->save();

        $this->uiUtil->sendSuccess($this->plugin->txt("config.user.save.success"), true);

        if (!$this->matrixUserHistoryRepo->create(new MatrixUserHistory($this->user->getId(), $matrixUserId))) {
            $this->logger->warning(sprintf(
                "Unable to create history entry for ilias user with id '%s' setting matrix user to '%s'",
                $this->user->getId(),
                $matrixUserId
            ));
        }


        $result = $this->processUserRoomAddQueue($this->user);
        if ($result) {
            $this->uiUtil->sendInfo($result, true);
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
