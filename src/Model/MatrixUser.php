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

class MatrixUser
{
    private string $id;
    private string $displayName;
    private string $accessToken = "";
    private bool $exists;
    private string $deviceId = "ilias_auth_verification";

    public function __construct(string $id, string $displayName, bool $exists)
    {
        $this->id = $id;
        $this->displayName = $displayName;
        $this->exists = $exists;
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

    public function isExists(): bool
    {
        return $this->exists;
    }
    public function setDeviceId(string $deviceId): MatrixUser
    {
        $this->deviceId = $deviceId;
        return $this;
    }
}
