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


namespace ILIAS\Plugin\MatrixChat\Controller;

use ILIAS\DI\Container;
use ILIAS\Plugin\Libraries\ControllerHandler\BaseController;
use ILIAS\Plugin\Libraries\ControllerHandler\ControllerHandler;
use ILIAS\Plugin\MatrixChat\Repository\MailTemplatesRepository;
use ILIAS\Plugin\MatrixChat\Table\MailTemplatesTable;
use ilMatrixChatConfigGUI;

class MailTemplatesController extends BaseController
{
    public const CMD_SHOW_MAIL_TEMPLATES_CONFIG = "showSettings";
    public const CMD_EDIT_MAIL_TEMPLATE = "editMailTemplate";
    private MailTemplatesRepository $mailTemplateRepo;
    private ilMatrixChatConfigGUI $configGui;

    public function __construct(Container $dic, ControllerHandler $controllerHandler)
    {
        parent::__construct($dic, $controllerHandler);
        $this->mailTemplateRepo = MailTemplatesRepository::getInstance($dic->database());
        $this->configGui = new ilMatrixChatConfigGUI();
    }

    public function showSettings(): void
    {
        $this->injectTabs(ilMatrixChatConfigGUI::TAB_MAIL_TEMPLATES);
        $table = new MailTemplatesTable($this->configGui, $this);
        $table->setData($table->buildTableData($this->mailTemplateRepo->readAllMappedByLanguageAndTemplateId()));

        $this->mainTpl->setContent($table->getHTML());
    }
    
    public function injectTabs(?string $tabId = null): void
    {
        $this->configGui->injectTabs($tabId);
    }

    public function getCtrlClassesForCommand(string $cmd): array
    {
        return [ilMatrixChatConfigGUI::class];
    }
}
