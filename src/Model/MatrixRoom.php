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

class MatrixRoom
{
    private string $id;
    private string $name;

    /**
     * @var string[]
     */
    private array $members;
    private ilMatrixChatClientPlugin $plugin;

    /**
     * @param string[] $members
     */
    public function __construct(string $id, string $name, array $members)
    {
        $this->id = $id;
        $this->name = $name;
        $this->members = $members;
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
    }


    public function getId(): string
    {
        return $this->id;
    }


    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string[]
     */
    public function getMembers(): array
    {
        return $this->members;
    }

    /**
     * @param string[] $members
     */
    public function setMembers(array $members): MatrixRoom
    {
        $this->members = $members;
        return $this;
    }

    /**
     * @param MatrixUser|string $matrixUserOrId
     * @return bool
     */
    public function isMember($matrixUserOrId): bool
    {
        $matrixUserID = $matrixUserOrId instanceof MatrixUser ? $matrixUserOrId->getId() : $matrixUserOrId;
        return in_array($matrixUserID, $this->getMembers(), true);
    }

    public function exists(): bool
    {
        return (bool) $this->plugin->getMatrixApi()->getRoom($this->getId());
    }
}
