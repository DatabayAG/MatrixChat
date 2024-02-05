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

use ilGlobalTemplateInterface;
use ILIAS\DI\Container;
use ilMatrixChatConfigGUI;
use ilMatrixChatPlugin;
use ilPropertyFormGUI;
use ilTextAreaInputGUI;

class ChatPageDesignerForm extends ilPropertyFormGUI
{
    private ilMatrixChatPlugin $plugin;
    private Container $dic;
    private ilGlobalTemplateInterface $mainTpl;

    public function __construct()
    {
        parent::__construct();
        $this->plugin = ilMatrixChatPlugin::getInstance();
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
                ilMatrixChatConfigGUI::class,
                ilMatrixChatConfigGUI::CMD_SHOW_CHAT_PAGE_DESIGNER
            )
        );
        $this->setId("{$this->plugin->getId()}_{$this->plugin->getPluginName()}_chat_page_designer");
        $this->setTitle($this->plugin->txt("general.plugin.settings"));

        $this->addCommandButton(ilMatrixChatConfigGUI::CMD_SAVE_CHAT_PAGE_DESIGNER, $this->lng->txt("save"));
    }
}
