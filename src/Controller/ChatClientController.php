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

namespace ILIAS\Plugin\MatrixChatClient\Controller;

use ilTemplate;
use ILIAS\DI\Container;
use ILIAS\Plugin\MatrixChatClient\Model\CourseSettings;
use ilUtil;
use ilObjCourseGUI;
use ilTemplateException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\RequestInterface;
use ILIAS\Filesystem\Stream\Streams;
use ILIAS\Plugin\MatrixChatClient\Repository\CourseSettingsRepository;
use Exception;
use ilCourseParticipants;
use ilSession;
use ILIAS\Plugin\MatrixChatClient\Form\ChatLoginForm;
use ILIAS\Plugin\MatrixChatClient\Model\MatrixUser;
use ilObject;
use JsonException;
use ReflectionMethod;
use ilUIPluginRouterGUI;
use ilMatrixChatClientUIHookGUI;
use ilObjGroupGUI;

/**
 * Class ChatClientController
 *
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

        $courseId = (int) $this->verifyQueryParameter("ref_id");

        $this->courseId = (int) $courseId;
        $this->courseSettingsRepo = CourseSettingsRepository::getInstance();
        $this->courseSettings = $this->courseSettingsRepo->read((int) $this->courseId);
    }

    public function injectTabs(string $selectedTabId) : void
    {
        $gui = null;
        $this->ctrl->setParameterByClass(ilUIPluginRouterGUI::class, "ref_id", $this->courseId);
        switch (ilObject::_lookupType($this->courseId, true)) {
            case "crs":
                $gui = new ilObjCourseGUI();
                $gui->getTabs();
                break;
            case "grp":
                $gui = new ilObjGroupGUI([], $this->courseId, true);
                $gui->getTabs();
                break;
        }

        if ($gui) {
            $reflectionMethod = new ReflectionMethod($gui, 'setTitleAndDescription');
            $reflectionMethod->setAccessible(true);
            $reflectionMethod->invoke($gui);

            $this->dic['ilLocator']->addRepositoryItems($this->courseId);
        }

        $this->tabs->addTab(
            "matrix-chat",
            $this->plugin->txt("chat"),
            $this->dic->ctrl()->getLinkTargetByClass([
                ilUIPluginRouterGUI::class,
                ilMatrixChatClientUIHookGUI::class,
            ], self::getCommand("showChat"))
        );

        $this->tabs->activateTab($selectedTabId);
    }

    /**
     * @throws ilTemplateException|JsonException
     */
    public function showChat() : void
    {
        $this->injectTabs("matrix-chat");
        $this->mainTpl->setTitle($this->plugin->txt("chat"));
        $this->mainTpl->loadStandardTemplate();

        $this->ctrl->setParameterByClass(ilObjCourseGUI::class, "ref_id", $this->courseId);
        if (!$this->courseSettings || !$this->courseSettings->isChatIntegrationEnabled()) {
            $this->ctrl->redirectByClass(
                ["ilRepositoryGUI", "ilObjCourseGUI"],
                "view"
            );
        }

        if (!ilCourseParticipants::_isParticipant($this->courseId, $this->dic->user()->getId())) {
            ilUtil::sendFailure($this->lng->txt("permission_denied", true));
            $this->ctrl->redirectByClass(
                ["ilRepositoryGUI", "ilObjCourseGUI"],
                "view"
            );
        }

        try {
            $matrixUser = MatrixUser::createFromJson(
                json_decode(
                    ilSession::get("matrixUser") ?? "[]",
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                )
            );
        } catch (Exception $ex) {
            $matrixUser = null;
        }

        if (!$matrixUser) {
            $this->redirectToCommand("showChatLogin", ["ref_id" => $this->courseId]);
            return;
        }

        $room = $this->matrixApi->admin->getRoom($this->courseSettings->getMatrixRoomId());

        if (!$room) {
            ilUtil::sendFailure($this->plugin->txt("matrix.chat.room.notFound"), true);
            $this->ctrl->redirectByClass(
                ["ilRepositoryGUI", "ilObjCourseGUI"],
                "view"
            );
            return;
        }

        if (
            !$this->matrixApi->admin->isUserMemberOfRoom($matrixUser, $this->courseSettings->getMatrixRoomId())
        ) {
            if (!$this->matrixApi->admin->addUserToRoom($matrixUser, $room)) {
                ilUtil::sendFailure($this->plugin->txt("matrix.chat.room.memberAssignFailed"), true);
                $this->ctrl->redirectByClass(
                    ["ilRepositoryGUI", "ilObjCourseGUI"],
                    "view"
                );
            }
        }

        $this->mainTpl->addJavaScript($this->plugin->jsFolder("libs/easymde.min.js"));
        $this->mainTpl->addJavaScript($this->plugin->jsFolder("libs/markdown-it.min.js"));
        $this->mainTpl->addJavaScript($this->plugin->jsFolder("libs/olm.js"));
        $this->mainTpl->addJavaScript($this->plugin->jsFolder("libs/browser-matrix.js"));
        $this->mainTpl->addJavaScript($this->plugin->jsFolder("chat-implementation.js"));
        $this->mainTpl->addCss($this->plugin->cssFolder("style.css"));
        $this->mainTpl->addCss($this->plugin->cssFolder("easymde.min.css"));

        $tpl = new ilTemplate($this->plugin->templatesFolder("tpl.chat-integration.html"), true, true);
        $tpl->setVariable("LOGOUT_URL", $this->getCommandLink("chatLogout", ["ref_id" => $this->courseId]));
        $tpl->setVariable("LOGOUT_TEXT", $this->lng->txt("logout"));
        $tpl->setVariable("LOGGED_IN_AS_TEXT", $this->plugin->txt("matrix.chat.loggedInAs"));
        $tpl->setVariable("LOGGED_IN_AS_DISPLAY_NAME", $matrixUser->getMatrixDisplayName());
        $tpl->setVariable("LOGGED_IN_AS_USER", $matrixUser->getMatrixUsername());

        $tpl->setVariable(
            $room->isEncrypted() ? "ENCRYPTED_TEXT" : "UNENCRYPTED_TEXT",
            $this->plugin->txt($room->isEncrypted() ? "matrix.chat.room.encryption.encrypted" : "matrix.chat.room.encryption.unencrypted")
        );
        if (!$room->isEncrypted()) {
            $tpl->setVariable("ENCRYPTION_ENABLE_TEXT", $this->lng->txt("enable"));
        }
        $tpl->setVariable("SEND_TEXT", $this->plugin->txt("matrix.chat.send"));
        $this->mainTpl->addOnLoadCode(
            "window.matrixChatConfig = " . json_encode([
                "baseUrl" => $this->plugin->getPluginConfig()->getMatrixServerUrl(),
                "ajax" => [
                    "getTemplateAjax" => $this->getCommandLink("getTemplateAjax", [
                        "ref_id" => $this->courseId
                    ]),
                ],
                "user" => $matrixUser,
                "roomId" => $room->getId(),
                "chatInitialLoadLimit" => $this->plugin->getPluginConfig()->getChatInitialLoadLimit(),
                "chatHistoryLoadLimit" => $this->plugin->getPluginConfig()->getChatHistoryLoadLimit(),
            ], JSON_THROW_ON_ERROR)
        );

        $translationFilePath = "{$this->plugin->getDirectory()}/lang/{$this->lng->getLangKey()}.lang.json";
        $translationJson = "{}";
        if (is_file($translationFilePath) && is_readable($translationFilePath)) {
            $translationJson = file_get_contents($translationFilePath);
        }

        $this->mainTpl->addOnLoadCode(
            "window.matrixChatTranslation = " . json_encode(
                $translationJson,
                JSON_THROW_ON_ERROR
            )
        );

        $this->mainTpl->setContent($tpl->get());
        $this->mainTpl->printToStdOut();
    }

    public function showChatLogin(?ChatLoginForm $form = null) : void
    {
        if (!$form) {
            $form = new ChatLoginForm();
        }

        $this->injectTabs("matrix-chat");

        $this->mainTpl->loadStandardTemplate();

        $this->mainTpl->setContent($form->getHTML());
        $this->mainTpl->printToStdOut();
    }

    public function chatLogout() : void
    {
        ilSession::set("matrixUser", null);

        ilUtil::sendSuccess($this->plugin->txt("matrix.chat.logout.success"), true);

        $this->redirectToCommand("showChatLogin", ["ref_id" => $this->courseId]);
    }

    public function saveChatLogin() : void
    {
        $form = new ChatLoginForm();

        if (!$form->checkInput()) {
            ilUtil::sendFailure($this->plugin->txt("matrix.chat.login.failed"));
            $form->setValuesByPost();
            $this->showChatLogin($form);
            return;
        }

        $form->setValuesByPost();

        try {
            $matrixUser = $this->matrixApi->user->loginUser(
                $this->dic->user()->getId(),
                $form->getInput("username"),
                $form->getInput("password")
            );
            if (!$matrixUser) {
                throw new Exception("Login failed");
            }
        } catch (Exception $ex) {
            ilUtil::sendFailure($this->plugin->txt("matrix.chat.login.failed"), true);
            $form->setValuesByArray(["password" => "", true]);
            $this->showChatLogin($form);
            return;
        }

        ilSession::set("matrixUser", json_encode($matrixUser, JSON_THROW_ON_ERROR));

        try {
            ilUtil::sendSuccess($this->plugin->txt("matrix.chat.login.success"), true);
            $this->showChat();
        } catch (ilTemplateException $e) {
            $this->redirectToCommand("showChat", ["ref_id" => $this->courseId]);
        }
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
}
