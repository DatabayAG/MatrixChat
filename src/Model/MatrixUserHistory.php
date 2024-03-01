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

use DateTime;

class MatrixUserHistory
{
    private int $id;
    private int $userId;
    private string $matrixUserId;
    private DateTime $createdAt;

    public function __construct(int $userId, string $matrixUserId)
    {
        $this->userId = $userId;
        $this->matrixUserId = $matrixUserId;
    }

    public function setId(int $id): MatrixUserHistory
    {
        $this->id = $id;
        return $this;
    }

    public function setCreatedAt(DateTime $createdAt): MatrixUserHistory
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getMatrixUserId(): string
    {
        return $this->matrixUserId;
    }

    public function getCreatedAt(): DateTime
    {
        return $this->createdAt;
    }
}
