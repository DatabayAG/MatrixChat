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

namespace ILIAS\Plugin\MatrixChatClient\Libs\UserField;

use ILIAS\Plugin\MatrixChatClient\Libs\UserField\Model\UserField;
use ilUserProfile;
use ilUserDefinedFields;
use ilDBInterface;

/**
 * Class UserFieldLoader
 *
 * @author Marvin Beym <mbeym@databay.de>
 */
class UserFieldLoader
{
    /**
     * @var UserFieldLoader
     */
    private static $instance;
    /**
     * @var UserField[]|null
     */
    private $userFields;
    /**
     * @var ilDBInterface
     */
    private $db;

    public function __construct(ilDBInterface $db = null)
    {
        if (!$db) {
            global $DIC;
            $this->db = $DIC->database();
        }
    }

    public static function getInstance(?ilDBInterface $db = null) : self
    {
        if (self::$instance) {
            return self::$instance;
        }
        return self::$instance = new self($db);
    }

    /**
     * @return UserField[]
     */
    public function getAllUserFields() : array
    {
        if ($this->userFields !== null) {
            return $this->userFields;
        }

        $up = new ilUserProfile();
        $user_field_definitions = ilUserDefinedFields::_getInstance();

        $userFields = [];

        global $DIC;
        $lng = $DIC->language();
        $lng->loadLanguageModule("user");
        $lng->loadLanguageModule("mail");
        $lng->loadLanguageModule("chatroom");

        foreach ($up->getStandardFields() as $name => $field) {
            if ($field["input"] !== "text") {
                continue;
            }
            $userFields[] = (new UserField())
                ->setId((string) $name)
                ->setName($lng->txt($field["lang_var"] ?? $name))
                ->setType($field["input"]);
        }

        foreach ($user_field_definitions->getDefinitions() as $field) {
            $userFields[] = (new UserField())
                ->setName((string) $field["field_name"])
                ->setId((string) $field["field_id"])
                ->setCustom(true);
        }

        $this->userFields = $userFields;
        return $this->userFields;
    }

    public function getAllUserFieldsAsOptions() : array
    {
        $options = [];
        foreach ($this->getAllUserFields() as $field) {
            $options[$field->getId()] = $field->getName();
        }
        return $options;
    }

    public function getUserFieldById(string $fieldId) : ?UserField
    {
        foreach ($this->getAllUserFields() as $field) {
            if ($field->getId() === $fieldId) {
                return $field;
            }
        }
        return null;
    }

    public function getUserFieldForUser(int $userId, string $fieldId) : ?UserField
    {
        $field = $this->getUserFieldById($fieldId);
        if ($field === null) {
            return null;
        }

        foreach ($this->getAllUserFieldsForUser($userId) as $userField) {
            if ($userField->getId() === $fieldId) {
                return $userField;
            }
        }

        return null;
    }

    /**
     * @param int $userId
     * @return UserField[]
     */
    public function getAllCustomUserFieldsForUser(int $userId) : array
    {
        $userFields = [];

        /**
         * @var UserField[] $fields
         */
        $fields = array_values(array_filter($this->getAllUserFields(), static function (UserField $field) : bool {
            return $field->isCustom();
        }));

        $sqlString = "";

        foreach ($fields as $index => $field) {
            if ($index === count($fields) - 1) {
                $sqlString .= $this->db->quote($field->getId(), "integer");
            } else {
                $sqlString .= "{$this->db->quote($field->getId(), "integer")}, ";
            }
        }

        $result = $this->db->queryF(
            "SELECT field_id, value FROM udf_text WHERE usr_id = %s AND usr_id != %s AND field_id IN ($sqlString)",
            ["integer", "integer"],
            [$userId, ANONYMOUS_USER_ID]
        );

        while ($data = $this->db->fetchAssoc($result)) {
            $userFields[] = (new UserField())
                ->setId($data["field_id"])
                ->setValue($data["value"] ?? "")
                ->setCustom(true);
        }
        return $userFields;
    }

    /**
     * @param int $userId
     * @return UserField[]
     */
    public function getAllIliasUserFieldsForUser(int $userId) : array
    {
        $userFields = [];
        $sqlString = "";

        /**
         * @var UserField[] $fields
         */
        $fields = array_values(array_filter($this->getAllUserFields(), static function (UserField $field) : bool {
            return !$field->isCustom();
        }));

        foreach ($fields as $index => $field) {
            $fieldId = str_replace("sel_", "", $field->getId());
            if ($fieldId === "username") {
                $fieldId = "login";
            }

            if ($index === count($fields) - 1) {
                $sqlString .= $fieldId;
            } else {
                $sqlString .= "$fieldId, ";
            }
        }

        $result = $this->db->queryF(
            "SELECT $sqlString FROM usr_data WHERE usr_id = %s AND usr_id != %s",
            ["integer", "integer"],
            [$userId, ANONYMOUS_USER_ID]
        );

        $data = $this->db->fetchAssoc($result);

        foreach ($data as $fieldId => $value) {
            $userFields[] = (new UserField())
                ->setId($fieldId)
                ->setValue($value ?? "");
        }

        return $userFields;
    }

    public function getAllUserFieldsForUser(int $userId) : array
    {
        return array_merge(
            $this->getAllIliasUserFieldsForUser($userId),
            $this->getAllCustomUserFieldsForUser($userId)
        );
    }
}
