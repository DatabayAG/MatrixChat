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
    public function __construct(
        private readonly string $templateId,
        private readonly string $language,
        private readonly string $subject = "",
        private readonly string $content = "",
        private bool $exists = false,
        private readonly bool $active = true
    ) {
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

    public function getContent(): string
    {
        return $this->content;
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

    public function isActive(): bool
    {
        return $this->active;
    }
}
