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

use Exception;

/**
 * Class MatrixApiException
 * @package ILIAS\Plugin\MatrixChatClient\Api
 * @author  Marvin Beym <mbeym@databay.de>
 */
class MatrixApiException extends Exception
{
    /**
     * @var string
     */
    private $errorCode;

    public function __construct(string $errorCode, string $errorMessage)
    {
        parent::__construct($errorMessage);
        $this->setErrorCode($errorCode);
    }

    private function setErrorCode(string $errorCode) : void
    {
        $this->errorCode = $errorCode;
    }

    private function getErrorCode() : string
    {
        return $this->errorCode;
    }
}
