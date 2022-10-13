<?php declare(strict_types=1);
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

namespace ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Model;

use ReflectionProperty;

/**
 * Class LoadableProperty
 * @package ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class LoadableProperty
{
    /**
     * @var ReflectionProperty
     */
    private $property;
    /**
     * @var string
     */
    private $type;

    public function getProperty() : ReflectionProperty
    {
        return $this->property;
    }

    public function setProperty(ReflectionProperty $property) : self
    {
        $this->property = $property;
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
}
