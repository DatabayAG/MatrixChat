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

use ilFormSectionHeaderGUI;
use ilGlobalPageTemplate;
use ILIAS\DI\Container;
use ilMatrixChatClientConfigGUI;
use ilMatrixChatClientPlugin;
use ilNumberInputGUI;
use ilPasswordInputGUI;
use ilPropertyFormGUI;
use ilTextInputGUI;
use ilUriInputGUI;
use ilUtil;

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

        $matrixServerUrl = new ilUriInputGUI($this->plugin->txt("matrix.server.url"), "matrixServerUrl");
        $matrixServerUrl->setRequired(true);

        $matrixAdminUsername = new ilTextInputGUI(
            $this->plugin->txt("config.admin.username.title"),
            "matrixAdminUsername"
        );
        $matrixAdminUsername->setRequired(true);
        $matrixAdminUsername->setInfo($this->plugin->txt("config.admin.username.info"));

        $matrixAdminPassword = new ilPasswordInputGUI(
            $this->plugin->txt("config.admin.password.title"),
            "matrixAdminPassword"
        );
        $matrixAdminPassword->setInfo($this->plugin->txt("config.admin.password.info"));
        $matrixAdminPassword->setSkipSyntaxCheck(true);
        $matrixAdminPassword->setRetype(false);

        $sharedSecret = new ilPasswordInputGUI($this->plugin->txt("config.sharedSecret.title"), "sharedSecret");
        $sharedSecret->setInfo($this->plugin->txt("config.sharedSecret.info"));
        $sharedSecret->setSkipSyntaxCheck(true);
        $sharedSecret->setRetype(false);

        $chatInitialLoadLimit = new ilNumberInputGUI(
            $this->plugin->txt("config.loadLimit.initial.title"),
            "chatInitialLoadLimit"
        );
        $chatInitialLoadLimit->setRequired(true);
        $chatInitialLoadLimit->setInfo($this->plugin->txt("config.loadLimit.initial.info"));

        $chatHistoryLoadLimit = new ilNumberInputGUI(
            $this->plugin->txt("config.loadLimit.history.title"),
            "chatHistoryLoadLimit"
        );
        $chatHistoryLoadLimit->setRequired(true);
        $chatHistoryLoadLimit->setInfo($this->plugin->txt("config.loadLimit.history.info"));

        $usernameScheme = new ilTextInputGUI(
            $this->plugin->txt("config.usernameScheme.title"),
            "usernameScheme"
        );
        $usernameScheme->setRequired(true);

        $allowedCharacters = array_map(static function ($char) {
            return "<span style='color: blue; font-weight: bold'>$char</span>";
        }, ["a-z", "0-9", "=", "_", "-", ".", "/", "'"]);

        $usernameScheme->setInfo(sprintf(
            $this->plugin->txt("config.usernameScheme.info"),
            implode(", ", $allowedCharacters),
            "- " . implode("<br>- ", array_map(static function ($variable) : string {
                return "<span>{</span>$variable<span>}</span>";
            }, array_keys($this->plugin->getUsernameSchemeVariables())))
        ));

        $serverReachable = $this->plugin->getMatrixCommunicator()->general->serverReachable();
        $serverSection = new ilFormSectionHeaderGUI();
        if ($this->plugin->getMatrixCommunicator()->general->serverReachable()) {
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

        $chatSection = new ilFormSectionHeaderGUI();
        $chatSection->setTitle($this->plugin->txt("config.section.chat"));

        $userSection = new ilFormSectionHeaderGUI();
        $userSection->setTitle($this->plugin->txt("config.section.user"));

        $this->addItem($serverSection);
        $this->addItem($matrixServerUrl);
        $this->addItem($sharedSecret);

        $this->addItem($adminAuthenticationSection);
        $this->addItem($matrixAdminUsername);
        $this->addItem($matrixAdminPassword);

        $this->addItem($chatSection);
        $this->addItem($chatInitialLoadLimit);
        $this->addItem($chatHistoryLoadLimit);

        $this->addItem($userSection);
        $this->addItem($usernameScheme);
        $this->addCommandButton("saveSettings", $this->lng->txt("save"));
    }
}
