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
    private string $id;
    private string $displayName;
    private string $accessToken = "";
    private string $deviceId = "ilias_auth_verification";

    /**
     * @param string $id
     * @param string $displayName
     */
    public function __construct(string $id, string $displayName)
    {
        $this->id = $id;
        $this->displayName = $displayName;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
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
