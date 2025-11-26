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

class MailError
{
    public function __construct(
        private readonly string $userLogin,
        private readonly string $error,
        private readonly int $objRefId,
        private readonly string $templateId,
        private readonly string $language
    )
    {
    }

    public function getUserLogin(): string
    {
        return $this->userLogin;
    }

    public function getError(): string
    {
        return $this->error;
    }

    public function getObjRefId(): int
    {
        return $this->objRefId;
    }

    public function getTemplateId(): string
    {
        return $this->templateId;
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function formatMessage(bool $withoutRefId = true): string
    {
        if ($withoutRefId) {
            return sprintf(
                "<span style='font-weight: bold;'>%s</span>: %s <span style='font-weight: bold;'>|</span> Data: template: %s, language: %s",
                $this->getUserLogin(),
                $this->getError(),
                $this->getTemplateId(),
                $this->getLanguage()
            );
        }

        return sprintf(
            "%s: %s | Data: ref-id: %s, template: %s, language: %s",
            $this->getUserLogin(),
            $this->getError(),
            $this->getObjRefId(),
            $this->getTemplateId(),
            $this->getLanguage()
        );
    }
}
