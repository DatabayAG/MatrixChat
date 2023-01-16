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
use ilMatrixChatClientUIHookGUI;
use ILIAS\Plugin\MatrixChatClient\Form\UserConfigForm;
use ilUtil;
use ILIAS\DI\Container;
use ILIAS\Plugin\MatrixChatClient\Model\UserConfig;
use ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Exception\ConfigLoadException;
use ILIAS\Plugin\MatrixChatClient\Form\UserAuthenticateAccountForm;
use ILIAS\Plugin\MatrixChatClient\Form\UserRegisterAccountForm;
use ILIAS\Plugin\MatrixChatClient\Repository\UserDataRepository;
use ILIAS\Plugin\MatrixChatClient\Model\UserData;
use ilTextInputGUI;

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
    /**
     * @var UserDataRepository
     */
    private $userDataRepo;

    public function __construct(Container $dic)
    {
        parent::__construct($dic);

        $this->userConfig = (new UserConfig($this->dic->user()))->load();
        $this->userDataRepo = UserDataRepository::getInstance($this->dic->database());
    }

    private function showUserAuthenticatedNotification() : bool
    {
        $userData = $this->userDataRepo->read($this->dic->user()->getId());
        if ($userData) {
            if (!$this->matrixApi->admin->userExists($userData->getMatrixUserId())) {
                ilUtil::sendFailure($this->plugin->txt("matrix.user.authentication.failed.authFailed"), true);
            }
            ilUtil::sendSuccess(sprintf(
                $this->plugin->txt("matrix.user.authentication.success"),
                $userData->getMatrixUserId()
            ), true);
            return true;
        }

        ilUtil::sendFailure($this->plugin->txt("matrix.user.authentication.failed.unConfigured"), true);

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

        $this->userConfig->setAuthMethod($form->getInput("authMethod"));
        $this->userConfig->save();

        ilUtil::sendSuccess($this->plugin->txt("general.update.success"), true);
        $this->redirectToCommand("showGeneralConfig");
    }

    public function showLoginOrCreate(
        ?UserAuthenticateAccountForm $loginForm = null,
        ?UserRegisterAccountForm $registerForm = null
    ) : void {
        $this->injectTabs("chat-user-config");
        $this->tabs->activateSubTab("chat-user-config-loginOrCreate");
        $this->showUserAuthenticatedNotification();

        $this->mainTpl->loadStandardTemplate();

        if (!$loginForm) {
            $loginForm = new UserAuthenticateAccountForm();
        }

        if (!$registerForm) {
            $registerForm = new UserRegisterAccountForm();
        }

        $this->mainTpl->setContent($loginForm->getHTML() . $registerForm->getHTML());
        $this->mainTpl->printToStdOut();
    }

    public function saveLogin() : void
    {
        $form = new UserAuthenticateAccountForm();

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->showLoginOrCreate($form);
            return;
        }

        $form->setValuesByPost();

        $username = $form->getInput("loginUsername");
        $password = $form->getInput("loginPassword");

        $matrixUser = $this->matrixApi->admin->login($username, $password, "ilias_auth_verification");
        if (!$matrixUser) {
            ilUtil::sendFailure($this->plugin->txt("matrix.user.authentication.failed.authFailed"), true);
            $this->showLoginOrCreate($form);
            return;
        }

        $userData = $this->userDataRepo->read($this->dic->user()->getId());
        if ($userData) {
            $userData->setMatrixUserId($matrixUser->getMatrixUserId());
            $this->userDataRepo->update($userData);
        } else {
            $userData = new UserData(
                $this->dic->user()->getId(),
                $matrixUser->getMatrixUserId(),
                "ilias_auth_verification"
            );
            $this->userDataRepo->create($userData);
        }

        $this->redirectToCommand("showLoginOrCreate");
    }

    public function saveCreate() : void
    {
        $form = new UserRegisterAccountForm();

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->showLoginOrCreate(null, $form);
            return;
        }

        $form->setValuesByPost();

        $username = $form->getInput("registerUsername");
        $password = $form->getInput("registerPassword");

        if (!$this->matrixApi->admin->usernameAvailable($username)) {
            ilUtil::sendFailure(sprintf(
                $this->plugin->txt("matrix.user.authentication.failed.usernameTaken"),
                $username
            ), true);
            /**
             * @var ilTextInputGUI $password
             */
            $password = $form->getItemByPostVar("registerUsername");
            $form->setValuesByArray([
                "registerPassword" => ""
            ], true);
            $password->setAlert(sprintf(
                $this->plugin->txt("matrix.user.authentication.failed.usernameTaken"),
                $username
            ));
            $this->showLoginOrCreate(null, $form);
            return;
        }

        $matrixUser = $this->matrixApi->admin->createUser($username, $password, $this->dic->user()->getFullname());

        if (!$matrixUser) {
            ilUtil::sendFailure($this->plugin->txt("matrix.user.authentication.failed.userCreation"), true);
            $this->showLoginOrCreate(null, $form);
            return;
        }

        $userData = $this->userDataRepo->read($this->dic->user()->getId());
        if ($userData) {
            $userData->setMatrixUserId($matrixUser->getMatrixUserId());
            $this->userDataRepo->update($userData);
        } else {
            $userData = new UserData(
                $this->dic->user()->getId(),
                $matrixUser->getMatrixUserId(),
                "ilias_auth_verification"
            );
            $this->userDataRepo->create($userData);
        }

        $this->redirectToCommand("showLoginOrCreate");
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
                    "chat-user-config-loginOrCreate",
                    $this->plugin->txt("config.user.loginOrCreate.title"),
                    $this->getCommandLink("showLoginOrCreate")
                );
            }
        }
    }
}
