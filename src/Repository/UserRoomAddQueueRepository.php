<?php

declare(strict_types=1);
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

namespace ILIAS\Plugin\MatrixChatClient\Repository;

use ilDBInterface;
use ILIAS\Plugin\MatrixChatClient\Model\UserRoomAddQueue;

/**
 * Class UserRoomAddQueueRepository
 *
 * @package ILIAS\Plugin\MatrixChatClient\Repository
 * @author  Marvin Beym <mbeym@databay.de>
 */
class UserRoomAddQueueRepository
{
    private static ?UserRoomAddQueueRepository $instance = null;
    protected ilDBInterface $db;

    /**
     * @var string
     */
    protected const TABLE_NAME = "mcc_usr_room_add_queue";

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

    public function exists(int $userId, int $refId): bool
    {
        $result = $this->db->queryF(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE user_id = %s AND ref_id = %s",
            ["integer", "integer"],
            [$userId, $refId]
        );

        return $result->numRows() === 1;
    }

    public function create(UserRoomAddQueue $userRoomAddQueue): bool
    {
        if ($this->exists($userRoomAddQueue->getUserId(), $userRoomAddQueue->getRefId())) {
            return false;
        }

        $affectedRows = (int) $this->db->manipulateF(
            "INSERT INTO " . self::TABLE_NAME . " (user_id, ref_id) VALUES (%s, %s)",
            ["integer", "integer"],
            [
                $userRoomAddQueue->getUserId(),
                $userRoomAddQueue->getRefId(),
            ]
        );
        return $affectedRows === 1;
    }

    public function delete(UserRoomAddQueue $userRoomAddQueue): void
    {
        $this->db->manipulateF(
            "DELETE FROM " . self::TABLE_NAME . " WHERE user_id = %s AND ref_id = %s",
            ["integer", "integer"],
            [
                $userRoomAddQueue->getUserId(),
                $userRoomAddQueue->getRefId(),
            ]
        );
    }

    public function read(int $userId, int $objRefId): ?UserRoomAddQueue
    {
        $result = $this->db->queryF(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE user_id = %s AND ref_id = %s",
            ["integer", "integer"],
            [$userId, $objRefId]
        );

        $data = $this->db->fetchAssoc($result);

        if (!$data) {
            return null;
        }

        return new UserRoomAddQueue((int) $result["user_id"], (int) $result["ref_id"]);
    }

    /**
     * @return UserRoomAddQueue[]
     */
    public function readAllByUserId(int $userId): array
    {
        $result = $this->db->queryF(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE user_id = %s",
            ["integer"],
            [$userId]
        );

        $data = [];
        while ($row = $this->db->fetchAssoc($result)) {
            $data[] = new UserRoomAddQueue((int) $row["user_id"], (int) $row["ref_id"]);
        }
        return $data;
    }
}
