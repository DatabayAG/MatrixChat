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
 * Class UserRoomAddQueue
 *
 * @package ILIAS\Plugin\MatrixChatClient\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class UserRoomAddQueue
{
    private int $userId;
    private int $refId;

    public function __construct($userId, $refId)
    {
        $this->userId = $userId;
        $this->refId = $refId;
    }


    public function getUserId(): int
    {
        return $this->userId;
    }


    public function getRefId(): int
    {
        return $this->refId;
    }
}
