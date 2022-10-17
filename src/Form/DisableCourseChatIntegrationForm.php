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
use ILIAS\Plugin\MatrixChatClient\Controller\ChatCourseSettingsController;
use ilUtil;
use ilMatrixChatClientPlugin;
use ilCheckboxInputGUI;

/**
 * Class DisableCourseChatIntegration
 *
 * @package ILIAS\Plugin\MatrixChatClient\Form
 * @author  Marvin Beym <mbeym@databay.de>
 */
class DisableCourseChatIntegrationForm extends ilPropertyFormGUI
{
    /**
     * @var ilMatrixChatClientPlugin
     */
    private $plugin;

    public function __construct(?int $courseId = null)
    {
        parent::__construct();
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
        ilUtil::sendQuestion($this->plugin->txt("matrix.chat.room.delete.confirm"));

        $fullyDeleteChatRoom = new ilCheckboxInputGUI($this->plugin->txt("matrix.chat.room.delete.button"), "deleteChatRoom");
        $this->addItem($fullyDeleteChatRoom);

        $this->setFormAction(ChatCourseSettingsController::getInstance()->getCommandLink("showSettings", ["ref_id" => $courseId], true));
        $this->addCommandButton(ChatCourseSettingsController::getCommand("showSettings"), $this->lng->txt("cancel"));
        $this->addCommandButton(ChatCourseSettingsController::getCommand("disableCourseChatIntegration"), $this->lng->txt("confirm"));
    }
}
