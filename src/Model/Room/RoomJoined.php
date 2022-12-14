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

namespace ILIAS\Plugin\MatrixChatClient\Model\Room;

/**
 * Class RoomJoined
 * @package ILIAS\Plugin\MatrixChatClient\Model
 * @author  Marvin Beym <mbeym@databay.de>
 */
class RoomJoined extends RoomModel
{
    private $unreadNotifications = ["notification_count" => 0, "highlight_count" => 0];
    private $timeline = [];

    /**
     * @return array{notification_count: int, highlight_count: int}
     */
    public function getUnreadNotifications() : array
    {
        return $this->unreadNotifications;
    }

    /**
     * @param array{notification_count: int, highlight_count: int} $unreadNotifications
     * @return RoomJoined
     */
    public function setUnreadNotifications(array $unreadNotifications) : RoomJoined
    {
        $this->unreadNotifications = $unreadNotifications;
        return $this;
    }

    /**
     * @return array
     */
    public function getTimeline() : array
    {
        return $this->timeline;
    }

    /**
     * @param array $timeline
     * @return RoomJoined
     */
    public function setTimeline(array $timeline) : RoomJoined
    {
        $this->timeline = $timeline;
        return $this;
    }
}
