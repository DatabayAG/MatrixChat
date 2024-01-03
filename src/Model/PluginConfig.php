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

namespace ILIAS\Plugin\MatrixChatClient\Model;

use Exception;
use ILIAS\Plugin\Libraries\IliasConfigLoader\Exception\ConfigLoadException;
use ILIAS\Plugin\Libraries\IliasConfigLoader\Model\Config\SettingsConfig;

/**
 * Class PluginConfig
 *
 * @package ILIAS\Plugin\MatrixChatClient\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class PluginConfig extends SettingsConfig
{
    private string $matrixServerUrl = "";
    private string $matrixAdminUsername = "";
    private string $matrixAdminPassword = "";
    private string $sharedSecret = "";
    private int $chatInitialLoadLimit = 20;
    private int $chatHistoryLoadLimit = 20;
    private string $usernameScheme = "";

    public function getMatrixServerUrl() : string
    {
        return $this->matrixServerUrl;
    }

    public function setMatrixServerUrl(string $matrixServerUrl) : PluginConfig
    {
        $this->matrixServerUrl = $matrixServerUrl;
        return $this;
    }

    public function getMatrixAdminUsername() : string
    {
        return $this->matrixAdminUsername;
    }

    public function setMatrixAdminUsername(string $matrixAdminUsername) : PluginConfig
    {
        $this->matrixAdminUsername = $matrixAdminUsername;
        return $this;
    }

    public function getMatrixAdminPassword() : string
    {
        return $this->matrixAdminPassword;
    }

    public function setMatrixAdminPassword(string $matrixAdminPassword) : PluginConfig
    {
        $this->matrixAdminPassword = $matrixAdminPassword;
        return $this;
    }

    public function getSharedSecret() : string
    {
        return $this->sharedSecret;
    }

    public function setSharedSecret(string $sharedSecret) : PluginConfig
    {
        $this->sharedSecret = $sharedSecret;
        return $this;
    }


    public function getChatInitialLoadLimit() : int
    {
        return $this->chatInitialLoadLimit;
    }

    public function setChatInitialLoadLimit(int $chatInitialLoadLimit) : PluginConfig
    {
        $this->chatInitialLoadLimit = $chatInitialLoadLimit;
        return $this;
    }


    public function getChatHistoryLoadLimit() : int
    {
        return $this->chatHistoryLoadLimit;
    }

    public function setChatHistoryLoadLimit(int $chatHistoryLoadLimit) : PluginConfig
    {
        $this->chatHistoryLoadLimit = $chatHistoryLoadLimit;
        return $this;
    }


    public function getUsernameScheme() : string
    {
        return $this->usernameScheme;
    }

    public function setUsernameScheme(string $usernameScheme) : PluginConfig
    {
        $this->usernameScheme = $usernameScheme;
        return $this;
    }

    /**
     * @throws Exception
     */
    public function save() : void
    {
        try {
            parent::save();
        } catch (ConfigLoadException $ex) {
            $errorsFound = 0;
            foreach ($ex->getUnloadableProperties() as $unloadableProperty) {
                switch ($unloadableProperty->getProperty()->getName()) {
                    default:
                        $errorsFound++;
                        break;
                }
            }

            if ($errorsFound > 0) {
                throw new Exception("general.update.failed");
            }
        }
    }
}
