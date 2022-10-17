<?php declare(strict_types=1);
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

namespace ILIAS\Plugin\MatrixChatClient\Controller;

use ilTemplate;
use ILIAS\DI\Container;
use ILIAS\Plugin\MatrixChatClient\Model\CourseSettings;
use ILIAS\Plugin\MatrixChatClient\Api\MatrixApiCommunicator;
use ilUtil;
use ilObjCourseGUI;
use ILIAS\Plugin\MatrixChatClient\Model\Room\RoomInvite;
use ilMatrixChatClientUIHookGUI;
use ilTemplateException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\RequestInterface;
use Defuse\Crypto\Crypto;
use ilUIPluginRouterGUI;
use ilCourseBookingUIHookGUI;
use ILIAS\Filesystem\Stream\Streams;
use ILIAS\Plugin\MatrixChatClient\Repository\CourseSettingsRepository;

/**
 * Class ChatClientController
 * @package ILIAS\Plugin\MatrixChatClient\Controller
 * @author  Marvin Beym <mbeym@databay.de>
 */
class ChatClientController extends BaseController
{
    /**
     * @var CourseSettings
     */
    private $courseSettings;
    /**
     * @var int
     */
    private $courseId;
    /**
     * @var RequestInterface|ServerRequestInterface
     */
    private $request;
    /**
     * @var CourseSettingsRepository
     */
    private $courseSettingsRepo;

    public function __construct(Container $dic)
    {
        parent::__construct($dic);

        $this->request = $this->dic->http()->request();
        $query = $this->request->getQueryParams();
        if (!isset($query["ref_id"]) || !$query["ref_id"]) {
            ilUtil::sendFailure($this->plugin->txt("general.plugin.requiredParameterMissing"), true);
            $this->plugin->redirectToHome();
        }

        $this->courseId = (int) $query["ref_id"];
        $this->courseSettingsRepo = CourseSettingsRepository::getInstance();
        $this->courseSettings = $this->courseSettingsRepo->read((int) $this->courseId);
    }

    /**
     * @throws ilTemplateException
     */
    public function showChat() : void
    {
        $this->mainTpl->setTitle($this->plugin->txt("chat"));
        $this->mainTpl->loadStandardTemplate();

        $this->ctrl->setParameterByClass(ilObjCourseGUI::class, "ref_id", $this->courseId);
        $this->tabs->setBackTarget(
            $this->lng->txt("content"),
            $this->ctrl->getLinkTargetByClass(
                ["ilRepositoryGUI", "ilObjCourseGUI"],
                "view"
            )
        );

        if (($matrixUser = $this->matrixApi->user->login("admin", "Test123")) === null) {
            ilUtil::sendFailure($this->plugin->txt("matrix.chat.login.failed"), true);
            $this->mainTpl->printToStdOut();
            return;
        }

        $room = $this->matrixApi->user->getRoomByCourseId($this->courseId);

        if (!$room) {
            ilUtil::sendFailure($this->plugin->txt("chat_client_integration_room_not_found"), true);
            $this->mainTpl->printToStdOut();
            return;
        }

        if ($room instanceof RoomInvite) {
            ilUtil::sendInfo($this->plugin->txt("chat_client_integration_room_invite_available"), true);
            $this->ctrl->setParameterByClass(ilMatrixChatClientUIHookGUI::class, "ref_id", $this->courseId);
            $joinButton = $this->dic->ui()->factory()->button()->standard(
                $this->lng->txt("join"),
                $this->getCommandLink("joinRoom")
            );
            $this->mainTpl->setContent($this->dic->ui()->renderer()->render($joinButton));
            $this->mainTpl->printToStdOut();
            return;
        }
        $message = $room->getTimeline()["events"][array_key_last($room->getTimeline()["events"])];
        $data = $message["content"];

        //$a = openssl_decrypt($data["ciphertext"], "aes-256-cbc-hmac-sha1", "ZgmQiaSHT4UrK6GP3JNsy48FJCAT/SfPqFJ4k/GmfFg");

        $this->mainTpl->addJavaScript($this->plugin->jsFolder("libs/olm.js"));
        $this->mainTpl->addJavaScript($this->plugin->jsFolder("libs/browser-matrix.js"));
        $this->mainTpl->addJavaScript($this->plugin->jsFolder("test.js"));
        $this->mainTpl->addJavaScript($this->plugin->jsFolder("test.js"));
        $this->mainTpl->addCss($this->plugin->cssFolder("style.css"));
        $tpl = new ilTemplate($this->plugin->templatesFolder("tpl.chat-integration.html"), false, false);
        $this->mainTpl->addOnLoadCode(
            "window.matrixChatConfig = " . json_encode([
                "baseUrl" => "http://localhost:8008",
                "ajax" => [
                    "getTemplateAjax" => $this->getCommandLink("getTemplateAjax", [
                        "ref_id" => $this->courseId
                    ]),
                ],
                "user" => $matrixUser,
                "roomId" => $room->getRoomId(),
            ], JSON_THROW_ON_ERROR)
        );


        $this->mainTpl->setContent($tpl->get());
        $this->mainTpl->printToStdOut();
    }

    public function getTemplateAjax() : void
    {
        $query = $this->request->getQueryParams();
        $http = $this->dic->http();
        if (
            !isset($query["templateName"])
            || !$query["templateName"]
            || !file_exists($this->plugin->templatesFolder($query["templateName"]))
        ) {
            $responseStream = Streams::ofString("");
            $http->saveResponse($http->response()->withBody($responseStream));
            $http->sendResponse();
            $http->close();
        }

        $tpl = new ilTemplate($this->plugin->templatesFolder($query["templateName"]), false, false);
        $a = $tpl->get();

        $responseStream = Streams::ofString($a);
        $http->saveResponse($http->response()->withBody($responseStream));
        $http->sendResponse();
        $http->close();
    }

    public function joinRoom() : void
    {
        //ToDo: Replace manual login with getting username & password from user field
        //ToDo: Also move logging in to api calls by checking if accessToken is set, if not => login
        if (!$this->matrixApi->user->login("user1", "Test123")) {
            ilUtil::sendFailure($this->plugin->txt("chat_client_integration_login_failed"), true);
            $this->redirectToCommand("showChat", ["ref_id" => $this->courseId]);
            return;
        }
        $room = $this->matrixApi->user->getRoomByCourseId($this->courseId);

        if (!$room instanceof RoomInvite) {
            ilUtil::sendFailure($this->plugin->txt("chat_client_integration_room_invite_invalid"), true);
            $this->redirectToCommand("showChat", ["ref_id" => $this->courseId]);
            return;
        }

        $this->matrixApi->user->joinRoom($room);
    }
}
