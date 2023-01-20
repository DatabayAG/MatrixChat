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

use ILIAS\Plugin\MatrixChatClient\Model\MatrixUser;
use ILIAS\Plugin\MatrixChatClient\Model\MatrixRoom;
use Throwable;

/**
 * Class MatrixAdminApi
 *
 * @package ILIAS\Plugin\MatrixChatClient\Api
 * @author  Marvin Beym <mbeym@databay.de>
 */
class MatrixAdminApi extends MatrixApiEndpointBase
{
    /**
     * @var MatrixUser|null
     */
    private $adminUser;

    public function checkAdminUser() : bool
    {
        try {
            return $this->getUser() !== null;
        } catch (Throwable $ex) {
            return false;
        }
    }

    public function loginUserWithAdmin(int $iliasUserId, string $matrixUserId) : ?MatrixUser
    {
        try {
            $response = $this->sendRequest(
                "/_synapse/admin/v1/users/{$matrixUserId}/login",
                "POST",
                [],
                $this->getUser()->getAccessToken()
            );
        } catch (MatrixApiException $e) {
            return null;
        }

        return (new MatrixUser())
            ->setIliasUserId($iliasUserId)
            ->setMatrixUserId($matrixUserId)
            ->setMatrixUsername($matrixUserId) //ToDo: maybe can be removed/replaced with matrixUserId
            ->setMatrixDisplayName($this->getMatrixUserDisplayName($matrixUserId))
            ->setAccessToken($response["access_token"])
            ->setDeviceId("ilias_auth_verification");
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
            ->setDeviceId($deviceId);
    }

    private function getUser() : MatrixUser
    {
        if ($this->adminUser === null) {
            $this->adminUser = $this->login(
                $this->plugin->getPluginConfig()->getMatrixAdminUsername(),
                $this->plugin->getPluginConfig()->getMatrixAdminPassword(),
                "ilias_matrix_chat_device_admin"
            );
        }

        if (!$this->adminUser) {
            $this->adminUser = $this->login(
                $this->plugin->getPluginConfig()->getMatrixAdminUsername(),
                $this->plugin->getPluginConfig()->getMatrixAdminPassword(),
                "ilias_matrix_chat_device_admin"
            );
        }

        return $this->adminUser;
    }

    public function roomExists(string $matrixRoomId) : bool
    {
        return $this->getRoom($matrixRoomId) !== null;
    }

    public function getRoom(string $matrixRoomId) : ?MatrixRoom
    {
        try {
            $response = $this->sendRequest(
                "/_synapse/admin/v1/rooms/$matrixRoomId",
                "GET",
                [],
                $this->getUser()->getAccessToken()
            );
        } catch (MatrixApiException $e) {
            return null;
        }

        try {
            return (new MatrixRoom())
                ->setId($response["room_id"])
                ->setName($response["name"])
                ->setEncrypted($response["encryption"] !== null);
        } catch (Throwable $e) {
            $this->plugin->dic->logger()->root()->error($e->getMessage());
            return null;
        }
    }

    /**
     * @throws MatrixApiException
     */
    public function createRoom(string $name) : MatrixRoom
    {
        $response = $this->sendRequest(
            "/_matrix/client/v3/createRoom",
            "POST",
            [
                "name" => $name,
                "preset" => "private_chat"
            ],
            $this->getUser()->getAccessToken()
        );

        $matrixRoom = (new MatrixRoom())
            ->setId($response["room_id"])
            ->setName($name);

        $this->addUserToRoom($this->getUser(), $matrixRoom);
        return $matrixRoom;
    }

    public function deleteRoom(string $roomId) : bool
    {
        try {
            $response = $this->sendRequest(
                "/_synapse/admin/v1/rooms/$roomId",
                "DELETE",
                ["message" => "Deleted because course chat integration was disabled", "purge" => true, "block" => true],
                $this->getUser()->getAccessToken()
            );
        } catch (MatrixApiException $e) {
            return false;
        }

        return true;
    }

    /**
     * @param string $roomId
     * @return string[]
     */
    public function getRoomMembers(string $roomId) : array
    {
        try {
            $response = $this->sendRequest(
                "/_synapse/admin/v1/rooms/$roomId/members",
                "GET",
                [],
                $this->getUser()->getAccessToken()
            );
        } catch (MatrixApiException $e) {
            return [];
        }

        return $response["members"];
    }

    public function addUserToRoom(MatrixUser $matrixUser, MatrixRoom $matrixRoom) : bool
    {
        try {
            $response = $this->sendRequest(
                "/_synapse/admin/v1/join/{$matrixRoom->getId()}",
                "POST",
                [
                    "user_id" => $matrixUser->getMatrixUserId(),
                ],
                $this->getUser()->getAccessToken()
            );
        } catch (MatrixApiException $e) {
            //Todo: If admin user is not in room, he can't invite himself.
            //Todo: Find any user in the room. Login as that user (https://matrix-org.github.io/synapse/latest/admin_api/user_admin_api.html#login-as-a-user)
            //Todo: Then invite the admin user using that user.
            //Todo: Then make the admin user an admin using: https://matrix-org.github.io/synapse/latest/admin_api/rooms.html#make-room-admin-api
            return false;
        }

        return true;
    }

    public function isUserMemberOfRoom(MatrixUser $matrixUser, string $matrixRoomId) : bool
    {
        return in_array($matrixUser->getMatrixUserId(), $this->getRoomMembers($matrixRoomId), true);
    }

    public function userExists(string $matrixUserId) : bool
    {
        try {
            return $this->sendRequest(
                "/_synapse/admin/v2/users/$matrixUserId",
                "GET",
                [],
                $this->getUser()->getAccessToken()
            ) !== [];
        } catch (MatrixApiException $e) {
            return false;
        }
    }

    public function usernameAvailable(string $username) : bool
    {
        try {
            $response = $this->sendRequest(
                "/_synapse/admin/v1/username_available?username=$username",
                "GET",
                [],
                $this->getUser()->getAccessToken()
            );
            return $response["available"];
        } catch (MatrixApiException $e) {
            return false;
        }
    }

    public function retrieveNonce() : ?string
    {
        try {
            $response = $this->sendRequest(
                "/_synapse/admin/v1/register",
                "GET",
                [],
                $this->getUser()->getAccessToken()
            );
            return $response["nonce"];
        } catch (MatrixApiException $e) {
            return null;
        }
    }

    public function createUser(string $username, string $password, string $displayName) : ?MatrixUser
    {
        $nonce = $this->retrieveNonce();
        if (!$nonce) {
            return null;
        }

        if (!$this->plugin->getPluginConfig()->getSharedSecret()) {
            return null;
        }

        $hmac = hash_hmac(
            "sha1",
            "$nonce\0$username\0$password\0notadmin",
            $this->plugin->getPluginConfig()->getSharedSecret()
        );

        try {
            $response = $this->sendRequest(
                "/_synapse/admin/v1/register",
                "POST",
                [
                    "nonce" => $nonce,
                    "username" => $username,
                    "password" => $password,
                    "displayname" => $displayName,
                    "admin" => false,
                    "mac" => $hmac
                ],
                $this->getUser()->getAccessToken()
            );
        } catch (MatrixApiException $e) {
            return null;
        }

        return $this->login($username, $password, "ilias_auth_verification");
    }

    public function removeUserFromRoom(MatrixUser $matrixUser, MatrixRoom $room, string $reason) : bool
    {
        try {
            $response = $this->sendRequest(
                "/_matrix/client/v3/rooms/{$room->getId()}/kick",
                "POST",
                [
                    "reason" => $reason,
                    "user_id" => $matrixUser->getMatrixUserId(),
                ],
                $this->getUser()->getAccessToken()
            );
            return true;
        } catch (MatrixApiException $e) {
            return false;
        }
    }
}
