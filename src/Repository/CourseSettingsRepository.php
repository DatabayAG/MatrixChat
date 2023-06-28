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
use ILIAS\Plugin\MatrixChatClient\Model\CourseSettings;

/**
 * Class CourseSettingsRepository
 *
 * @package ILIAS\Plugin\MatrixChatClient\Repository
 * @author  Marvin Beym <mbeym@databay.de>
 */
class CourseSettingsRepository
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
    protected const TABLE_NAME = "mcc_course_settings";

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

    /**
     * @return CourseSettings[]
     */
    public function readAll() : array
    {
        $result = $this->db->query("SELECT * FROM " . self::TABLE_NAME);

        $data = [];

        while ($row = $this->db->fetchAssoc($result)) {
            $data[] = (new CourseSettings((int) $row["course_id"], $row["matrix_room_id"]))
                ->setChatIntegrationEnabled((bool) $row["chat_integration_enabled"]);
        }

        return $data;
    }

    public function read(int $courseId) : CourseSettings
    {
        $result = $this->db->queryF(
            "SELECT * FROM " . self::TABLE_NAME . " WHERE course_id = %s",
            ["integer"],
            [$courseId]
        );

        if ($result->numRows() === 0) {
            return new CourseSettings($courseId);
        }

        $data = $this->db->fetchAssoc($result);
        return (new CourseSettings($courseId, $data["matrix_room_id"]))
            ->setChatIntegrationEnabled((bool) $data["chat_integration_enabled"]);
    }

    public function exists(int $courseId) : bool
    {
        $result = $this->db->queryF(
            "SELECT course_id FROM " . self::TABLE_NAME . " WHERE course_id = %s",
            ["integer"],
            [$courseId]
        );

        return $result->numRows() === 1;
    }

    public function save(CourseSettings $courseSettings) : bool
    {
        if ($this->exists($courseSettings->getCourseId())) {
            $affectedRows = (int) $this->db->manipulateF(
                "UPDATE " . self::TABLE_NAME . " SET chat_integration_enabled = %s, matrix_room_id = %s WHERE course_id = %s",
                [
                    "integer",
                    "text",
                    "integer"
                ],
                [
                    $courseSettings->isChatIntegrationEnabled(),
                    $courseSettings->getMatrixRoom() ? $courseSettings->getMatrixRoom()->getId() : null,
                    $courseSettings->getCourseId()
                ]
            );
            return $affectedRows === 1;
        }

        $affectedRows = (int) $this->db->manipulateF(
            "INSERT INTO " . self::TABLE_NAME . " (course_id, chat_integration_enabled, matrix_room_id) VALUES (%s, %s, %s)",
            ["integer", "integer", "text"],
            [$courseSettings->getCourseId(),
             $courseSettings->isChatIntegrationEnabled(),
             $courseSettings->getMatrixRoom() ? $courseSettings->getMatrixRoom()->getId() : null
            ]
        );
        return $affectedRows === 1;
    }
}
