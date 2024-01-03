<?php

declare(strict_types=1);
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

namespace ILIAS\Plugin\MatrixChatClient\Form;

use ilCheckboxGroupInputGUI;
use ilCheckboxInputGUI;
use ilCheckboxOption;
use ilFormSectionHeaderGUI;
use ilGlobalPageTemplate;
use ILIAS\DI\Container;
use ilMatrixChatClientConfigGUI;
use ilMatrixChatClientPlugin;
use ilPasswordInputGUI;
use ilPropertyFormGUI;
use ilTextInputGUI;
use ilUriInputGUI;

/**
 * Class PluginConfigForm
 *
 * @package ILIAS\Plugin\MatrixChatClient\Form
 * @author  Marvin Beym <mbeym@databay.de>
 */
class PluginConfigForm extends ilPropertyFormGUI
{
    private ilMatrixChatClientPlugin $plugin;
    private Container $dic;
    private ilGlobalPageTemplate $mainTpl;

    public function __construct()
    {
        parent::__construct();
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
        $this->dic = $this->plugin->dic;
        $this->mainTpl = $this->dic->ui()->mainTemplate();
        $this->mainTpl->addCss($this->plugin->cssFolder("style.css"));

        $this->setFormAction($this->ctrl->getFormActionByClass(ilMatrixChatClientConfigGUI::class, "showSettings"));
        $this->setId("{$this->plugin->getId()}_{$this->plugin->getPluginName()}_plugin_config_form");
        $this->setTitle($this->plugin->txt("general.plugin.settings"));

        $serverReachable = $this->plugin->getMatrixCommunicator()->general->serverReachable();

        $allowedUsernameSchemeCharacters = array_map(static function ($char) {
            return "<span style='color: blue; font-weight: bold'>$char</span>";
        }, ["a-z", "0-9", "=", "_", "-", ".", "/", "'"]);

        $this->addServerSection($serverReachable);
        $this->addAdminUserSection($serverReachable);
        $this->addExternalUserSection($allowedUsernameSchemeCharacters);
        $this->addLocalUserSection($allowedUsernameSchemeCharacters);
        $this->addRoomSection();

        $this->addCommandButton("saveSettings", $this->lng->txt("save"));
    }

    protected function addServerSection($serverReachable): void
    {
        $serverSection = new ilFormSectionHeaderGUI();
        if ($serverReachable) {
            $serverSection->setTitle(sprintf(
                $this->plugin->txt("config.section.server.reachable"),
                $this->plugin->txt("matrix.server.reachable")
            ));
        } else {
            $serverSection->setTitle(sprintf(
                $this->plugin->txt("config.section.server.unreachable"),
                $this->plugin->txt("matrix.server.unreachable")
            ));
        }
        $this->addItem($serverSection);

        $matrixServerUrl = new ilUriInputGUI($this->plugin->txt("matrix.server.url"), "matrixServerUrl");
        $matrixServerUrl->setRequired(true);
        $this->addItem($matrixServerUrl);

        $sharedSecret = new ilPasswordInputGUI($this->plugin->txt("config.sharedSecret.title"), "sharedSecret");
        $sharedSecret->setInfo($this->plugin->txt("config.sharedSecret.info"));
        $sharedSecret->setSkipSyntaxCheck(true);
        $sharedSecret->setRetype(false);
        $this->addItem($sharedSecret);
    }

    protected function addAdminUserSection(bool $serverReachable): void
    {
        $adminAuthenticationSection = new ilFormSectionHeaderGUI();
        if ($this->plugin->getMatrixCommunicator()->admin->checkAdminUser()) {
            $adminAuthenticationSection->setTitle(sprintf(
                $this->plugin->txt("config.section.adminAuthentication.valid"),
                $this->plugin->txt("matrix.admin.login.valid")
            ));
        } else {
            if (!$serverReachable) {
                $adminAuthenticationSection->setTitle(sprintf(
                    $this->plugin->txt("config.section.adminAuthentication.invalid"),
                    $this->plugin->txt("matrix.server.unreachable")
                ));
            } else {
                $adminAuthenticationSection->setTitle(sprintf(
                    $this->plugin->txt("config.section.adminAuthentication.invalid"),
                    $this->plugin->txt("matrix.admin.login.invalid")
                ));
            }
        }
        $this->addItem($adminAuthenticationSection);

        $matrixAdminUsername = new ilTextInputGUI(
            $this->plugin->txt("config.admin.username.title"),
            "matrixAdminUsername"
        );
        $matrixAdminUsername->setRequired(true);
        $matrixAdminUsername->setInfo($this->plugin->txt("config.admin.username.info"));
        $this->addItem($matrixAdminUsername);

        $matrixAdminPassword = new ilPasswordInputGUI(
            $this->plugin->txt("config.admin.password.title"),
            "matrixAdminPassword"
        );
        $matrixAdminPassword->setInfo($this->plugin->txt("config.admin.password.info"));
        $matrixAdminPassword->setSkipSyntaxCheck(true);
        $matrixAdminPassword->setRetype(false);
        $this->addItem($matrixAdminPassword);
    }

    protected function addExternalUserSection(array $allowedCharacters): void
    {
        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->plugin->txt("config.section.user.external"));
        $this->addItem($section);

        $usernameScheme = new ilTextInputGUI(
            $this->plugin->txt("config.usernameScheme.external"),
            "externalUserScheme"
        );
        $usernameScheme->setRequired(true);

        $usernameScheme->setInfo(sprintf(
            $this->plugin->txt("config.usernameScheme.info"),
            implode(", ", $allowedCharacters),
            "- " . implode("<br>- ", array_map(static function ($variable): string {
                return "<span>{</span>$variable<span>}</span>";
            }, array_keys($this->plugin->getUsernameSchemeVariables())))
        ));
        $this->addItem($usernameScheme);

        $accountOptions = new ilCheckboxGroupInputGUI(
            $this->plugin->txt("config.accountOptions.title"),
            "externalUserAccountOptions"
        );

        $accountOptions->addOption(new ilCheckboxOption(
                $this->plugin->txt("config.accountOptions.specifyOtherMatrixAccount"),
                "externalUserSpecifyOtherMatrixAccount"
        ));
        $accountOptions->addOption(new ilCheckboxOption(
            $this->plugin->txt("config.accountOptions.createOnConfiguredHomeserver"),
            "externalUserCreateOnConfiguredHomeserver"
        ));

        $this->addItem($accountOptions);
    }

    protected function addLocalUserSection(array $allowedCharacters): void
    {
        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->plugin->txt("config.section.user.local"));
        $this->addItem($section);

        $usernameScheme = new ilTextInputGUI(
            $this->plugin->txt("config.usernameScheme.local"),
            "localUserScheme"
        );
        $usernameScheme->setRequired(true);

        $usernameScheme->setInfo(sprintf(
            $this->plugin->txt("config.usernameScheme.info"),
            implode(", ", $allowedCharacters),
            "- " . implode("<br>- ", array_map(static function ($variable): string {
                return "<span>{</span>$variable<span>}</span>";
            }, array_keys($this->plugin->getUsernameSchemeVariables())))
        ));
        $this->addItem($usernameScheme);

        $accountOptions = new ilCheckboxGroupInputGUI(
            $this->plugin->txt("config.accountOptions.title"),
            "localUserAccountOptions"
        );

        $accountOptions->addOption(new ilCheckboxOption(
            $this->plugin->txt("config.accountOptions.specifyOtherMatrixAccount"),
            "localUserSpecifyOtherMatrixAccount"
        ));
        $accountOptions->addOption(new ilCheckboxOption(
            $this->plugin->txt("config.accountOptions.createOnConfiguredHomeserver"),
            "localUserCreateOnConfiguredHomeserver"
        ));

        $this->addItem($accountOptions);
    }

    protected function addRoomSection(): void
    {
        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->plugin->txt("config.section.room"));
        $this->addItem($section);

        $roomPrefix = new ilTextInputGUI(
            $this->plugin->txt("config.room.prefix"),
            "roomPrefix"
        );
        $this->addItem($roomPrefix);
    }
}
