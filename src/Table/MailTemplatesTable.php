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

namespace ILIAS\Plugin\MatrixChat\Table;

use ILIAS\DI\Container;
use ILIAS\Plugin\MatrixChat\Controller\MailTemplatesController;
use ILIAS\Plugin\MatrixChat\Model\MailTemplate;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ilMatrixChatConfigGUI;
use ilMatrixChatPlugin;
use ilTable2GUI;

class MailTemplatesTable extends ilTable2GUI
{
    private ilMatrixChatPlugin $plugin;
    private Container $dic;
    private MailTemplatesController $controller;
    private Factory $uiFactory;
    private Renderer $uiRenderer;

    public function __construct(ilMatrixChatConfigGUI $parentGui, MailTemplatesController $controller)
    {
        global $DIC;
        $this->dic = $DIC;
        $this->controller = $controller;
        $this->plugin = ilMatrixChatPlugin::getInstance();
        $this->uiRenderer = $this->dic->ui()->renderer();
        $this->uiFactory = $this->dic->ui()->factory();

        $this->setId("MailTemplatesTable");
        $this->setTitle($this->plugin->txt("config.mailTemplates.title"));

        parent::__construct($parentGui);

        $this->setEnableHeader(true);

        $this->setFormAction($controller->getCommandLink(
            MailTemplatesController::CMD_SHOW_MAIL_TEMPLATES_CONFIG,
            [],
            true
        ));
        $this->setRowTemplate($this->plugin->templatesFolder("table/tpl.mailTemplatesTable_row.html"));
        $this->setShowRowsSelector(false);

        $this->addColumn($this->lng->txt("language"));
        $this->addColumn($this->plugin->txt("config.mailTemplates.template.noMatrixAccount"));
        $this->addColumn($this->plugin->txt("config.mailTemplates.template.matrixAccount"));

        $this->lng->loadLanguageModule("meta");
    }

    /**
     * @param array<string, array<string, MailTemplate>> $mailTemplates
     */
    public function buildTableData(array $mailTemplates): array
    {
        $tableData = [];

        foreach ($mailTemplates as $languageId => $mailTemplateData) {
            $tableRow = [
                "language" => $this->lng->txt("meta_l_$languageId"),
            ];

            foreach ($mailTemplateData as $templateId => $mailTemplate) {
                $button = $this->uiFactory->button()->standard(
                    $this->plugin->txt("config.mailTemplates.template.edit"),
                    $this->controller->getCommandLink(
                        MailTemplatesController::CMD_EDIT_MAIL_TEMPLATE,
                        [
                            "language" => $languageId,
                            "template" => $templateId
                        ]
                    )
                );
                $tableRow[$templateId] = $this->uiRenderer->render($button) . (
                    $mailTemplate->isExists()
                        ? ""
                        : "<span style='color: red;'>" . $this->plugin->txt("config.mailTemplates.template.notConfigured") . "</span>"
                );
            }

            $tableData[] = $tableRow;
        }
        return $tableData;
    }
}
