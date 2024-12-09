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
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Plugin\Libraries\ControllerHandler\BaseController;
use ILIAS\Plugin\Libraries\ControllerHandler\ControllerHandler;
use ILIAS\Plugin\MatrixChat\Form\MailTemplateForm;
use ILIAS\Plugin\MatrixChat\Model\MailTemplate;
use ILIAS\Plugin\MatrixChat\Repository\MailTemplatesRepository;
use ILIAS\Plugin\MatrixChat\Table\MailTemplatesTable;
use ILIAS\Refinery\Factory;
use ilMatrixChatConfigGUI;
use ilMatrixChatPlugin;
use ilObject;
use ilObjUser;
use ilRepositoryGUI;
use ilTabsGUI;

class MailTemplatesController extends BaseController
{
    public const CMD_SHOW_MAIL_TEMPLATES_CONFIG = "showSettings";
    public const CMD_EDIT_MAIL_TEMPLATE = "editMailTemplate";
    public const CMD_SAVE_MAIL_TEMPLATE = "saveMailTemplate";

    public const SUPPORTED_TEMPLATES = ["noMatrixAccount", "matrixAccount"];

    private MailTemplatesRepository $mailTemplateRepo;
    private ilMatrixChatConfigGUI $configGui;
    private WrapperFactory $httpWrapper;
    private Factory $refinery;
    private ilMatrixChatPlugin $plugin;
    private ilTabsGUI $tabs;
    private ilObjUser $user;


    public function __construct(Container $dic, ControllerHandler $controllerHandler)
    {
        parent::__construct($dic, $controllerHandler);
        $this->mailTemplateRepo = MailTemplatesRepository::getInstance($dic->database());
        $this->configGui = new ilMatrixChatConfigGUI();
        $this->httpWrapper = $dic->http()->wrapper();
        $this->refinery = $dic->refinery();
        $this->plugin = ilMatrixChatPlugin::getInstance();
        $this->tabs = $this->dic->tabs();
        $this->user = $this->dic->user();
    }

    public function getTemplatePlaceholders(?int $objRefId = null): array
    {
        $objectLink = "";
        if ($objRefId) {
            $this->ctrl->setParameterByClass(ilRepositoryGUI::class, "ref_id", $objRefId);
            $objectLink = $this->ctrl->getLinkTargetByClass(ilRepositoryGUI::class);
        }
        return [
            "[FIRSTNAME]" => $this->user->getFirstname(),
            "[LASTNAME]" => $this->user->getLastname(),
            "[OBJECT_TITLE]" => $objRefId
                ? ilObject::_lookupTitle(ilObject::_lookupObjId($objRefId))
                : "",
            "[CHAT_SETTINGS_LINK]" => BaseUserConfigController::buildPermanentLink(),
            "[OBJECT_LINK]" => $objectLink,
        ];
    }

    public function showSettings(): void
    {
        $this->injectTabs(ilMatrixChatConfigGUI::TAB_MAIL_TEMPLATES);
        $table = new MailTemplatesTable($this->configGui, $this);
        $table->setData($table->buildTableData($this->mailTemplateRepo->readAllMappedByLanguageAndTemplateId()));

        $this->mainTpl->setContent($table->getHTML());
    }

    public function editMailTemplate(?MailTemplateForm $form = null): void
    {
        $this->tabs->setBackTarget(
            $this->plugin->txt("config.mailTemplates.title"),
            $this->getCommandLink(self::CMD_SHOW_MAIL_TEMPLATES_CONFIG)
        );

        $language = $this->verifyQueryParameterExists("language");
        $template = $this->verifyQueryParameterExists("template");
        $this->checkLanguageId($language);
        $this->checkTemplateId($template);

        $mailTemplate = $this->mailTemplateRepo->read($template, $language);

        if (!$mailTemplate) {
            $this->uiUtil->sendFailure(sprintf(
                $this->plugin->txt("config.mailTemplates.template.notFound"),
                $template,
                $language
            ), true);
            $this->redirectToCommand(self::CMD_SHOW_MAIL_TEMPLATES_CONFIG);
        }

        if (!$form) {
            $form = new MailTemplateForm($this, $language, $template);
            $form->setValuesByArray([
                "subject" => $mailTemplate->getSubject(),
                "content" => $mailTemplate->getContent()
            ]);
        }

        $this->mainTpl->setContent($form->getHTML());
    }

    public function saveMailTemplate(): void
    {
        $language = $this->verifyQueryParameterExists("language");
        $template = $this->verifyQueryParameterExists("template");
        $this->checkLanguageId($language);
        $this->checkTemplateId($template);

        $form = new MailTemplateForm($this, $language, $template);

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->editMailTemplate($form);
            return;
        }

        $mailTemplate = new MailTemplate(
            $template,
            $language,
            $form->getInput("subject"),
            $form->getInput("content")
        );

        $this->mailTemplateRepo->save($mailTemplate);
        $this->uiUtil->sendSuccess($this->plugin->txt("config.mailTemplates.template.saved.success"), true);
        $this->redirectToCommand(
            self::CMD_EDIT_MAIL_TEMPLATE,
            [
                "template" => $template,
                "language" => $language
            ]
        );
    }

    private function verifyQueryParameterExists(string $parameterName): string
    {
        $parameter = $this->httpWrapper->query()->retrieve(
            $parameterName,
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always(null)
            ])
        );

        if ($parameter === null) {
            $this->uiUtil->sendFailure(
                sprintf($this->plugin->txt("general.plugin.requiredParameterMissing"), $parameterName),
                true
            );
            $this->redirectToCommand(self::CMD_SHOW_MAIL_TEMPLATES_CONFIG);
        }

        return $parameter;
    }

    private function checkLanguageId(string $languageId, bool $redirectOnError = true): bool
    {
        if (in_array($languageId, $this->mailTemplateRepo->getAvailableLanguages(), true)) {
            return true;
        }

        if ($redirectOnError) {
            $this->uiUtil->sendFailure(sprintf(
                $this->plugin->txt("config.mailTemplates.template.unsupported.language"),
                $languageId
            ), true);
            $this->redirectToCommand(self::CMD_SHOW_MAIL_TEMPLATES_CONFIG);
        }
        return false;
    }

    private function checkTemplateId(string $templateId, bool $redirectOnError = true): bool
    {
        if (in_array($templateId, self::SUPPORTED_TEMPLATES, true)) {
            return true;
        }

        if ($redirectOnError) {
            $this->uiUtil->sendFailure(sprintf(
                $this->plugin->txt("config.mailTemplates.template.unsupported.template"),
                $templateId
            ), true);
            $this->redirectToCommand(self::CMD_SHOW_MAIL_TEMPLATES_CONFIG);
        }
        return false;
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
