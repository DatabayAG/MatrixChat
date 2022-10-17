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

namespace ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Exception;

use Exception;

/**
 * Class ConfigLoadInvalidTypeException
 * @package ILIAS\Plugin\MatrixChatClient\Exception
 * @author  Marvin Beym <mbeym@databay.de>
 */
class ConfigLoadInvalidTypeException extends Exception
{
    /**
     * @var mixed
     */
    private $loadedType;
    /**
     * @var mixed
     */
    private $propertyType;

    /**
     * @param mixed $loadedType
     * @param mixed $propertyType
     */
    public function __construct($loadedType, $propertyType)
    {
        $this->loadedType = $loadedType;
        $this->propertyType = $propertyType;
        parent::__construct("Type of loaded value is different than the value of the property");
    }

    /**
     * @return mixed
     */
    public function getLoadedType()
    {
        return $this->loadedType;
    }

    /**
     * @return mixed
     */
    public function getPropertyType()
    {
        return $this->propertyType;
    }
}
