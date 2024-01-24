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

/**
 * Class MatrixApiResponse
 *
 * @package ILIAS\Plugin\MatrixChatClient\Api
 * @author  Marvin Beym <mbeym@databay.de>
 */
class MatrixApiResponse
{
    private int $statusCode;
    private array $responseData;

    /**
     * @param int   $statusCode
     * @param array $responseData
     */
    public function __construct(int $statusCode, array $responseData)
    {
        $this->statusCode = $statusCode;
        $this->responseData = $responseData;
    }

    /**
     * @param string $key
     * @return null|string|int|float|bool|array
     */
    public function getResponseDataValue(string $key)
    {
        return $this->getResponseData()[$key] ?? null;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseData(): array
    {
        return $this->responseData;
    }
}
