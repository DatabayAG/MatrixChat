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
use ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Model\UnloadableProperty;

/**
 * Class ConfigLoadException
 * @package ILIAS\Plugin\ChatClientInterface\Exception
 * @author  Marvin Beym <mbeym@databay.de>
 */
class ConfigLoadException extends Exception
{
    /**
     * @var UnloadableProperty[]
     */
    private $unloadableProperties;

    public function __construct(array $unloadableProperties)
    {
        $this->unloadableProperties = $unloadableProperties;
        parent::__construct("Unloadable properties detected");
    }

    /**
     * @return UnloadableProperty[]
     */
    public function getUnloadableProperties() : array
    {
        return $this->unloadableProperties;
    }
}
