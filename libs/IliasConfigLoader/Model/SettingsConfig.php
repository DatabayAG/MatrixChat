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

use ilSetting;

/**
 * Class SettingsConfig
 *
 * @package ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class SettingsConfig extends ConfigBase
{
    /**
     * @var ilSetting
     */
    private $settings;

    public function __construct(ilSetting $settings, string $settingsPrefix = "config_")
    {
        $this->settings = $settings;
        parent::__construct($settingsPrefix);
    }

    protected function saveSingleValue(string $key, $value) : void
    {
        $this->settings->set($key, $value);
    }

    protected function loadSingleValue(string $key, ?string $defaultValue) : ?string
    {
        return $this->settings->get(
            $key,
            $defaultValue
        );
    }

    protected function cleanSingleValue(string $key) : bool
    {
        return (bool) $this->settings->delete($key);
    }

    protected function getIgnoredPropertyNames() : array
    {
        return [
            "settings"
        ];
    }
}
