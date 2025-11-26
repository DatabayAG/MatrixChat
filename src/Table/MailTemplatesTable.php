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

use Generator;
use ILIAS\Data\Order;
use ILIAS\Data\Range;
use ILIAS\DI\Container;
use ILIAS\Plugin\MatrixChat\Controller\MailTemplatesController;
use ILIAS\Plugin\MatrixChat\Model\MailTemplate;
use ILIAS\Plugin\MatrixChat\Repository\MailTemplatesRepository;
use ILIAS\UI\Component\Table\Data;
use ILIAS\UI\Component\Table\DataRetrieval;
use ILIAS\UI\Component\Table\DataRowBuilder;
use ILIAS\UI\Component\Table\Factory as TableFactory;
use ILIAS\UI\Factory as UiFactory;
use ILIAS\UI\Renderer;
use ilLanguage;
use ilMatrixChatPlugin;
use Psr\Http\Message\ServerRequestInterface;

class MailTemplatesTable implements DataRetrieval
{
    private ilLanguage $lng;
    private ilMatrixChatPlugin $plugin;
    private Data $table;
    private UiFactory $uiFactory;
    private Renderer $uiRenderer;
    private TableFactory $uiTableFactory;
    private ServerRequestInterface $request;

    public function __construct(
        private readonly Container               $dic,
        private readonly MailTemplatesController $controller,
        private readonly MailTemplatesRepository $repo,
    )
    {

        $this->plugin = ilMatrixChatPlugin::getInstance();
        $this->lng = $dic->language();
        $this->uiFactory = $this->dic->ui()->factory();
        $this->uiTableFactory = $this->uiFactory->table();
        $this->uiRenderer = $this->dic->ui()->renderer();
        $this->request = $this->dic->http()->request();

        $this->table = $this->buildTable();
    }

    public function getRows(
        DataRowBuilder $row_builder,
        array          $visible_column_ids,
        Range          $range,
        Order          $order,
        ?array         $filter_data,
        ?array         $additional_parameters
    ): Generator
    {
        $table_rows = $this->buildTableRows($this->repo->readAllMappedByLanguageAndTemplateId());

        foreach ($table_rows as $row) {
            yield $row_builder->buildDataRow((string) $row["language"], $row);
        }
    }

    public function getTotalRowCount(?array $filter_data, ?array $additional_parameters): ?int
    {
        return count($this->dic->language()->getInstalledLanguages());
    }

    private function buildTable(): Data
    {

        return $this->uiTableFactory->data(
            $this->plugin->txt("config.mailTemplates.title"),
            [
                "language" => $this->uiTableFactory->column()
                    ->text($this->lng->txt("language"))
                    ->withIsSortable(false),
                "noMatrixAccount" => $this->uiTableFactory->column()
                    ->text($this->plugin->txt("config.mailTemplates.template.noMatrixAccount"))
                    ->withIsSortable(false),
                "matrixAccount" => $this->uiTableFactory->column()
                    ->text($this->plugin->txt("config.mailTemplates.template.matrixAccount"))
                    ->withIsSortable(false)
            ],
            $this
        )
            ->withId("MailTemplatesTable")
            ->withRequest($this->request);
    }


    /**
     * @param array<string, array{matrixAccount: MailTemplate, noMatrixAccount: MailTemplate}> $mailTemplates
     * @return list<array{language: string, matrixAccount: string, "noMatrixAccount": string}>
     */
    private function buildTableRows(array $mailTemplates): array
    {
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

    public function render(): string
    {
        return $this->uiRenderer->render($this->table);
    }
}
