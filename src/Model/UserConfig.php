<?php

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

declare(strict_types=1);

namespace ILIAS\Plugin\MatrixChat\Model;

use Exception;
use ILIAS\Plugin\Libraries\IliasConfigLoader\Exception\ConfigLoadException;
use ILIAS\Plugin\Libraries\IliasConfigLoader\Model\Config\UserPrefConfig;
use ilObjUser;

class UserConfig extends UserPrefConfig
{
    private string $authMethod = "";
    private string $matrixUserId = "";

    public function __construct(ilObjUser $user)
    {
        parent::__construct($user, "mcc_");
    }

    public function getAuthMethod(): string
    {
        return $this->authMethod;
    }

    public function setAuthMethod(string $authMethod): UserConfig
    {
        $this->authMethod = $authMethod;
        return $this;
    }

    public function getMatrixUserId(): string
    {
        return $this->matrixUserId;
    }

    public function setMatrixUserId(string $matrixUserId): UserConfig
    {
        $this->matrixUserId = $matrixUserId;
        return $this;
    }

    public function save(): void
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
