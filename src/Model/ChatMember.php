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
    private int $userId;
    private string $name;
    private string $login;
    private string $roleText;
    private string $status;
    private string $matrixUserId;

    public function __construct(
        int $userId,
        string $name,
        string $login,
        string $roleText,
        string $status,
        string $matrixUserId
    ) {
        $this->userId = $userId;
        $this->name = $name;
        $this->login = $login;
        $this->roleText = $roleText;
        $this->status = $status;
        $this->matrixUserId = $matrixUserId;
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
