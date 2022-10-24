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
    private $encrypted = false;

    /**
     * @return string
     */
    public function getId() : string
    {
        return $this->id;
    }

    /**
     * @param string $id
     * @return MatrixRoom
     */
    public function setId(string $id) : MatrixRoom
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return MatrixRoom
     */
    public function setName(string $name) : MatrixRoom
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return bool
     */
    public function isEncrypted() : bool
    {
        return $this->encrypted;
    }

    /**
     * @param bool $encrypted
     * @return MatrixRoom
     */
    public function setEncrypted(bool $encrypted) : MatrixRoom
    {
        $this->encrypted = $encrypted;
        return $this;
    }
}
