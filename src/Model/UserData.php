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

/**
 * Class UserData
 *
 * @package ILIAS\Plugin\MatrixChatClient\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class UserData
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
    private $deviceId;

    public function __construct(int $iliasUserId, string $matrixUserId, string $deviceId)
    {
        $this->iliasUserId = $iliasUserId;
        $this->matrixUserId = $matrixUserId;
        $this->deviceId = $deviceId;
    }

    public function getIliasUserId() : int
    {
        return $this->iliasUserId;
    }

    public function getMatrixUserId() : string
    {
        return $this->matrixUserId;
    }

    public function setMatrixUserId(string $matrixUserId) : self
    {
        $this->matrixUserId = $matrixUserId;
        return $this;
    }

    public function getDeviceId() : string
    {
        return $this->deviceId;
    }
}
