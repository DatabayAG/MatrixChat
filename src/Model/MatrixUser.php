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

use JsonSerializable;
use ReflectionClass;

/**
 * Class MatrixUser
 *
 * @package ILIAS\Plugin\MatrixChatClient\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class MatrixUser implements JsonSerializable
{
    /**
     * @var int
     */
    private $iliasUserId;
    /**
     * @var string
     */
    private $matrixUserId;
    /**
     * @var string
     */
    private $accessToken;
    /**
     * @var string
     */
    private $homeServer;
    /**
     * @var string
     */
    private $deviceId;
    /**
     * @var string
     */
    private $matrixUsername;
    /**
     * @var string
     */
    private $matrixDisplayName;

    /**
     * @return int
     */
    public function getIliasUserId() : int
    {
        return $this->iliasUserId;
    }

    /**
     * @param int $iliasUserId
     * @return MatrixUser
     */
    public function setIliasUserId(int $iliasUserId) : MatrixUser
    {
        $this->iliasUserId = $iliasUserId;
        return $this;
    }

    /**
     * @return string
     */
    public function getMatrixUserId() : string
    {
        return $this->matrixUserId;
    }

    /**
     * @param string $matrixUserId
     * @return MatrixUser
     */
    public function setMatrixUserId(string $matrixUserId) : MatrixUser
    {
        $this->matrixUserId = $matrixUserId;
        return $this;
    }

    /**
     * @return string
     */
    public function getAccessToken() : string
    {
        return $this->accessToken;
    }

    /**
     * @param string $accessToken
     * @return MatrixUser
     */
    public function setAccessToken(string $accessToken) : MatrixUser
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    /**
     * @return string
     */
    public function getHomeServer() : string
    {
        return $this->homeServer;
    }

    /**
     * @param string $homeServer
     * @return MatrixUser
     */
    public function setHomeServer(string $homeServer) : MatrixUser
    {
        $this->homeServer = $homeServer;
        return $this;
    }

    /**
     * @return string
     */
    public function getDeviceId() : string
    {
        return $this->deviceId;
    }

    /**
     * @param string $deviceId
     * @return MatrixUser
     */
    public function setDeviceId(string $deviceId) : MatrixUser
    {
        $this->deviceId = $deviceId;
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
     * @return MatrixUser
     */
    public function setMatrixUsername(string $matrixUsername) : MatrixUser
    {
        $this->matrixUsername = $matrixUsername;
        return $this;
    }

    /**
     * @return string
     */
    public function getMatrixDisplayName() : string
    {
        return $this->matrixDisplayName;
    }

    /**
     * @param string $matrixDisplayName
     * @return MatrixUser
     */
    public function setMatrixDisplayName(string $matrixDisplayName) : MatrixUser
    {
        $this->matrixDisplayName = $matrixDisplayName;
        return $this;
    }

    public function jsonSerialize() : array
    {
        $refClass = new ReflectionClass($this);

        $data = [];
        foreach ($refClass->getProperties() as $property) {
            $property->setAccessible(true);
            $data[$property->getName()] = $property->getValue($this);
        }

        return $data;
    }

    public static function createFromJson(array $json) : ?MatrixUser
    {
        $matrixUser = new self();

        $refClass = new ReflectionClass($matrixUser);
        foreach ($refClass->getProperties() as $property) {
            if ($json[$property->getName()] === null) {
                return null;
            }
            $property->setAccessible(true);
            $property->setValue($matrixUser, $json[$property->getName()]);
        }
        return $matrixUser;
    }
}
