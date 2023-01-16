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
use ILIAS\Plugin\MatrixChatClient\Model\UserData;

/**
 * Class UserDeviceRepository
 *
 * @package ILIAS\Plugin\MatrixChatClient\Repository
 * @author  Marvin Beym <mbeym@databay.de>
 */
class UserDataRepository
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
    protected const TABLE_NAME = "mcc_user_data";

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
     *
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

    public function create(UserData $userData) : bool
    {
        $affectedRows = (int) $this->db->manipulateF(
            "INSERT INTO " . self::TABLE_NAME . " (ilias_user_id, matrix_user_id, matrix_device_id) VALUES (%s, %s, %s)",
            ["integer", "text", "text"],
            [$userData->getIliasUserId(), $userData->getMatrixUserId(), $userData->getDeviceId()]
        );

        //uniqid("ilias_matrix_chat_device_", true) => Maybe useful in the future

        return $affectedRows === 1;
    }

    public function update(UserData $userData) : bool
    {
        $affectedRows = (int) $this->db->manipulateF(
            "UPDATE " . self::TABLE_NAME . " SET matrix_user_id = %s, matrix_device_id = %s WHERE ilias_user_id = %s",
            ["text", "text", "integer"],
            [$userData->getMatrixUserId(), $userData->getDeviceId(), $userData->getIliasUserId()]
        );

        return $affectedRows === 1;
    }

    public function read(int $iliasUserId) : ?UserData
    {
        $result = $this->db->queryF(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE ilias_user_id = %s",
            ["integer"],
            [$iliasUserId]
        );

        $data = $this->db->fetchAssoc($result);

        if (!$data) {
            return null;
        }

        return new UserData(
            (int) $data["ilias_user_id"],
            $data["matrix_user_id"],
            $data["matrix_device_id"]
        );
    }
}
