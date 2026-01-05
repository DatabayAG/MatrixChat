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

namespace ILIAS\Plugin\MatrixChat\Model;

class ChatMember
{
    public function __construct(
        private readonly int $userId,
        private readonly string $name,
        private readonly string $login,
        private readonly string $roleText,
        private readonly string $status,
        private readonly string $matrixUserId
    ) {
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function getRoleText(): string
    {
        return $this->roleText;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getMatrixUserId(): string
    {
        return $this->matrixUserId;
    }
}
