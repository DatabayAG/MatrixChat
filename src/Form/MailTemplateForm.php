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


namespace ILIAS\Plugin\MatrixChat\Form;

use ilGlobalPageTemplate;
use ILIAS\DI\Container;
use ILIAS\Plugin\MatrixChat\Controller\MailTemplatesController;
use ilMatrixChatConfigGUI;
use ilMatrixChatPlugin;
use ilPropertyFormGUI;
use ilTextAreaInputGUI;
use ilTextInputGUI;

class MailTemplateForm extends ilPropertyFormGUI
{
    protected ilMatrixChatPlugin $plugin;
    protected ilGlobalPageTemplate $mainTpl;
    protected Container $dic;
    protected MailTemplatesController $controller;

    public function __construct(
        MailTemplatesController $controller,
        string $language,
        string $template
    ) {
        global $DIC;
        parent::__construct();
        $this->dic = $DIC;
        $this->plugin = ilMatrixChatPlugin::getInstance();
        $this->mainTpl = $this->dic->ui()->mainTemplate();
        $this->controller = $controller;

        $this->lng->loadLanguageModule("meta");

        $this->setTitle(sprintf(
            $this->plugin->txt("config.mailTemplates.template.editTemplateTitle"),
            $this->plugin->txt("config.mailTemplates.template.$template"),
            $this->lng->txt("meta_l_$language")
        ));

        $this->ctrl->setParameterByClass(
            ilMatrixChatConfigGUI::class,
            "language",
            $language
        );

        $this->ctrl->setParameterByClass(
            ilMatrixChatConfigGUI::class,
            "template",
            $template
        );
        $this->setFormAction($controller->getCommandLink(
            MailTemplatesController::CMD_SHOW_MAIL_TEMPLATES_CONFIG,
            [],
            true
        ));

        $subject = new ilTextInputGUI(
            $this->plugin->txt("config.mailTemplates.template.subject.title"),
            "subject"
        );

        $placeHolderInfoList = array_map(function (string $placeholderKey): string {
            return "<li>"
                . "<span style='font-weight: bolder'>$placeholderKey</span>"
                . ": "
                . $this->plugin->txt("config.mailTemplates.template.placeholders.$placeholderKey")
                . "</li>";
        }, array_keys($this->controller->getTemplatePlaceholders()));

        $subject->setRequired(true);
        $subject->setInfo(sprintf(
                $this->plugin->txt("config.mailTemplates.template.subject.info"),
                implode("", $placeHolderInfoList)
            )
        );
        $this->addItem($subject);

        $content = new ilTextAreaInputGUI(
            $this->plugin->txt("config.mailTemplates.template.content.title"),
            "content"
        );
        $content->setRequired(true);
        $content->setInfo(sprintf(
            $this->plugin->txt("config.mailTemplates.template.content.info"),
            implode("", $placeHolderInfoList)
        ));
        $this->addItem($content);

        $this->addCommandButton(
            MailTemplatesController::getCommand(MailTemplatesController::CMD_SAVE_MAIL_TEMPLATE),
            $this->lng->txt("save")
        );
    }
}
