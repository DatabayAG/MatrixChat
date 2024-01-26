<?php

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

declare(strict_types=1);

namespace ILIAS\Plugin\MatrixChatClient\Api;

use Exception;
use ILIAS\Plugin\MatrixChatClient\Model\MatrixRoom;
use ILIAS\Plugin\MatrixChatClient\Model\MatrixUser;
use ILIAS\Plugin\MatrixChatClient\Model\MatrixUserPowerLevel;
use ILIAS\Plugin\MatrixChatClient\Model\Room\MatrixSpace;
use ilLogger;
use ilMatrixChatClientPlugin;
use JsonException;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Class MatrixApi
 *
 * @package ILIAS\Plugin\MatrixChatClient\Api
 * @author  Marvin Beym <mbeym@databay.de>
 */
class MatrixApi
{
    private static ?MatrixUser $adminUser = null;
    private static ?MatrixUser $restApiUser = null;
    private HttpClientInterface $client;
    private ilMatrixChatClientPlugin $plugin;
    private string $matrixServerUrl;
    private float $requestTimeout;
    private ilLogger $logger;

    public function __construct(
        string $matrixServerUrl,
        float $requestTimeout = 3,
        ?ilMatrixChatClientPlugin $plugin = null,
        ?ilLogger $logger = null
    ) {
        $this->client = HttpClient::create();
        $this->matrixServerUrl = $matrixServerUrl;
        $this->requestTimeout = $requestTimeout;

        if (!$plugin) {
            $plugin = ilMatrixChatClientPlugin::getInstance();
        }
        $this->plugin = $plugin;

        if (!$logger) {
            global $DIC;
            $logger = $DIC->logger()->root();
        }
        $this->logger = $logger;
    }

    /**
     * @throws MatrixApiException
     */
    protected function sendRequest(
        string $apiCall,
        bool $requiresAuth = true,
        string $method = "GET",
        array $body = [],
        bool $useRestApiUserAuth = false
    ): MatrixApiResponse {
        $options = [
            "timeout" => $this->requestTimeout
        ];

        if ($requiresAuth) {
            $accessToken = $useRestApiUserAuth ? $this->getRestApiUserAccessToken() : $this->getAdminAccessToken();

            if (!$accessToken) {
                //Don't log error when request send is trying to login the admin
                //(As it's already clear the access token won't be available yet)
                //In theory should never get called because login does not require prior auth,
                if (
                    $apiCall === "/_matrix/client/v3/login"
                    || !isset($body["device_id"])
                    || $body["device_id"] !== "ilias_matrix_chat_device_bot"
                ) {
                    $this->logError($apiCall, "Access token missing but required");
                    throw new MatrixApiException(
                        "ADMIN_AUTH_ERROR",
                        "Missing admin access token. Login probably failed."
                    );
                }
            }
            $options["auth_bearer"] = $accessToken;
        }

        if ($body !== []) {
            try {
                $options["body"] = json_encode($body, JSON_THROW_ON_ERROR);
            } catch (JsonException $ex) {
                $this->logError($apiCall, "JSON_ENCODE error", $ex);
                throw new MatrixApiException("JSON_ERROR", $ex->getMessage());
            }
        }

        $statusCode = "UNKNOWN";
        try {
            $request = $this->client->request($method, $this->getApiUrl($apiCall), $options);
            $statusCode = $request->getStatusCode();
            $content = $request->getContent(false);
            if ($request->getStatusCode() >= 500) {
                $this->logError($apiCall, "Received Status code of $statusCode");
                throw new Exception("Received Status code of $statusCode",);
            }
        } catch (Throwable $ex) {
            $this->logError($apiCall, " | Status-Code: $statusCode", $ex);
            throw new MatrixApiException("REQUEST_ERROR", $ex->getMessage(), $ex->getCode());
        }

        try {
            $responseData = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $ex) {
            $this->logError($apiCall, "Error occurred while decoding response json data | Status-Code: $statusCode",
                $ex);
            throw new MatrixApiException("JSON_ERROR", $ex->getMessage(), $ex->getCode());
        }

        if (isset($responseData["errcode"])) {
            if ($responseData["errcode"] === "M_LIMIT_EXCEEDED") {
                $this->logError($apiCall,
                    "Matrix API Request limit reached. Consider removing ratelimit for admin & rest-api user");
            }
            throw new MatrixApiException($responseData["errcode"], $responseData["error"]);
        }
        return new MatrixApiResponse($statusCode, $responseData);
    }

    private function getApiUrl(string $apiCall): string
    {
        return $this->matrixServerUrl . "/" . ltrim($apiCall, "/");
    }

    public function checkRestApiUser(): bool
    {
        try {
            return (bool) $this->getRestApiUser()->getAccessToken();
        } catch (Throwable $ex) {
            $this->logger->error("Checking Rest-API User failed. Ex.: {$ex->getMessage()}");
            return false;
        }
    }

    public function checkAdminUser(): bool
    {
        try {
            return (bool) $this->getAdminUser()->getAccessToken();
        } catch (Throwable $ex) {
            $this->logger->error("Checking Admin User failed. Ex.: {$ex->getMessage()}");
            return false;
        }
    }

    protected function getAdminAccessToken(): string
    {
        try {
            return $this->getAdminUser()->getAccessToken();
        } catch (Throwable $ex) {
            $this->logger->error("Unable to retrieve Admin User access token. Ex.: {$ex->getMessage()}");
            return "";
        }
    }

    protected function getRestApiUserAccessToken(): string
    {
        try {
            return $this->getRestApiUser()->getAccessToken();
        } catch (Throwable $ex) {
            $this->logger->error("Unable to retrieve Rest-API User access token. Ex.: {$ex->getMessage()}");
            return "";
        }
    }

    public function getRestApiUser(): MatrixUser
    {
        if (self::$restApiUser === null) {
            self::$restApiUser = $this->login(
                $this->plugin->getPluginConfig()->getMatrixRestApiUserUsername(),
                $this->plugin->getPluginConfig()->getMatrixRestApiUserPassword(),
                "ilias_matrix_chat_device_bot"
            );
        }

        return self::$restApiUser;
    }

    public function getAdminUser(): MatrixUser
    {
        if (self::$adminUser === null) {
            self::$adminUser = $this->login(
                $this->plugin->getPluginConfig()->getMatrixAdminUsername(),
                $this->plugin->getPluginConfig()->getMatrixAdminPassword(),
                "ilias_matrix_chat_device_bot"
            );
        }

        return self::$adminUser;
    }

    public function getSpace(string $matrixSpaceId): ?MatrixSpace
    {
        try {
            $response = $this->sendRequest(
                "/_synapse/admin/v1/rooms/$matrixSpaceId"
            );
        } catch (MatrixApiException $ex) {
            $this->logger->error("Error occurred while trying to retrieve space '$matrixSpaceId'.");
            return null;
        }

        return new MatrixSpace(
            $matrixSpaceId,
            $response->getResponseDataValue("name"),
            $this->getRoomMembers($matrixSpaceId)
        );
    }

    public function getRoom(string $matrixRoomId): ?MatrixRoom
    {
        try {
            $response = $this->sendRequest(
                "/_synapse/admin/v1/rooms/$matrixRoomId"
            );
            if ($response->getResponseDataValue("room_type") === null && $response->getResponseDataValue("name") === null) {
                throw new Exception("Faulty room");
            }
        } catch (MatrixApiException|Exception $ex) {
            $this->logger->error("Error occurred while trying to retrieve room '$matrixRoomId'.");
            return null;
        }

        return new MatrixRoom(
            $response->getResponseDataValue("room_id"),
            $response->getResponseDataValue("name"),
            $this->getRoomMembers($matrixRoomId)
        );
    }

    /**
     * @return string[]
     */
    public function getRoomMembers(string $roomId): array
    {
        try {
            $response = $this->sendRequest("/_synapse/admin/v1/rooms/$roomId/members");
        } catch (MatrixApiException $ex) {
            $this->logger->error("Error occurred while trying to retrieve room members for room '$roomId'.");
            return [];
        }

        return $response->getResponseDataValue("members");
    }

    public function inviteUserToRoom(MatrixUser $matrixUser, MatrixRoom $matrixRoom): bool
    {
        if ($matrixRoom->isMember($matrixUser)) {
            return true;
        }
        try {
            $response = $this->sendRequest(
                "/_matrix/client/v3/rooms/{$matrixRoom->getId()}/invite",
                true,
                "POST",
                [
                    "user_id" => $matrixUser->getMatrixUserId(),
                ],
                true
            );
            return $response->getStatusCode() === 200;
        } catch (MatrixApiException $ex) {
            $roomType = $matrixRoom instanceof MatrixSpace ? "space" : "room";


            $this->logger->error(sprintf(
                "Error occurred while trying to invite user '%s' to $roomType '%s'",
                $matrixUser->getMatrixUserId(),
                $matrixRoom->getId()
            ));
            return false;
        }
    }

    public function addUserToRoom(MatrixUser $matrixUser, MatrixRoom $matrixRoom): bool
    {
        try {
            $response = $this->sendRequest(
                "/_synapse/admin/v1/join/{$matrixRoom->getId()}",
                true,
                "POST",
                [
                    "user_id" => $matrixUser->getMatrixUserId(),
                ],
            );
        } catch (MatrixApiException $ex) {
            //Todo: If admin user is not in room, he can't invite himself.
            //Todo: Find any user in the room. Login as that user (https://matrix-org.github.io/synapse/latest/admin_api/user_admin_api.html#login-as-a-user)
            //Todo: Then invite the admin user using that user.
            //Todo: Then make the admin user an admin using: https://matrix-org.github.io/synapse/latest/admin_api/rooms.html#make-room-admin-api
            return false;
        }

        return true;
    }

    public function userExists(string $matrixUserId): bool
    {
        try {
            return $this->sendRequest(
                    "/_synapse/admin/v2/users/$matrixUserId"
                )->getResponseData() !== [];
        } catch (MatrixApiException $ex) {
            $this->logger->error("Error occurred while trying to check if user '$matrixUserId' exists.");
            return false;
        }
    }

    public function usernameAvailable(string $username): bool
    {
        try {
            $response = $this->sendRequest("/_synapse/admin/v1/username_available?username=$username");
            return $response->getResponseDataValue("available");
        } catch (MatrixApiException $ex) {
            $this->logger->error("Error occurred while trying to check if username '$username' is available.");
            return false;
        }
    }

    public function retrieveNonce(): ?string
    {
        try {
            return $this->sendRequest("/_synapse/admin/v1/register")->getResponseDataValue("nonce");
        } catch (MatrixApiException $ex) {
            $this->logger->error("Error occurred while trying to retrieve nonce from server");
            return null;
        }
    }

    public function createUser(string $username, string $password, string $displayName): ?MatrixUser
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
                true,
                "POST",
                [
                    "nonce" => $nonce,
                    "username" => $username,
                    "password" => $password,
                    "displayname" => $displayName,
                    "admin" => false,
                    "mac" => $hmac
                ],
            );
        } catch (MatrixApiException $ex) {
            $this->logger->error("Error occurred while trying to create user with username '$username'");
            return null;
        }

        return $this->login($username, $password, "ilias_auth_verification");
    }

    public function getUser(string $matrixUserId): ?MatrixUser
    {
        try {
            $profile = $this->getMatrixUserProfile($matrixUserId);
        } catch (MatrixApiException $ex) {
            $this->logger->error("Error occurred while trying to retrieve user profile for user '$matrixUserId'");
            return null;
        }

        return new MatrixUser($matrixUserId, $profile["displayname"]);
    }

    public function removeUserFromRoom(MatrixUser $matrixUser, MatrixRoom $room, string $reason): bool
    {
        try {
            $response = $this->sendRequest(
                "/_matrix/client/v3/rooms/{$room->getId()}/kick",
                true,
                "POST",
                [
                    "reason" => $reason,
                    "user_id" => $matrixUser->getMatrixUserId(),
                ], true
            );
            return true;
        } catch (MatrixApiException $ex) {
            $this->logger->error(sprintf(
                "Error occurred while trying to remove user '%s' from room '%s' with reason '%s'.",
                $matrixUser->getMatrixUserId(),
                $room->getId(),
                $reason
            ));
            return false;
        }
    }

    public function changePassword(string $matrixUserId, $newPassword): bool
    {
        try {
            if (!$this->userExists($matrixUserId)) {
                return false;
                //Trying to update a non-existing user will create a new user.
            }
            $response = $this->sendRequest(
                "/_synapse/admin/v2/users/$matrixUserId",
                true,
                "PUT",
                [
                    "password" => $newPassword,
                    "logout_devices" => true
                ],
            );
            return true;
        } catch (MatrixApiException $ex) {
            $this->logger->error("Error occurred while trying to change password of user '$matrixUserId'");
            return false;
        }
    }

    public function deleteRoom(MatrixRoom $room, string $reason = ""): bool
    {
        try {
            $response = $this->sendRequest(
                "/_synapse/admin/v1/rooms/{$room->getId()}",
                true,
                "DELETE",
                ["message" => $reason, "purge" => true, "block" => true],
            );
        } catch (MatrixApiException $ex) {
            $this->logger->error("Error occurred while trying to delete room '{$room->getId()}' with reason '$reason'");
            return false;
        }

        return true;
    }

    public function login(string $username, string $password, string $deviceId): ?MatrixUser
    {
        try {
            $response = $this->sendRequest("/_matrix/client/v3/login", false, "POST", [
                "type" => "m.login.password",
                "user" => $username,
                "password" => $password,
                "device_id" => $deviceId
            ]);
        } catch (MatrixApiException $ex) {
            $this->logger->error("Error occurred while trying to login user with username '$username'");
            return null;
        }

        $userId = $response->getResponseDataValue("user_id");

        try {
            $matrixUserProfile = $this->getMatrixUserProfile($userId);
            $displayName = $matrixUserProfile["displayname"];
        } catch (MatrixApiException $e) {
            $displayName = "";
            $this->logger->error("Error occurred while trying to retrieve profile for user '$userId'. Assuming displayname as empty");
        }

        return (new MatrixUser(
            $userId,
            $displayName
        ))->setAccessToken($response->getResponseDataValue("access_token"))
            ->setDeviceId($deviceId);
    }

    public function loginUserWithAdmin(string $matrixUserId): ?MatrixUser
    {
        try {
            $response = $this->sendRequest(
                "/_synapse/admin/v1/users/{$matrixUserId}/login",
                true,
                "POST"
            );
        } catch (MatrixApiException $ex) {
            $this->logger->error("Error occurred while trying to login into user '$matrixUserId' through the Admin-API");
            return null;
        }

        try {
            $displayName = $this->getMatrixUserProfile($matrixUserId)["displayname"];
        } catch (MatrixApiException $ex) {
            $displayName = "";
            $this->logger->error("Error occurred while trying to retrieve profile for user '$matrixUserId'. Assuming displayname as empty");
        }


        return (new MatrixUser($matrixUserId, $displayName));
    }

    public function createSpace(string $name): ?MatrixSpace
    {
        try {
            $response = $this->sendRequest(
                "/_matrix/client/v3/createRoom",
                true,
                "POST",
                [
                    "name" => $name,
                    "preset" => "private_chat",
                    "visibility" => "private",
                    "creation_content" => [
                        "type" => "m.space"
                    ],
                    "initial_state" => [
                        [
                            "type" => "m.room.history_visibility",
                            "content" => [
                                "history_visibility" => "invited"
                            ]
                        ]
                    ],
                    "topic" => "",
                    "power_level_content_override" => [
                        "ban" => 100,
                        "events_default" => 0,
                        "invite" => 100,
                        "kick" => 100,
                        "redact" => 100,
                        "state_default" => 100,
                        "users_default" => 0
                    ]
                ], true
            );
        } catch (MatrixApiException $e) {
            $this->logger->error("Error occurred while trying to create space with name '$name'");
            return null;
        }

        $matrixRoomId = $response->getResponseDataValue("room_id");

        return new MatrixSpace(
            $matrixRoomId,
            $name,
            $this->getRoomMembers($matrixRoomId)
        );
    }

    protected function addRoomToSpace(MatrixSpace $space, MatrixRoom $room): bool
    {
        try {
            $response = $this->putRoomStateEvent($space, [
                "via" => [
                    $this->plugin->getPluginConfig()->getMatrixServerName()
                ],
                "suggested" => false,
            ], "m.space.child", $room->getId());
            return $response->getStatusCode() === 200;
        } catch (MatrixApiException $ex) {
            $this->logger->error("Error occurred while trying to add room '{$room->getId()}' to space '{$space->getId()}'");
            return false;
        }
    }

    /**
     * @throws MatrixApiException
     */
    protected function getRoomState(MatrixRoom $room, string $eventType, string $stateKey = ""): array
    {
        $response = $this->sendRequest(
            "/_matrix/client/v3/rooms/{$room->getId()}/state/$eventType" . ($stateKey ? "/$stateKey" : ""),
            true,
            "GET",
            [],
            true
        );
        return $response->getResponseData();
    }

    /**
     * @throws MatrixApiException
     */
    protected function putRoomStateEvent(
        MatrixRoom $room,
        array $data,
        string $eventType,
        string $stateKey = ""
    ): MatrixApiResponse {
        return $this->sendRequest(
            "/_matrix/client/v3/rooms/{$room->getId()}/state/$eventType" . ($stateKey ? "/$stateKey" : ""),
            true,
            "PUT",
            $data,
            true
        );
    }

    public function getStatusOfUserInRoom(MatrixRoom $room, string $matrixUserId): string
    {
        try {
            $state = $this->getRoomState($room, "m.room.member", $matrixUserId);
            return $state["membership"];
        } catch (MatrixApiException $ex) {
            $this->logger->error("Error occurred while trying to retrieve status of user '$matrixUserId' in room '{$room->getId()}'.");
            return "";
        }
    }

    /**
     * @param MatrixUserPowerLevel[]|MatrixUserPowerLevel $matrixUserPowerLevelMap
     */
    public function setUserPowerLevelOnRoom(MatrixRoom $room, $matrixUserPowerLevelMap): bool
    {
        if (!is_array($matrixUserPowerLevelMap)) {
            $matrixUserPowerLevelMap = [$matrixUserPowerLevelMap];
        }

        if ($matrixUserPowerLevelMap === []) {
            return false;
        }

        try {
            $state = $this->getRoomState($room, "m.room.power_levels");
        } catch (MatrixApiException $ex) {
            $this->logger->error("Error occurred while trying to request current state of room '{$room->getId()}'");
            return false;
        }

        if (!isset($state["users"]) || !is_array($state["users"])) {
            $state["users"] = [];
        }

        foreach ($matrixUserPowerLevelMap as $matrixUserPowerLevel) {
            $state["users"][$matrixUserPowerLevel->getMatrixUserId()] = $matrixUserPowerLevel->getPowerLevel();
        }

        try {
            $response = $this->putRoomStateEvent($room, $state, "m.room.power_levels");
            return $response->getStatusCode() === 200;
        } catch (MatrixApiException $ex) {
            $powerLevelsSetString = "";
            foreach ($matrixUserPowerLevelMap as $matrixUserPowerLevel) {
                $powerLevelsSetString .= "- {$matrixUserPowerLevel->getMatrixUserId()}: {$matrixUserPowerLevel->getPowerLevel()}\n";
            }

            $this->logger->error("Error occurred while trying to set power levels on room '{$room->getId()}'. Power levels:\n$powerLevelsSetString");
            return false;
        }
    }

    public function createRoom(string $name, bool $enableEncryption, MatrixSpace $parentSpace): ?MatrixRoom
    {
        $postData = [
            "name" => $name,
            "preset" => "private_chat",
            "visibility" => "private",
            "initial_state" => [
                [
                    "type" => "m.room.history_visibility",
                    "content" => [
                        "history_visibility" => "invited"
                    ]
                ],
                [
                    "type" => "m.space.parent",
                    "content" => [
                        "via" => [$this->plugin->getPluginConfig()->getMatrixServerName()],
                        "canonical" => true
                    ],
                    "state_key" => $parentSpace->getId()
                ],
                [
                    "type" => "m.room.join_rules",
                    "content" => [
                        "join_rule" => "invite"
                    ]
                ]
            ],
            "power_level_content_override" => [
                "ban" => 50,
                "invite" => 50,
                "kick" => 50,
                "state_default" => 100,
            ]
        ];

        if ($enableEncryption) {
            $postData["initial_state"][] = [
                "type" => "m.room.encryption",
                "state_key" => "",
                "content" => [
                    "algorithm" => "m.megolm.v1.aes-sha2"
                ]
            ];
        }

        try {
            $response = $this->sendRequest(
                "/_matrix/client/v3/createRoom",
                true,
                "POST",
                $postData,
                true
            );
        } catch (MatrixApiException $e) {
            $this->logger->error("Error occurred while trying to create room with name '$name' & assign to parent space '{$parentSpace->getId()}'");
            return null;
        }

        $matrixRoomId = $response->getResponseDataValue("room_id");

        $matrixRoom = new MatrixRoom(
            $matrixRoomId,
            $name,
            $this->getRoomMembers($matrixRoomId)
        );

        if (!$this->addRoomToSpace($parentSpace, $matrixRoom)) {
            $this->logger->error(sprintf(
                "Room was created but adding room to space as a child failed. Room will not show up under Space '%s' (%s)",
                $parentSpace->getName(),
                $parentSpace->getId()
            ));
        }

        return $matrixRoom;
    }

    public function setOverrideRateLimit(MatrixUser $matrixUser, int $messagesPerSecond, int $burstCount): bool
    {
        try {
            $response = $this->sendRequest(
                "/_synapse/admin/v1/users/{$matrixUser->getMatrixUserId()}/override_ratelimit",
                true,
                "POST",
                [
                    "messages_per_second" => $messagesPerSecond,
                    "burst_count" => $burstCount
                ]
            );
            return $response->getStatusCode() === 200;
        } catch (MatrixApiException $ex) {
            $this->logger->error(sprintf(
                "Error occurred while trying to set overwrite_ratelimit for user '%s', messages_per_second = %s & burst_count = %s",
                $matrixUser->getMatrixUserId(),
                $messagesPerSecond,
                $burstCount
            ));
            return false;
        }
    }

    /**
     * @throws MatrixApiException
     */
    public function getMatrixUserProfile(string $matrixUserId): array
    {
        $response = $this->sendRequest("/_matrix/client/v3/profile/$matrixUserId", false, "GET", [], true);

        return [
            "avatar_url" => $response->getResponseDataValue("avatar_url") ?: "",
            "displayname" => $response->getResponseDataValue("displayname") ?: ""
        ];
    }

    /**
     * @return array{name:String, version:String}|null
     */
    public function getServerVersionInfo(): ?array
    {
        try {
            $response = $this->sendRequest("/_matrix/federation/v1/version", false);
        } catch (MatrixApiException $ex) {
            $this->logger->error("Error occurred while trying to retrieve server version info");
            return null;
        }
        return $response->getResponseDataValue("server");
    }

    public function serverReachable(): bool
    {
        return $this->getServerVersionInfo() !== null;
    }

    protected function logError(string $apiCall, string $message, ?Exception $ex = null): void
    {
        $this->logger->error("Matrix API request to `{$this->getApiUrl($apiCall)}` failed. $message." . ($ex === null ? "" : " Ex.: {$ex->getMessage()}"));
    }
}
