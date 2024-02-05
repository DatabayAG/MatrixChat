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

use ILIAS\DI\Container;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Plugin\Libraries\ControllerHandler\UiUtils;
use ILIAS\Plugin\MatrixChatClient\Api\MatrixApi;
use ILIAS\Plugin\MatrixChatClient\Controller\ChatController;
use ilMatrixChatClientPlugin;
use ilPropertyFormGUI;
use ilTextInputGUI;

class ChatSettingsForm extends ilPropertyFormGUI
{
    private ilMatrixChatClientPlugin $plugin;
    private Container $dic;
    private UiUtils $uiUtil;
    private WrapperFactory $httpWrapper;
    private MatrixApi $matrixApi;

    public function __construct(ChatController $controller, int $refId, string $matrixRoomId = null)
    {
        parent::__construct();
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
        global $DIC;
        $this->dic = $DIC;
        $this->uiUtil = new UiUtils();
        $this->httpWrapper = $this->dic->http()->wrapper();
        $this->matrixApi = $this->plugin->getMatrixApi();

        $this->setTitle($this->plugin->txt("matrix.chat.settings"));

        $this->setFormAction($controller->getCommandLink(
            ChatController::CMD_SHOW_CHAT_SETTINGS,
            ["ref_id" => $refId],
            true
        ));

        $room = null;
        if ($matrixRoomId) {
            $room = $this->matrixApi->getRoom($matrixRoomId);
        }

        $roomStatus = new ilTextInputGUI($this->plugin->txt("config.room.status.title"));
        $roomStatus->setInfo($this->plugin->txt("config.room.status.info"));
        $roomStatus->setDisabled(true);
        $this->addItem($roomStatus);

        if (!$matrixRoomId) {
            $roomStatus->setValue($this->plugin->txt("config.room.status.disconnected"));

            $this->addCommandButton(
                ChatController::getCommand(ChatController::CMD_CREATE_ROOM),
                $this->plugin->txt("config.room.create")
            );
        } else {
            $this->addCommandButton(
                ChatController::getCommand(ChatController::CMD_SHOW_CONFIRM_DELETE_ROOM),
                $this->plugin->txt("config.room.delete")
            );
        }

        if ($matrixRoomId && !$room) {
            $roomStatus->setValue($this->plugin->txt("config.room.status.faulty"));
        }

        if ($room) {
            if (!$room->isMember($this->matrixApi->getRestApiUser())) {
                $roomStatus->setValue($this->plugin->txt("config.room.status.restApiUserMissingInRoom"));
            } else {
                $roomStatus->setValue($this->plugin->txt("config.room.status.connected"));
            }
        }

        $roomName = new ilTextInputGUI($this->plugin->txt("config.room.name"));
        $roomName->setDisabled(true);
        $roomName->setValue("");
        if ($matrixRoomId && $room) {
            $roomName->setValue($room->getName());
        }
        $this->addItem($roomName);
    }
}
