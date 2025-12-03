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

use ILIAS\DI\Container;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Plugin\ExportCertificates\Enum\PluginAsset;
use ILIAS\Plugin\MatrixChat\Api\MatrixApi;
use ILIAS\Plugin\MatrixChat\Controller\ChatController;
use ILIAS\Plugin\MatrixChat\Enum\RoomCreationLocation;
use ILIAS\Plugin\MatrixChat\Enum\SpaceSelection;
use ILIAS\Plugin\MatrixChat\Utils\UiUtil;
use ilMatrixChatPlugin;
use ilPropertyFormGUI;
use ilRadioGroupInputGUI;
use ilRadioOption;
use ilTextInputGUI;

class ChatSettingsForm extends ilPropertyFormGUI
{
    private readonly ilMatrixChatPlugin $plugin;
    private readonly Container $dic;
    private readonly UiUtil $uiUtil;
    private readonly WrapperFactory $httpWrapper;
    private readonly MatrixApi $matrixApi;

    public function __construct(
        private readonly ChatController $controller,
        int                             $refId,
        ?string                         $matrixRoomId = null,
        ?string                         $matrixSpaceName = null
    )
    {
        parent::__construct();
        $this->plugin = ilMatrixChatPlugin::getInstance();
        global $DIC;
        $this->dic = $DIC;
        $this->uiUtil = new UiUtil();
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


        if ($matrixRoomId && $room) {
            $roomName = new ilTextInputGUI($this->plugin->txt("config.room.name"));
            $roomName->setDisabled(true);
            $roomName->setValue($room->getName());
            $this->addItem($roomName);
        }

        $this->addItem($this->buildRoomCreationLocationInput($matrixRoomId, $matrixSpaceName));
    }

    private function buildRoomCreationLocationInput(?string $matrixRoomId, ?string $matrixSpaceName): ilRadioGroupInputGUI
    {
        $roomCreationLocation = new ilRadioGroupInputGUI(
            $this->plugin->txt("config.room.creationLocation.title"),
            "roomCreationLocation"
        );
        $roomCreationLocation->setRequired(true);

        $spaceOption = new ilRadioOption(
            $this->plugin->txt("config.room.creationLocation.space"),
            RoomCreationLocation::SPACE->value
        );

        $spaceSelection = new ilRadioGroupInputGUI(
            $this->plugin->txt("config.room.creationLocation.space.selection"),
            "spaceSelection"
        );
        $spaceSelection->setRequired(true);


        $spaceSelection->addOption(new ilRadioOption(
            $this->plugin->txt("config.room.creationLocation.space.selection.general.title")
            . ($matrixSpaceName ? " ($matrixSpaceName)" : ""),
            SpaceSelection::GENERAL->value
        ));

        $customSpaceOption = new ilRadioOption(
            $this->plugin->txt("config.room.creationLocation.space.selection.custom.title"),
            SpaceSelection::CUSTOM->value
        );

        $spaceSelection->addOption($customSpaceOption);

        $this->dic->ui()->mainTemplate()->addJavaScript(
            $this->plugin->assetsFile(PluginAsset::JS, "longInputInfoAutocompleteFix.js")
        );
        $customSpaceTitle = new ilTextInputGUI(
            $this->plugin->txt("config.room.creationLocation.space.selection.custom.customSpaceTitle.title"),
            "customSpaceTitle"
        );
        $customSpaceTitle->setInfo($this->plugin->txt("config.room.creationLocation.space.selection.custom.customSpaceTitle.info"));

        $customSpaceTitle->setRequired(true);
        $customSpaceTitle->setDataSource($this->ctrl->getLinkTargetByClass(
            array_values($this->controller->getCtrlClassesForCommand(ChatController::AJAX_CMD_AUTOCOMPLETE_SPACE_NAME)),
            ChatController::getCommand(ChatController::AJAX_CMD_AUTOCOMPLETE_SPACE_NAME),
            null,
            true
        ));
        $customSpaceOption->addSubItem($customSpaceTitle);
        $spaceOption->addSubItem($spaceSelection);


        $roomCreationLocation->addOption($spaceOption);

        $roomCreationLocation->addOption(new ilRadioOption(
            $this->plugin->txt("config.room.creationLocation.independent"),
            RoomCreationLocation::INDEPENDENT->value
        ));

        if ($matrixRoomId) {
            $roomCreationLocation->setDisabled(true);
            $spaceSelection->setDisabled(true);
            $customSpaceTitle->setDisabled(true);
        }

        return $roomCreationLocation;
    }
}
