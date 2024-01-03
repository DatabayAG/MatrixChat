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

namespace ILIAS\Plugin\MatrixChatClient\Model;

use ilMatrixChatClientPlugin;

/**
 * Class MatrixRoom
 *
 * @package ILIAS\Plugin\MatrixChatClient\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class MatrixRoom
{
    /**
     * @var string
     */
    private $id;
    /**
     * @var string
     */
    private $name;
    /**
     * @var bool
     */
    private $encrypted;
    /**
     * @var string[]
     */
    private $members;
    /**
     * @var ilMatrixChatClientPlugin
     */
    private $plugin;

    /**
     * @param string   $id
     * @param string   $name
     * @param bool     $encrypted
     * @param string[] $members
     */
    public function __construct(string $id, string $name, bool $encrypted, array $members)
    {
        $this->id = $id;
        $this->name = $name;
        $this->encrypted = $encrypted;
        $this->members = $members;
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
    }

    /**
     * @return string
     */
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function isEncrypted() : bool
    {
        return $this->encrypted;
    }

    /**
     * @return string[]
     */
    public function getMembers() : array
    {
        return $this->members;
    }

    /**
     * @param string[] $members
     * @return MatrixRoom
     */
    public function setMembers(array $members) : MatrixRoom
    {
        $this->members = $members;
        return $this;
    }

    public function isMember(MatrixUser $matrixUser) : bool
    {
        return in_array($matrixUser->getMatrixUserId(), $this->getMembers(), true);
    }

    public function exists() : bool
    {
        return $this->plugin->getMatrixCommunicator()->admin->roomExists($this->getId());
    }
}
