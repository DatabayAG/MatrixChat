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

namespace ILIAS\Plugin\MatrixChat\Repository;

use ilDBConstants;
use ilDBInterface;
use ILIAS\Plugin\MatrixChat\Controller\MailTemplatesController;
use ILIAS\Plugin\MatrixChat\Model\MailTemplate;
use ilLanguage;

class MailTemplatesRepository
{
    private static ?MailTemplatesRepository $instance = null;
    protected ilDBInterface $db;

    /** @var string */
    protected const TABLE_NAME = "mcc_mail_templates";
    private array $availableLanguages;
    private ilLanguage $lng;

    public function __construct(?ilDBInterface $db = null)
    {
        global $DIC;

        if ($db) {
            $this->db = $db;
        } else {
            $this->db = $DIC->database();
        }

        $this->lng = $DIC->language();
        $this->availableLanguages = $DIC->language()->getInstalledLanguages();
    }

    public function getAvailableLanguages(): array
    {
        return $this->availableLanguages;
    }

    public static function getInstance(?ilDBInterface $db = null): self
    {
        if (self::$instance) {
            return self::$instance;
        }
        return self::$instance = new self($db);
    }

    public function exists(string $templateId, string $language): bool
    {
        $result = $this->db->queryF(
            "SELECT 1 AS exist FROM " . self::TABLE_NAME . " WHERE template_id = %s AND language = %s",
            [ilDBConstants::T_TEXT, ilDBConstants::T_TEXT],
            [$templateId, $language]
        );

        return (bool) $this->db->fetchAssoc($result);
    }

    public function save(MailTemplate $mailTemplate): bool
    {
        if ($this->exists($mailTemplate->getTemplateId(), $mailTemplate->getLanguage())) {
            $mailTemplate->setExists(true);
            return $this->db->manipulateF(
                    "UPDATE " . self::TABLE_NAME . " SET "
                    . "subject = %s, "
                    . "content = %s "
                    . "WHERE template_id = %s AND language = %s",
                    [
                        ilDBConstants::T_CLOB,
                        ilDBConstants::T_CLOB,
                        ilDBConstants::T_TEXT,
                        ilDBConstants::T_TEXT,
                    ],
                    [
                        $mailTemplate->getSubject(),
                        $mailTemplate->getContent(),
                        $mailTemplate->getTemplateId(),
                        $mailTemplate->getLanguage()
                    ]
                ) === 1;
        }

        $result = $this->db->manipulateF(
                "INSERT INTO " . self::TABLE_NAME . " (template_id, language, subject, content) VALUES (%s, %s, %s, %s)",
                [ilDBConstants::T_TEXT, ilDBConstants::T_TEXT, ilDBConstants::T_CLOB, ilDBConstants::T_CLOB],
                [
                    $mailTemplate->getTemplateId(),
                    $mailTemplate->getLanguage(),
                    $mailTemplate->getSubject(),
                    $mailTemplate->getContent(),
                ]
            ) === 1;

        $mailTemplate->setExists($result);
        return $result;
    }

    public function read(string $templateId, string $language, bool $returnNewOnNotFound = true): ?MailTemplate
    {
        if ($returnNewOnNotFound && !$this->exists($templateId, $language)) {
            return $this->constructFallbackNewMailTemplate(
                $templateId,
                $language
            );
        }
        $result = $this->db->queryF(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE template_id = %s AND language = %s",
            [ilDBConstants::T_TEXT, ilDBConstants::T_TEXT],
            [$templateId, $language]
        );

        $data = $this->db->fetchAssoc($result);
        if (!$data) {
            return null;
        }

        return $this->map($data);
    }

    /** @return array<string, array<string, MailTemplate>> */
    public function readAllMappedByLanguageAndTemplateId(bool $addMissing = true): array
    {
        $result = $this->db->query("SELECT * FROM " . self::TABLE_NAME);

        /** @var MailTemplate[] $data */
        $data = [];
        while ($row = $this->db->fetchAssoc($result)) {
            $data[] = $this->map($row);
        }

        /** @var array<string, array<string, MailTemplate>> $languageMappedData */
        $languageMappedData = [];
        foreach ($data as $mailTemplate) {
            $languageMappedData[$mailTemplate->getLanguage()][$mailTemplate->getTemplateId()] = $mailTemplate;
        }

        if (!$addMissing) {
            return $languageMappedData;
        }

        foreach ($this->availableLanguages as $language) {
            if (!array_key_exists($language, $languageMappedData)) {
                foreach (MailTemplatesController::SUPPORTED_TEMPLATES as $templateId) {
                    $languageMappedData[$language][$templateId] = $this->constructFallbackNewMailTemplate(
                        $templateId,
                        $language
                    );
                }
            } else {
                foreach (MailTemplatesController::SUPPORTED_TEMPLATES as $templateId) {
                    if (!array_key_exists($templateId, $languageMappedData[$language])) {
                        $languageMappedData[$language][$templateId] = $this->constructFallbackNewMailTemplate(
                            $templateId,
                            $language
                        );
                    }
                }
            }
        }

        return $languageMappedData;
    }

    private function constructFallbackNewMailTemplate(string $templateId, string $language): MailTemplate
    {
        $fallbackText = $this->lng->txtlng("ui_uihk_mcc", "ui_uihk_mcc_config.mailTemplates.template.$templateId", $language);
        return new MailTemplate(
            $templateId,
            $language,
            $fallbackText,
            $fallbackText
        );
    }

    private function map(array $row): MailTemplate
    {
        return new MailTemplate(
            $row["template_id"],
            $row["language"],
            $row["subject"],
            $row["content"],
            true
        );
    }
}
