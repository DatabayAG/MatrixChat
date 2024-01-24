<?php

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

declare(strict_types=1);

namespace ILIAS\Plugin\MatrixChatClient\Form;

use ilGlobalTemplateInterface;
use ILIAS\DI\Container;
use ilMatrixChatClientConfigGUI;
use ilMatrixChatClientPlugin;
use ilPropertyFormGUI;
use ilTextAreaInputGUI;

/**
 * Class ChatPageDesignerForm
 *
 * @package ILIAS\Plugin\MatrixChatClient\Form
 * @author  Marvin Beym <mbeym@databay.de>
 */
class ChatPageDesignerForm extends ilPropertyFormGUI
{
    private ilMatrixChatClientPlugin $plugin;
    private Container $dic;
    private ilGlobalTemplateInterface $mainTpl;

    public function __construct()
    {
        parent::__construct();
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
        $this->dic = $this->plugin->dic;
        $this->mainTpl = $this->dic->ui()->mainTemplate();

        $chatPageText = new ilTextAreaInputGUI(
            $this->plugin->txt("config.pageDesignerText.title"),
            "pageDesignerText"
        );
        $chatPageText->setRteTagSet("full");
        $chatPageText->setUseRte(true);
        $chatPageText->setInfo($this->plugin->txt("config.pageDesignerText.info"));

        $this->addItem($chatPageText);

        $this->setFormAction(
            $this->ctrl->getFormActionByClass(
                ilMatrixChatClientConfigGUI::class,
                ilMatrixChatClientConfigGUI::CMD_SHOW_CHAT_PAGE_DESIGNER
            )
        );
        $this->setId("{$this->plugin->getId()}_{$this->plugin->getPluginName()}_chat_page_designer");
        $this->setTitle($this->plugin->txt("general.plugin.settings"));

        $this->addCommandButton(ilMatrixChatClientConfigGUI::CMD_SAVE_CHAT_PAGE_DESIGNER, $this->lng->txt("save"));
    }
}
