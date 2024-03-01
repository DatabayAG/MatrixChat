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

use DateTime;
use ilDBInterface;
use ILIAS\Plugin\MatrixChat\Model\MatrixUserHistory;
use ILIAS\Plugin\MatrixChat\Model\UserRoomAddQueue;

class MatrixUserHistoryRepository
{
    private static ?MatrixUserHistoryRepository $instance = null;
    protected ilDBInterface $db;

    /** @var string */
    protected const TABLE_NAME = "mcc_matrix_usr_history";

    public function __construct(?ilDBInterface $db = null)
    {
        if ($db) {
            $this->db = $db;
        } else {
            global $DIC;
            $this->db = $DIC->database();
        }
    }

    public static function getInstance(?ilDBInterface $db = null): self
    {
        if (self::$instance) {
            return self::$instance;
        }
        return self::$instance = new self($db);
    }

    public function create(MatrixUserHistory $matrixUserHistory): bool
    {
        $matrixUserHistory->setCreatedAt(new DateTime());
        $matrixUserHistory->setId($this->db->nextId(self::TABLE_NAME));
        return $this->db->manipulateF(
            "INSERT INTO " . self::TABLE_NAME . " (id, user_id, matrix_user_id, created_at) VALUES (%s, %s, %s, %s)",
            ["integer", "integer", "text", "integer"],
            [
                $matrixUserHistory->getId(),
                $matrixUserHistory->getUserId(),
                $matrixUserHistory->getMatrixUserId(),
                $matrixUserHistory->getCreatedAt()->getTimestamp()
            ]
        ) === 1;
    }
}
