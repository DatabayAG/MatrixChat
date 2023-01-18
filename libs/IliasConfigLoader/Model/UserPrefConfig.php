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

namespace ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Model;

use ilObjUser;

/**
 * Class UserConfig
 *
 * @package ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class UserPrefConfig extends ConfigBase
{
    /**
     * @var ilObjUser
     */
    protected $user;

    public function __construct(ilObjUser $user, string $settingsPrefix)
    {
        $this->user = $user;
        parent::__construct($settingsPrefix);
    }

    protected function saveSingleValue(string $key, $value) : void
    {
        $this->user->writePref($key, $value);
    }

    protected function loadSingleValue(string $key, ?string $defaultValue) : ?string
    {
        $pref = $this->user->getPref($key);

        return $pref ?: $defaultValue;
    }

    protected function cleanSingleValue(string $key) : bool
    {
        $this->user->deletePref($key);
        return true;
    }

    protected function getIgnoredPropertyNames() : array
    {
        return [
            "user"
        ];
    }
}
