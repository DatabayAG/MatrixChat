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
use ILIAS\Plugin\MatrixChat\Model\MailError;
use ILIAS\Plugin\MatrixChat\Model\MailTemplate;
use ILIAS\Plugin\MatrixChat\Repository\MailTemplatesRepository;
use ILIAS\Plugin\MatrixChat\Table\MailTemplatesTable;
use ILIAS\Plugin\MatrixChat\Utils\UiUtil;
use ILIAS\Refinery\Factory;
use ilLink;
use ilLogger;
use ilMail;
use ilMatrixChatConfigGUI;
use ilMatrixChatPlugin;
use ilObject;
use ilObjUser;
use ilTabsGUI;

class MailTemplatesController extends BaseController
{
    public const CMD_SHOW_MAIL_TEMPLATES_CONFIG = "showSettings";
    public const CMD_EDIT_MAIL_TEMPLATE = "editMailTemplate";
    public const CMD_SAVE_MAIL_TEMPLATE = "saveMailTemplate";

    public const TEMPLATE_NO_MATRIX_ACCOUNT = "noMatrixAccount";
    public const TEMPLATE_MATRIX_ACCOUNT = "matrixAccount";

    public const SUPPORTED_TEMPLATES = [
        self::TEMPLATE_NO_MATRIX_ACCOUNT,
        self::TEMPLATE_MATRIX_ACCOUNT
    ];

    private MailTemplatesRepository $mailTemplateRepo;
    private ilMatrixChatConfigGUI $configGui;
    private WrapperFactory $httpWrapper;
    private Factory $refinery;
    private ilMatrixChatPlugin $plugin;
    private ilTabsGUI $tabs;
    private ilLogger $logger;

    /** @var string[] */
    private array $availableLanguages;
    private UiUtil $uiUtil;


    public function __construct(Container $dic, ControllerHandler $controllerHandler)
    {
        parent::__construct($dic, $controllerHandler);
        $this->configGui = new ilMatrixChatConfigGUI();
        $this->httpWrapper = $dic->http()->wrapper();
        $this->refinery = $dic->refinery();
        $this->plugin = ilMatrixChatPlugin::getInstance();
        $this->tabs = $this->dic->tabs();
        $this->logger = $this->dic->logger()->root();
        $this->availableLanguages = $this->dic->language()->getInstalledLanguages();
        $this->mailTemplateRepo = MailTemplatesRepository::getInstance($this->dic->database(), $this->availableLanguages);
        $this->uiUtil = new UiUtil($this->dic);
    }

    public function getTemplatePlaceholders(ilObjUser $user, ?int $objRefId = null): array
    {
        return [
            "[FIRSTNAME]" => $user->getFirstname(),
            "[LASTNAME]" => $user->getLastname(),
            "[OBJECT_TITLE]" => $objRefId
                ? ilObject::_lookupTitle(ilObject::_lookupObjId($objRefId))
                : "",
            "[CHAT_SETTINGS_LINK]" => BaseUserConfigController::buildPermanentLink(),
            "[OBJECT_LINK]" => $objRefId
                ? ilLink::_getStaticLink($objRefId, ilObject::_lookupType($objRefId, true))
                : "",
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
            ));
            $this->redirectToCommand(self::CMD_SHOW_MAIL_TEMPLATES_CONFIG);
        }

        if (!$form) {
            $form = new MailTemplateForm($this, $language, $template);
            $form->setValuesByArray([
                "subject" => $mailTemplate->getSubject(),
                "content" => $mailTemplate->getContent(),
                "active" => $mailTemplate->isActive()
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
            $form->getInput("content"),
            false,
            (bool) $form->getInput("active")
        );

        $this->mailTemplateRepo->save($mailTemplate);
        $this->uiUtil->sendSuccess($this->plugin->txt("config.mailTemplates.template.saved.success"));
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
                sprintf($this->plugin->txt("general.plugin.requiredParameterMissing"), $parameterName)
            );
            $this->redirectToCommand(self::CMD_SHOW_MAIL_TEMPLATES_CONFIG);
        }

        return $parameter;
    }

    private function checkLanguageId(string $languageId, bool $redirectOnError = true): bool
    {
        if (in_array($languageId, $this->availableLanguages, true)) {
            return true;
        }

        if ($redirectOnError) {
            $this->uiUtil->sendFailure(sprintf(
                $this->plugin->txt("config.mailTemplates.template.unsupported.language"),
                $languageId
            ));
            $this->redirectToCommand(self::CMD_SHOW_MAIL_TEMPLATES_CONFIG);
        }
        return false;
    }

    public function sendMail(int $objRefId, ilObjUser $user, string $template, string $language): ?MailError
    {
        if (!$this->checkTemplateId($template, false)) {
            $this->logger->error(sprintf(
                "Unable to send mail template '%s' (language: '%s') to user with id '%s'. Template does not exist.",
                $template,
                $language,
                $user->getId()
            ));
            return new MailError(
                $user->getLogin(),
                sprintf(
                    $this->plugin->txt("config.mailTemplates.template.unsupported.template"),
                    $template
                ),
                $objRefId,
                $template,
                $language
            );
        }

        if (!$this->checkLanguageId($language, false)) {
            $this->logger->error(sprintf(
                "Unable to send mail template '%s' (language: '%s') to user with id '%s'. Language not supported.",
                $template,
                $language,
                $user->getId()
            ));
            return new MailError(
                $user->getLogin(),
                sprintf(
                    $this->plugin->txt("config.mailTemplates.template.unsupported.language"),
                    $language
                ),
                $objRefId,
                $template,
                $language
            );
        }

        $mail = new ilMail(ANONYMOUS_USER_ID);

        $mailTemplate = $this->mailTemplateRepo->read($template, $language);
        if (!$mailTemplate) {
            $this->logger->error(sprintf(
                "No template with the ID '%s' could be found for the language '%s'",
                $template,
                $language
            ));

            return new MailError(
                $user->getLogin(),
                sprintf(
                    $this->plugin->txt("config.mailTemplates.template.notFound"),
                    $template,
                    $language
                ),
                $objRefId,
                $template,
                $language
            );
        }

        if (!$mailTemplate->isActive()) {
            return null;
        }

        $subject = $mailTemplate->getSubject();
        $content = $mailTemplate->getContent();

        foreach ($this->getTemplatePlaceholders($user, $objRefId) as $placeholder => $value) {
            $subject = str_replace($placeholder, $value, $subject);
            $content = str_replace($placeholder, $value, $content);
        }

        $errors = $mail->enqueue(
            $user->getLogin(),
            "",
            "",
            $subject,
            $content,
            []
        );

        $mailError = null;
        $errorMessages = [];
        foreach ($errors as $error) {
            $errorMessages[] = $error->getLanguageVariable();
            $this->logger->error(sprintf(
                "Error occurred trying to send mail to user with id '%s'. Error: %s | obj-ref-id: %s, template: %s, language: %s",
                $user->getId(),
                $error->getLanguageVariable(),
                $objRefId,
                $template,
                $language
            ));
        }

        if ($errorMessages !== []) {
            $mailError = new MailError(
                $user->getLogin(),
                implode(", ", $errorMessages),
                $objRefId,
                $template,
                $language
            );
        }

        return $mailError;
    }

    /** @param MailError[] $mailErrors */
    public function showMailErrors(array $mailErrors): void
    {
        if ($mailErrors !== []) {
            $mailErrorMessage = "<br><ul>";

            foreach ($mailErrors as $mailError) {
                $mailErrorMessage .= "<li>" . $mailError->formatMessage() . "</li>";
            }
            $mailErrorMessage .= "</ul>";

            $this->uiUtil->sendFailure(sprintf(
                $this->plugin->txt("mail.send.failed"),
                $mailErrorMessage
            ));
        }
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
            ));
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
