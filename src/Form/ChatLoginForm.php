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
use ilPasswordInputGUI;
use ilTextInputGUI;
use ILIAS\Plugin\MatrixChatClient\Controller\ChatClientController;
use ilMatrixChatClientPlugin;

/**
 * Class ChatLoginForm
 *
 * @package ILIAS\Plugin\MatrixChatClient\Form
 * @author  Marvin Beym <mbeym@databay.de>
 */
class ChatLoginForm extends ilPropertyFormGUI
{
    public function __construct()
    {
        parent::__construct();
        global $DIC;
        $query = $DIC->http()->request()->getQueryParams();

        $this->setTitle(ilMatrixChatClientPlugin::getInstance()->txt("matrix.admin.auth.login"));
        $this->setFormAction(ChatClientController::getInstance()->getCommandLink(
            "showChatLogin",
            ["ref_id" => $query["ref_id"]],
            true
        ));

        $username = new ilTextInputGUI($this->lng->txt("login"), "username");
        $username->setRequired(true);
        $this->addItem($username);


        $password = new ilPasswordInputGUI($this->lng->txt("password"), "password");
        $password->setRetype(false);
        $password->setRequired(true);
        $password->setSkipSyntaxCheck(true);
        $this->addItem($password);

        $this->addCommandButton(ChatClientController::getCommand("saveChatLogin"), $this->lng->txt("log_in"));
    }
}
