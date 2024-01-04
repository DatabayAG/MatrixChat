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

;
use ilUIPluginRouterGUI;
use ilPersonalSettingsGUI;
use ilMatrixChatClientUIHookGUI;
use ILIAS\Plugin\MatrixChatClient\Form\UserConfigForm;
use ilUtil;
use ILIAS\DI\Container;
use ILIAS\Plugin\MatrixChatClient\Model\UserConfig;
use ILIAS\Plugin\MatrixChatClient\Form\UserAuthenticateAccountForm;
use ILIAS\Plugin\MatrixChatClient\Form\UserRegisterAccountForm;
use ilTextInputGUI;
use ilObjUser;

/**
 * Class UserConfigController
 *
 * @package ILIAS\Plugin\MatrixChatClient\Controller
 * @author  Marvin Beym <mbeym@databay.de>
 */
class UserConfigController extends BaseController
{
    private UserConfig $userConfig;
    private ilObjUser $user;
    private UiUtil $uiUtil;

    public function __construct(Container $dic)
    {
        parent::__construct($dic);

        $this->user = $this->dic->user();
        $this->uiUtil = new UiUtil();
        $this->userConfig = (new UserConfig($this->user))->load();
    }

    private function showUserAuthenticatedNotification() : bool
    {
        $matrixUserId = $this->userConfig->getMatrixUserId();
        if ($matrixUserId) {
            if (!$this->matrixApi->admin->userExists($matrixUserId)) {
                $this->uiUtil->sendFailure(
                    sprintf(
                        $this->plugin->txt("matrix.user.authentication.failed.authFailed"),
                        $matrixUserId
                    ),
                    true
                );
                return false;
            }
            $this->uiUtil->sendSuccess(sprintf(
                $this->plugin->txt("matrix.user.authentication.success"),
                $this->userConfig->getMatrixUsername(),
                $matrixUserId
            ), true);
            return true;
        }

        if ($this->userConfig->getAuthMethod() === "usingExternal") {
            $this->uiUtil->sendFailure(sprintf(
                $this->plugin->txt("matrix.user.authentication.failed.authFailed"),
                $matrixUserId
            ), true);
            return true;
        }

        $this->uiUtil->sendFailure($this->plugin->txt("matrix.user.authentication.failed.unConfigured"), true);
        return false;
    }

    public function showGeneralConfig(?UserConfigForm $form = null) : void
    {
        $this->injectTabs("chat-user-config");
        $this->tabs->activateSubTab("chat-user-config-general");
        $this->showUserAuthenticatedNotification();

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

        $this->userConfig
            ->setAuthMethod($form->getInput("authMethod"))
            ->setMatrixUserId("")
            ->setMatrixUsername("")
            ->save();

        $this->uiUtil->sendSuccess($this->plugin->txt("general.update.success"), true);
        $this->redirectToCommand("showGeneralConfig");
    }

    public function showLogin(?UserAuthenticateAccountForm $form = null) : void
    {
        $this->injectTabs("chat-user-config");
        $this->tabs->activateSubTab("chat-user-config-login");
        $this->showUserAuthenticatedNotification();

        $this->mainTpl->loadStandardTemplate();

        if (!$form) {
            $form = new UserAuthenticateAccountForm();
            $form->setValuesByArray([
                "username" => $this->userConfig->getMatrixUsername()
            ], true);
        }

        $this->mainTpl->setContent($form->getHTML());
        $this->mainTpl->printToStdOut();
    }

    public function showCreate(?UserRegisterAccountForm $form = null) : void
    {
        $this->injectTabs("chat-user-config");
        $this->tabs->activateSubTab("chat-user-config-create");
        $this->showUserAuthenticatedNotification();

        $this->mainTpl->loadStandardTemplate();

        if (!$form) {
            $form = new UserRegisterAccountForm();
        }

        $this->mainTpl->setContent($form->getHTML());
        $this->mainTpl->printToStdOut();
    }

    public function saveLogin() : void
    {
        $form = new UserAuthenticateAccountForm();

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->showLogin($form);
            return;
        }

        $form->setValuesByPost();

        $username = $form->getInput("username");
        $password = $form->getInput("password");

        $matrixUser = $this->matrixApi->admin->login($username, $password, "ilias_auth_verification");
        if (!$matrixUser) {
            $this->uiUtil->sendFailure($this->plugin->txt("matrix.user.authentication.failed.authFailed"), true);
            $this->showLogin($form);
            return;
        }

        $this->userConfig
            ->setMatrixUsername($username)
            ->setMatrixUserId($matrixUser->getMatrixUserId())
            ->save();

        $this->plugin->processUserRoomAddQueue($this->user);

        $this->redirectToCommand("showLogin");
    }

    public function saveCreate() : void
    {
        $form = new UserRegisterAccountForm();

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->showCreate($form);
            return;
        }

        $form->setValuesByPost();

        if ($this->user->getAuthMode() !== "local") {
            $username = $this->user->getLogin();
        } else {
            $username = $form->getInput("username");
        }
        $password = $form->getInput("password");

        $usernameSuffix = "_" . $this->plugin->getPluginConfig()->getUsernameScheme();
        foreach ($this->plugin->getUsernameSchemeVariables() as $key => $value) {
            $usernameSuffix = str_replace("{" . $key . "}", $value, $usernameSuffix);
        }
        /**
         * @var ilTextInputGUI $usernameInput
         */
        $usernameInput = $form->getItemByPostVar("username");

        $username .= $usernameSuffix;
        $username = strtolower($username);
        if (preg_match("/[^a-z0-9=_\-.\/']/i", $username)) {
            $this->uiUtil->sendFailure($this->lng->txt("form_input_not_valid"), true);
            $usernameInput->setAlert(
                $this->plugin->txt("config.user.create.username.illegalCharactersUsed")
            );

            $form->setValuesByArray([
                "registerPassword" => ""
            ], true);
            $this->showCreate($form);
            return;
        }

        if (!$this->matrixApi->admin->usernameAvailable($username)) {
            $this->uiUtil->sendFailure(sprintf(
                $this->plugin->txt("matrix.user.authentication.failed.usernameTaken"),
                $username
            ), true);

            $form->setValuesByArray([
                "registerPassword" => ""
            ], true);
            $usernameInput->setAlert(sprintf(
                $this->plugin->txt("matrix.user.authentication.failed.usernameTaken"),
                $username
            ));
            $this->showCreate($form);
            return;
        }

        $matrixUser = $this->matrixApi->admin->createUser($username, $password, $this->user->getFullname());

        if (!$matrixUser) {
            $this->uiUtil->sendFailure($this->plugin->txt("matrix.user.authentication.failed.userCreation"), true);
            $this->showCreate($form);
            return;
        }

        $this->userConfig
            ->setMatrixUsername($username)
            ->setMatrixUserId($matrixUser->getMatrixUserId())
            ->save();

        $this->plugin->processUserRoomAddQueue($this->user);

        $this->redirectToCommand("showCreate");
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
                $this->plugin->txt("config.user.generalSettings"),
                $this->getCommandLink("showGeneralConfig")
            );

            if ($this->userConfig->getAuthMethod() === "loginOrCreate") {
                $this->tabs->addSubTab(
                    "chat-user-config-login",
                    $this->plugin->txt("config.user.login"),
                    $this->getCommandLink("showLogin")
                );

                $this->tabs->addSubTab(
                    "chat-user-config-create",
                    $this->plugin->txt("config.user.create.title"),
                    $this->getCommandLink("showCreate")
                );
            }
        }
    }
}
