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

namespace ILIAS\Plugin\MatrixChatClient\Api;

use ILIAS\Plugin\MatrixChatClient\Model\Room\RoomInvite;
use ILIAS\Plugin\MatrixChatClient\Model\Room\RoomJoined;
use ILIAS\Plugin\MatrixChatClient\Model\Room\RoomModel;
use ILIAS\Plugin\MatrixChatClient\Model\MatrixUser;
use ILIAS\Plugin\MatrixChatClient\Repository\UserDeviceRepository;
use Exception;

/**
 * Class MatrixUserApi
 *
 * @package ILIAS\Plugin\MatrixChatClient\Api
 * @author  Marvin Beym <mbeym@databay.de>
 */
class MatrixUserApi extends MatrixApiEndpointBase
{
    /**
     * @throws Exception
     */
    public function loginUser(int $iliasUserId, string $username, string $password) : ?MatrixUser
    {
        $deviceId = UserDeviceRepository::getInstance()->read($iliasUserId);
        $matrixUser = $this->login($username, $password, $deviceId);

        if (!$matrixUser) {
            return null;
        }
        $matrixUser->setIliasUserId($iliasUserId);
        return $matrixUser;
    }

    public function login(string $username, string $password, string $deviceId) : ?MatrixUser
    {
        try {
            $response = $this->sendRequest("/_matrix/client/v3/login", "POST", [
                "type" => "m.login.password",
                "user" => $username,
                "password" => $password,
                "device_id" => $deviceId
            ]);
        } catch (MatrixApiException $e) {
            return null;
        }

        return (new MatrixUser())
            ->setMatrixUserId($response["user_id"])
            ->setMatrixUsername($username)
            ->setMatrixDisplayName($this->getMatrixUserDisplayName($response["user_id"]))
            ->setAccessToken($response["access_token"])
            ->setHomeServer($response["home_server"])
            ->setDeviceId($deviceId);
    }

    /**
     * @throws MatrixApiException
     */
    public function getState(MatrixUser $user) : array
    {
        return $this->sendRequest("/_matrix/client/v3/sync?access_token={$user->getAccessToken()}");
    }

    /**
     * @param MatrixUser $user
     * @param int        $courseId
     * @return RoomInvite|RoomJoined|null
     */
    public function getRoomByCourseId(MatrixUser $user, int $courseId) : ?RoomModel
    {
        //ToDo: maybe replace with a filter stored on the homeserver (setup on plugin installation)
        //ToDo: =>https://spec.matrix.org/v1.3/client-server-api/#post_matrixclientv3useruseridfilter

        $courseSettings = $this->courseSettingsRepo->read($courseId);
        if (!$courseSettings) {
            return null;
        }
        $roomId = $courseSettings->getMatrixRoomId();

        try {
            $data = $this->getState($user);
        } catch (MatrixApiException $e) {
            return null;
        }

        foreach ($data["rooms"] ?? ["join" => [], "invite" => [], "leave" => []] as $status => $rooms) {
            foreach ($rooms as $id => $roomData) {
                if ($id === $courseSettings->getMatrixRoomId()) {
                    switch ($status) {
                        case "invite":
                            return new RoomInvite($id);
                        case "join":
                            return (new RoomJoined($id))
                                ->setTimeline($roomData["timeline"])
                                ->setUnreadNotifications($roomData["unread_notifications"]);
                        default:
                            return null;
                    }
                }
            }
        }
        return null;
    }

    public function joinRoom(MatrixUser $user, RoomInvite $roomInvite) : bool
    {
        try {
            $result = $this->sendRequest(
                "/_matrix/client/v3/join/{$roomInvite->getRoomId()}",
                "POST",
                [],
                $user->getAccessToken()
            );
        } catch (MatrixApiException $e) {
            return false;
        }
        return $result["room_id"] === $roomInvite->getRoomId();
    }
}
