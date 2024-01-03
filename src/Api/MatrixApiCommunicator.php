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

namespace ILIAS\Plugin\MatrixChatClient\Api;

use ilMatrixChatClientPlugin;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Class MatrixApiCommunicator
 *
 * @package ILIAS\Plugin\MatrixChatClient\Api
 * @author  Marvin Beym <mbeym@databay.de>
 */
class MatrixApiCommunicator
{
    private HttpClientInterface $client;
    private ilMatrixChatClientPlugin $plugin;
    public MatrixAdminApi $admin;
    public MatrixUserApi $user;
    public MatrixGeneralApi $general;

    public function __construct(ilMatrixChatClientPlugin $plugin, string $matrixServerUrl)
    {
        $this->client = HttpClient::create();
        $this->plugin = $plugin;
        $this->user = new MatrixUserApi($matrixServerUrl, $this->client, $this->plugin);
        $this->admin = new MatrixAdminApi($matrixServerUrl, $this->client, $this->plugin);
        $this->general = new MatrixGeneralApi($matrixServerUrl, $this->client, $this->plugin);
    }
}
