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

use ilMatrixChatClientPlugin;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use JsonException;
use ILIAS\Plugin\MatrixChatClient\Repository\CourseSettingsRepository;
use Throwable;

/**
 * Class MatrixApiEndpointBase
 *
 * @package ILIAS\Plugin\MatrixChatClient\Api
 * @author  Marvin Beym <mbeym@databay.de>
 */
abstract class MatrixApiEndpointBase
{
    /**
     * @var string
     */
    private $matrixServerUrl;
    /**
     * @var CourseSettingsRepository
     */
    protected $courseSettingsRepo;
    /**
     * @var HttpClientInterface
     */
    protected $client;
    /**
     * @var ilMatrixChatClientPlugin
     */
    protected $plugin;

    public function __construct(string $matrixServerUrl, HttpClientInterface $client, ilMatrixChatClientPlugin $plugin)
    {
        $this->matrixServerUrl = $matrixServerUrl;
        $this->client = $client;
        $this->plugin = $plugin;
        $this->courseSettingsRepo = CourseSettingsRepository::getInstance();
    }

    protected function getMatrixUserDisplayName(string $matrixUserId) : string
    {
        try {
            $response = $this->sendRequest("/_matrix/client/v3/profile/{$matrixUserId}/displayname");
        } catch (MatrixApiException $e) {
            return "";
        }

        return $response["displayname"];
    }

    private function getApiUrl(string $apiCall) : string
    {
        return $this->matrixServerUrl . "/" . ltrim($apiCall, "/");
    }

    /**
     * @throws MatrixApiException
     */
    protected function sendRequest(
        string $apiCall,
        string $method = "GET",
        array $body = [],
        ?string $token = null
    ) : array {
        $options = [];
        if ($body !== []) {
            try {
                $options["body"] = json_encode($body, JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new MatrixApiException("JSON_ERROR", $e->getMessage());
            }
        }
        if ($token !== null) {
            $options["auth_bearer"] = $token;
        }

        try {
            $request = $this->client->request($method, $this->getApiUrl($apiCall), $options);
            $content = $request->getContent(false);
        } catch (Throwable $e) {
            throw new MatrixApiException("REQUEST_ERROR", $e->getMessage(), $e->getCode());
        }

        try {
            $responseData = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new MatrixApiException("JSON_ERROR", $e->getMessage());
        }

        if (isset($responseData["errcode"])) {
            if ($responseData["errcode"] === "M_LIMIT_EXCEEDED") {
                $this->plugin->dic->logger()->root()->error(
                    "matrix api request limit reached. Request call: '{$this->getApiUrl($apiCall)}'"
                );
            }
            throw new MatrixApiException($responseData["errcode"], $responseData["error"]);
        }
        return $responseData;
    }
}
