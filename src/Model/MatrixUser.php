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

/**
 * Class MatrixUser
 *
 * @package ILIAS\Plugin\MatrixChatClient\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class MatrixUser
{
    private string $matrixUserId;
    private string $matrixDisplayName;
    private string $accessToken = "";
    private string $deviceId = "ilias_auth_verification";

    /**
     * @param string $matrixUserId
     * @param string $matrixDisplayName
     */
    public function __construct(string $matrixUserId, string $matrixDisplayName)
    {
        $this->matrixUserId = $matrixUserId;
        $this->matrixDisplayName = $matrixDisplayName;
    }

    public function getMatrixUserId(): string
    {
        return $this->matrixUserId;
    }

    public function getMatrixDisplayName(): string
    {
        return $this->matrixDisplayName;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function setAccessToken(string $accessToken): MatrixUser
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    public function getDeviceId(): string
    {
        return $this->deviceId;
    }

    public function setDeviceId(string $deviceId): MatrixUser
    {
        $this->deviceId = $deviceId;
        return $this;
    }
}
