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

use ilPropertyFormGUI;
use ilMatrixChatClientPlugin;
use ilGlobalPageTemplate;
use ILIAS\DI\Container;
use ILIAS\Plugin\MatrixChatClient\Controller\UserConfigController;
use ilPasswordInputGUI;
use ILIAS\Plugin\MatrixChatClient\Form\Input\SuffixedTextInput;

/**
 * Class UserRegisterAccountForm
 *
 * @package ILIAS\Plugin\MatrixChatClient\Form
 * @author  Marvin Beym <mbeym@databay.de>
 */
class UserRegisterAccountForm extends ilPropertyFormGUI
{
    private ilMatrixChatClientPlugin $plugin;
    private Container $dic;
    private ilGlobalPageTemplate $mainTpl;

    public function __construct()
    {
        global $DIC;
        parent::__construct();
        $this->dic = $DIC;
        $this->mainTpl = $this->dic->ui()->mainTemplate();
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
        $this->setTitle($this->plugin->txt("config.user.create.title"));
        $this->setFormAction(UserConfigController::getInstance()->getCommandLink("showCreate", [], true));

        $username = new SuffixedTextInput($this->lng->txt("username"), "username");

        if ($this->user->getAuthMode() !== "local") {
            $username->setValue($this->user->getLogin());
            $username->setDisabled(true);
            $username->setInfo($this->plugin->txt("config.user.create.username.idm_info"));
        }

        $usernameSuffix = "_" . $this->plugin->getPluginConfig()->getUsernameScheme();
        foreach ($this->plugin->getUsernameSchemeVariables() as $key => $value) {
            $usernameSuffix = str_replace("{" . $key . "}", $value, $usernameSuffix);
        }

        $allowedCharacters = array_map(static function ($char) : string {
            return "<span style='color: blue; font-weight: bold'>$char</span>";
        }, ["a-z", "0-9", "=", "_", "-", ".", "/", "'"]);

        $username->setSuffix($usernameSuffix);

        $username->setInfo($username->getInfo() . sprintf(
            $this->plugin->txt("config.user.create.username.info"),
            $usernameSuffix,
            implode(", ", $allowedCharacters),
        ));
        $username->setRequired(true);

        $password = new ilPasswordInputGUI($this->lng->txt("password"), "password");
        $password->setSkipSyntaxCheck(true);
        $password->setRequired(true);

        $this->addItem($username);
        $this->addItem($password);

        $this->addCommandButton(UserConfigController::getCommand("saveCreate"), $this->lng->txt("register"));
    }
}
