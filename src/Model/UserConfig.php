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

namespace ILIAS\Plugin\MatrixChatClient\Model;

use ilObjUser;
use ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Model\UserPrefConfig;

/**
 * Class UserConfig
 *
 * @package ILIAS\Plugin\MatrixChatClient\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class UserConfig extends UserPrefConfig
{
    /**
     * @var string
     */
    private $authMethod = "usingExternal";

    /**
     * @var string
     */
    private $matrixUsername = "";

    public function __construct(ilObjUser $user)
    {
        parent::__construct($user, "mcc_");
    }

    /**
     * @return string
     */
    public function getAuthMethod() : string
    {
        return $this->authMethod;
    }

    /**
     * @param string $authMethod
     * @return UserConfig
     */
    public function setAuthMethod(string $authMethod) : UserConfig
    {
        $this->authMethod = $authMethod;
        return $this;
    }

    /**
     * @return string
     */
    public function getMatrixUsername() : string
    {
        return $this->matrixUsername;
    }

    /**
     * @param string $matrixUsername
     * @return UserConfig
     */
    public function setMatrixUsername(string $matrixUsername) : UserConfig
    {
        $this->matrixUsername = $matrixUsername;
        return $this;
    }
}
