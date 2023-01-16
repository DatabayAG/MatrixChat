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
use ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Exception\ConfigLoadException;
use ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Model\SettingsConfig;

/**
 * Class PluginConfig
 *
 * @package ILIAS\Plugin\MatrixChatClient\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class PluginConfig extends SettingsConfig
{
    /**
     * @var string
     */
    private $matrixServerUrl = "";
    /**
     * @var string
     */
    private $matrixAdminUsername = "";
    /**
     * @var string
     */
    private $matrixAdminPassword = "";
    /**
     * @var string
     */
    private $sharedSecret = "";
    /**
     * @var int
     */
    private $chatInitialLoadLimit = 20;
    /**
     * @var int
     */
    private $chatHistoryLoadLimit = 20;

    private $usernameScheme = "";

    /**
     * @return string
     */
    public function getMatrixServerUrl() : string
    {
        return $this->matrixServerUrl;
    }

    /**
     * @param string $matrixServerUrl
     * @return PluginConfig
     */
    public function setMatrixServerUrl(string $matrixServerUrl) : PluginConfig
    {
        $this->matrixServerUrl = $matrixServerUrl;
        return $this;
    }

    /**
     * @return string
     */
    public function getMatrixAdminUsername() : string
    {
        return $this->matrixAdminUsername;
    }

    /**
     * @param string $matrixAdminUsername
     * @return PluginConfig
     */
    public function setMatrixAdminUsername(string $matrixAdminUsername) : PluginConfig
    {
        $this->matrixAdminUsername = $matrixAdminUsername;
        return $this;
    }

    /**
     * @return string
     */
    public function getMatrixAdminPassword() : string
    {
        return $this->matrixAdminPassword;
    }

    /**
     * @param string $matrixAdminPassword
     * @return PluginConfig
     */
    public function setMatrixAdminPassword(string $matrixAdminPassword) : PluginConfig
    {
        $this->matrixAdminPassword = $matrixAdminPassword;
        return $this;
    }

    /**
     * @return string
     */
    public function getSharedSecret() : string
    {
        return $this->sharedSecret;
    }

    /**
     * @param string $sharedSecret
     * @return PluginConfig
     */
    public function setSharedSecret(string $sharedSecret) : PluginConfig
    {
        $this->sharedSecret = $sharedSecret;
        return $this;
    }

    /**
     * @return int
     */
    public function getChatInitialLoadLimit() : int
    {
        return $this->chatInitialLoadLimit;
    }

    /**
     * @param int $chatInitialLoadLimit
     * @return PluginConfig
     */
    public function setChatInitialLoadLimit(int $chatInitialLoadLimit) : PluginConfig
    {
        $this->chatInitialLoadLimit = $chatInitialLoadLimit;
        return $this;
    }

    /**
     * @return int
     */
    public function getChatHistoryLoadLimit() : int
    {
        return $this->chatHistoryLoadLimit;
    }

    /**
     * @param int $chatHistoryLoadLimit
     * @return PluginConfig
     */
    public function setChatHistoryLoadLimit(int $chatHistoryLoadLimit) : PluginConfig
    {
        $this->chatHistoryLoadLimit = $chatHistoryLoadLimit;
        return $this;
    }

    /**
     * @return string
     */
    public function getUsernameScheme() : string
    {
        return $this->usernameScheme;
    }

    /**
     * @param string $usernameScheme
     * @return PluginConfig
     */
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
