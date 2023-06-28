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

namespace ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Model;

/**
 * Class Properties
 * @package ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class Properties
{
    /**
     * @var LoadableProperty[]
     */
    private $loadable = [];
    /**
     * @var UnloadableProperty[]
     */
    private $unloadable = [];

    /**
     * @return LoadableProperty[]
     */
    public function getLoadable() : array
    {
        return $this->loadable;
    }

    /**
     * @param LoadableProperty[] $loadable
     */
    public function setLoadable(array $loadable) : void
    {
        $this->loadable = $loadable;
    }

    /**
     * @return UnloadableProperty[]
     */
    public function getUnloadable() : array
    {
        return $this->unloadable;
    }

    /**
     * @param UnloadableProperty[] $unloadable
     */
    public function setUnloadable(array $unloadable) : void
    {
        $this->unloadable = $unloadable;
    }

    public function addLoadable(LoadableProperty $loadable) : void
    {
        $this->loadable[] = $loadable;
    }

    public function addUnloadable(UnloadableProperty $unloadable) : void
    {
        $this->unloadable[] = $unloadable;
    }

    public function hasLoadable() : bool
    {
        return $this->loadable !== [];
    }

    public function hasUnloadable() : bool
    {
        return $this->unloadable !== [];
    }
}
