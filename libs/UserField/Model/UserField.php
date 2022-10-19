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

namespace ILIAS\Plugin\MatrixChatClient\Libs\UserField\Model;

/**
 * Class UserField
 *
 * @package ILIAS\Plugin\MatrixChatClient\Libs\UserField\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class UserField
{
    /**
     * @var string
     */
    private $name = "";
    /**
     * @var string
     */
    private $id = "";
    /**
     * @var bool
     */
    private $custom = false;
    /**
     * @var string
     */
    private $type;
    /**
     * @var string
     */
    private $value;

    public function getName() : string
    {
        return $this->name;
    }

    public function setName(string $name) : UserField
    {
        $this->name = $name;
        return $this;
    }

    public function getId() : string
    {
        return $this->id;
    }

    public function setId(string $id) : UserField
    {
        $this->id = $id;
        return $this;
    }

    public function isCustom() : bool
    {
        return $this->custom;
    }

    public function setCustom(bool $custom) : self
    {
        $this->custom = $custom;
        return $this;
    }

    public function getType() : string
    {
        return $this->type;
    }

    public function setType(string $type) : self
    {
        $this->type = $type;
        return $this;
    }

    public function getValue() : string
    {
        return $this->value;
    }

    public function setValue(string $value) : self
    {
        $this->value = $value;
        return $this;
    }
}
