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


namespace ILIAS\Plugin\MatrixChat\Model;

class MailTemplate
{
    private string $templateId;
    private string $language;
    private string $subject;
    private string $content;
    private bool $exists;

    public function __construct(
        string $templateId,
        string $language,
        string $subject = "",
        string $content = "",
        bool $exists = false
    ) {
        $this->templateId = $templateId;
        $this->language = $language;
        $this->subject = $subject;
        $this->content = $content;
        $this->exists = $exists;
    }

    public function getTemplateId(): string
    {
        return $this->templateId;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): MailTemplate
    {
        $this->subject = $subject;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): MailTemplate
    {
        $this->content = $content;
        return $this;
    }

    public function isExists(): bool
    {
        return $this->exists;
    }

    public function setExists(bool $exists): MailTemplate
    {
        $this->exists = $exists;
        return $this;
    }
}
