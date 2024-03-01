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

namespace ILIAS\Plugin\MatrixChat\Form;

use ilCheckboxInputGUI;
use ILIAS\Plugin\Libraries\ControllerHandler\UiUtils;
use ILIAS\Plugin\MatrixChat\Controller\ChatController;
use ilMatrixChatPlugin;
use ilPropertyFormGUI;

class ConfirmDeleteRoomForm extends ilPropertyFormGUI
{
    private ilMatrixChatPlugin $plugin;
    private UiUtils $uiUtil;

    public function __construct(ChatController $controller, int $refId)
    {
        parent::__construct();
        $this->plugin = ilMatrixChatPlugin::getInstance();
        $this->uiUtil = new UiUtils();
        $this->uiUtil->sendQuestion($this->plugin->txt("matrix.chat.room.delete.confirm"));

        $this->setFormAction($controller->getCommandLink(
            ChatController::CMD_SHOW_CHAT_SETTINGS,
            ["ref_id" => $refId],
            true
        ));

        $purge = new ilCheckboxInputGUI($this->plugin->txt("matrix.room.delete.purge"), "purge");
        $purge->setInfo($this->plugin->txt("matrix.room.delete.purge.info"));
        $this->addItem($purge);

        $block = new ilCheckboxInputGUI($this->plugin->txt("matrix.room.delete.block"), "block");
        $block->setInfo($this->plugin->txt("matrix.room.delete.block.info"));
        $this->addItem($block);

        $this->addCommandButton(
            ChatController::getCommand(ChatController::CMD_DELETE_ROOM),
            $this->lng->txt("confirm")
        );

        $this->addCommandButton(
            ChatController::getCommand(ChatController::CMD_SHOW_CHAT_SETTINGS),
            $this->lng->txt("cancel")
        );
    }
}
