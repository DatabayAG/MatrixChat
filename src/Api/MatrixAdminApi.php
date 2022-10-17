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

    /**
     * Optional: Other api calls will login automatically and remember the admin user for future use
     *
     * @return MatrixUser|null
     */
    public function login() : ?MatrixUser
    {
        try {
            $response = $this->sendRequest("/_matrix/client/v3/login", "POST", [
                "type" => "m.login.password",
                "user" => $this->plugin->getPluginConfig()->getMatrixAdminUsername(),
                "password" => $this->plugin->getPluginConfig()->getMatrixAdminPassword()
            ]);
        } catch (MatrixApiException $e) {
            return null;
        }

        return (new MatrixUser())
            ->setId($response["user_id"])
            ->setAccessToken($response["access_token"])
            ->setHomeServer($response["home_server"])
            ->setDeviceId($response["device_id"]);
    }

    public function checkAdminUser() : bool
    {
        return $this->getUser() !== null;
    }

    private function getUser() : MatrixUser
    {
        if ($this->adminUser === null) {
            $this->adminUser = $this->login();
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

        return (new MatrixRoom())
            ->setId($response["room_id"])
            ->setName($response["name"]);
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

        return (new MatrixRoom())
            ->setId($response["room_id"])
            ->setName($name);
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
}
