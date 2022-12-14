<?php

declare(strict_types=1);
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

namespace ILIAS\Plugin\MatrixChatClient\Repository;

use ilDBInterface;

/**
 * Class UserDeviceRepository
 *
 * @package ILIAS\Plugin\MatrixChatClient\Repository
 * @author  Marvin Beym <mbeym@databay.de>
 */
class UserDeviceRepository
{
    /**
     * @var self|null
     */
    private static $instance;
    /**
     * @var ilDBInterface
     */
    protected $db;
    /**
     * @var string
     */
    protected const TABLE_NAME = "mcc_user_device";

    public function __construct(?ilDBInterface $db = null)
    {
        if ($db) {
            $this->db = $db;
        } else {
            global $DIC;
            $this->db = $DIC->database();
        }
    }

    /**
     * Returns the instance of the repository to prevent recreation of the whole object.
     * @param ilDBInterface|null $db
     * @return self
     */
    public static function getInstance(ilDBInterface $db = null) : self
    {
        if (self::$instance) {
            return self::$instance;
        }
        return self::$instance = new self($db);
    }

    public function create(int $userId, string $deviceId) : bool
    {
        $affectedRows = (int) $this->db->manipulateF(
            "INSERT INTO " . self::TABLE_NAME . " (user_id, device_id) VALUES (%s, %s)",
            ["integer", "text"],
            [$userId, $deviceId]
        );

        return $affectedRows === 1;
    }

    public function read(int $userId) : string
    {
        $result = $this->db->queryF(
            "SELECT device_id FROM " . self::TABLE_NAME . " WHERE user_id = %s",
            ["integer"],
            [$userId]
        );

        $deviceId = $this->db->fetchAssoc($result)["device_id"] ?? null;

        if (!$deviceId) {
            $deviceId = uniqid("ilias_matrix_chat_device_", true);
            $this->create($userId, $deviceId);
        }
        return $deviceId;
    }
}
