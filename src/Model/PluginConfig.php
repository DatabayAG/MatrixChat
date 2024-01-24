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
    private string $externalUserScheme = "";
    private array $externalUserOptions = [];
    private string $localUserScheme = "";
    private array $localUserOptions = [];
    private string $roomPrefix = "";
    private array $activateChat = [];
    private string $pageDesignerText = "";
    private string $matrixSpaceId = "";
    private string $matrixSpaceName = "";
    private bool $enableRoomEncryption = false;
    private bool $modifyParticipantPowerLevel = false;
    private int $adminPowerLevel = 100;
    private int $tutorPowerLevel = 50;
    private int $memberPowerLevel = 0;

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

    public function getExternalUserScheme() : string
    {
        return $this->externalUserScheme;
    }

    public function setExternalUserScheme(string $externalUserScheme) : PluginConfig
    {
        $this->externalUserScheme = $externalUserScheme;
        return $this;
    }

    public function getLocalUserScheme() : string
    {
        return $this->localUserScheme;
    }

    public function setLocalUserScheme(string $localUserScheme) : PluginConfig
    {
        $this->localUserScheme = $localUserScheme;
        return $this;
    }

    public function getRoomPrefix() : string
    {
        return $this->roomPrefix;
    }

    public function setRoomPrefix(string $roomPrefix) : PluginConfig
    {
        $this->roomPrefix = $roomPrefix;
        return $this;
    }

    public function getExternalUserOptions() : array
    {
        return $this->externalUserOptions;
    }

    public function setExternalUserOptions(array $externalUserOptions) : PluginConfig
    {
        $this->externalUserOptions = $externalUserOptions;
        return $this;
    }

    public function getLocalUserOptions() : array
    {
        return $this->localUserOptions;
    }

    public function setLocalUserOptions(array $localUserOptions) : PluginConfig
    {
        $this->localUserOptions = $localUserOptions;
        return $this;
    }

    public function getActivateChat() : array
    {
        return $this->activateChat;
    }

    public function setActivateChat(array $activateChat) : PluginConfig
    {
        $this->activateChat = $activateChat;
        return $this;
    }

    public function getPageDesignerText() : string
    {
        return $this->pageDesignerText;
    }

    public function setPageDesignerText(string $pageDesignerText) : PluginConfig
    {
        $this->pageDesignerText = $pageDesignerText;
        return $this;
    }

    public function getMatrixSpaceId() : string
    {
        return $this->matrixSpaceId;
    }

    public function setMatrixSpaceId(string $matrixSpaceId) : PluginConfig
    {
        $this->matrixSpaceId = $matrixSpaceId;
        return $this;
    }

    public function getMatrixSpaceName() : string
    {
        return $this->matrixSpaceName;
    }

    public function setMatrixSpaceName(string $matrixSpaceName) : PluginConfig
    {
        $this->matrixSpaceName = $matrixSpaceName;
        return $this;
    }

    public function isEnableRoomEncryption() : bool
    {
        return $this->enableRoomEncryption;
    }

    public function setEnableRoomEncryption(bool $enableRoomEncryption) : PluginConfig
    {
        $this->enableRoomEncryption = $enableRoomEncryption;
        return $this;
    }

    public function isModifyParticipantPowerLevel() : bool
    {
        return $this->modifyParticipantPowerLevel;
    }

    public function setModifyParticipantPowerLevel(bool $modifyParticipantPowerLevel) : PluginConfig
    {
        $this->modifyParticipantPowerLevel = $modifyParticipantPowerLevel;
        return $this;
    }

    public function getAdminPowerLevel() : int
    {
        return $this->adminPowerLevel;
    }

    public function setAdminPowerLevel(int $adminPowerLevel) : PluginConfig
    {
        $this->adminPowerLevel = $adminPowerLevel;
        return $this;
    }

    public function getTutorPowerLevel() : int
    {
        return $this->tutorPowerLevel;
    }

    public function setTutorPowerLevel(int $tutorPowerLevel) : PluginConfig
    {
        $this->tutorPowerLevel = $tutorPowerLevel;
        return $this;
    }

    public function getMemberPowerLevel() : int
    {
        return $this->memberPowerLevel;
    }

    public function setMemberPowerLevel(int $memberPowerLevel) : PluginConfig
    {
        $this->memberPowerLevel = $memberPowerLevel;
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

    public function getMatrixServerName() : string
    {
        if (!$this->getMatrixServerUrl()) {
            return "";
        }
        $url = parse_url($this->getMatrixServerUrl());
        return $url["host"] ?? "";
    }
}
