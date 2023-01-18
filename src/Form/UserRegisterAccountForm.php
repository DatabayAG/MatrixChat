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
use ilTextInputGUI;
use ilPasswordInputGUI;

/**
 * Class UserRegisterAccountForm
 *
 * @package ILIAS\Plugin\MatrixChatClient\Form
 * @author  Marvin Beym <mbeym@databay.de>
 */
class UserRegisterAccountForm extends ilPropertyFormGUI
{
    /**
     * @var ilMatrixChatClientPlugin
     */
    private $plugin;
    /**
     * @var Container
     */
    private $dic;

    public function __construct()
    {
        global $DIC;
        parent::__construct();
        $this->dic = $DIC;
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
        $this->setTitle($this->plugin->txt("config.user.create"));
        $this->setFormAction(UserConfigController::getInstance()->getCommandLink("showCreate", [], true));

        $username = new ilTextInputGUI($this->lng->txt("username"), "username");
        $username->setRequired(true);

        $password = new ilPasswordInputGUI($this->lng->txt("password"), "password");
        $password->setSkipSyntaxCheck(true);
        $password->setRequired(true);

        $this->addItem($username);
        $this->addItem($password);

        $this->addCommandButton(UserConfigController::getCommand("saveCreate"), $this->lng->txt("register"));
    }
}
