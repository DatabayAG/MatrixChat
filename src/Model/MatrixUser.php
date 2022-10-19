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
    private $device_id;

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
        return $this->device_id;
    }

    /**
     * @param string $device_id
     * @return MatrixUser
     */
    public function setDeviceId(string $device_id) : MatrixUser
    {
        $this->device_id = $device_id;
        return $this;
    }

    public function jsonSerialize() : array
    {
        return [
            "iliasUserId" => $this->getIliasUserId(),
            "matrixUserId" => $this->getMatrixUserId(),
            "accessToken" => $this->getAccessToken(),
            "homeServer" => $this->getHomeServer(),
            "deviceId" => $this->getDeviceId()
        ];
    }
}
