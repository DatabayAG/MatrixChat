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

use ILIAS\Plugin\Libraries\ControllerHandler\UiUtils;
use ILIAS\Plugin\MatrixChatClient\Controller\ChatController;
use ilMatrixChatClientPlugin;
use ilPropertyFormGUI;

/**
 * Class ConfirmDeleteRoomForm
 *
 * @package ILIAS\Plugin\MatrixChatClient\Form
 * @author  Marvin Beym <mbeym@databay.de>
 */
class ConfirmDeleteRoomForm extends ilPropertyFormGUI
{
    private ilMatrixChatClientPlugin $plugin;
    private UiUtils $uiUtil;

    public function __construct(ChatController $controller, int $refId)
    {
        parent::__construct();
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
        $this->uiUtil = new UiUtils();
        $this->uiUtil->sendQuestion($this->plugin->txt("matrix.chat.room.delete.confirm"));

        $this->setFormAction($controller->getCommandLink(
            ChatController::CMD_SHOW_CHAT_SETTINGS,
            ["ref_id" => $refId],
            true
        ));

        $this->addCommandButton(
            ChatController::getCommand(ChatController::CMD_SHOW_CHAT_SETTINGS),
            $this->lng->txt("cancel")
        );
        $this->addCommandButton(
            ChatController::getCommand(ChatController::CMD_DELETE_ROOM),
            $this->lng->txt("confirm")
        );
    }
}
