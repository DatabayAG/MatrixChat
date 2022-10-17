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

namespace ILIAS\Plugin\MatrixChatClient\Model;

use Exception;
use ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Model\ConfigBase;
use ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Exception\ConfigLoadException;

/**
 * Class PluginConfig
 *
 * @package ILIAS\Plugin\MatrixChatClient\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class PluginConfig extends ConfigBase
{
    /**
     * @var string
     */
    private $matrixApiUrl = "";
    /**
     * @var string
     */
    private $matrixAdminUsername = "";
    /**
     * @var string
     */
    private $matrixAdminPassword = "";

    /**
     * @return string
     */
    public function getMatrixApiUrl() : string
    {
        return $this->matrixApiUrl;
    }

    /**
     * @param string $matrixApiUrl
     * @return PluginConfig
     */
    public function setMatrixApiUrl(string $matrixApiUrl) : PluginConfig
    {
        $this->matrixApiUrl = $matrixApiUrl;
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