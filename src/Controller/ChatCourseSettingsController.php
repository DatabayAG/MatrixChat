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

use Exception;
use ilCourseParticipants;
use ILIAS\DI\Container;
use ILIAS\Plugin\Libraries\ControllerHandler\BaseController;
use ILIAS\Plugin\Libraries\ControllerHandler\ControllerHandler;
use ILIAS\Plugin\MatrixChatClient\Form\ChatSettingsForm;
use ILIAS\Plugin\MatrixChatClient\Form\DisableChatIntegrationForm;
use ILIAS\Plugin\MatrixChatClient\Model\CourseSettings;
use ILIAS\Plugin\MatrixChatClient\Model\UserConfig;
use ILIAS\Plugin\MatrixChatClient\Repository\CourseSettingsRepository;
use ILIAS\Plugin\MatrixChatClient\Repository\UserRoomAddQueueRepository;
use ilMatrixChatClientUIHookGUI;
use ilObjCourseGUI;
use ilObject;
use ilObjGroupGUI;
use ilObjUser;
use ilUIPluginRouterGUI;
use ReflectionClass;
use ReflectionMethod;

/**
 * Class ChatCourseSettingsController
 *
 * @package ILIAS\Plugin\MatrixChatClient\Controller
 * @author  Marvin Beym <mbeym@databay.de>
 */
class ChatCourseSettingsController extends BaseController
{
    private CourseSettingsRepository $courseSettingsRepo;
    private UserRoomAddQueueRepository $userRoomAddQueueRepo;
    private CourseSettings $courseSettings;

    public function __construct(Container $dic, ControllerHandler $controllerHandler)
    {
        parent::__construct($dic, $controllerHandler);
        $this->courseSettingsRepo = CourseSettingsRepository::getInstance($dic->database());
        $this->userRoomAddQueueRepo = UserRoomAddQueueRepository::getInstance($dic->database());

        $this->courseSettings = $this->courseSettingsRepo->read(
            (int) $this->controllerHandler->verifyQueryParameterExists("ref_id")
        );
    }

    private function injectTabs(): void
    {
        $this->ctrl->setParameterByClass(ilUIPluginRouterGUI::class, "ref_id", $this->courseSettings->getCourseId());
        $gui = null;
        switch (ilObject::_lookupType($this->courseSettings->getCourseId(), true)) {
            case "crs":
                $gui = new ilObjCourseGUI([], $this->courseSettings->getCourseId(), true);
                $gui->prepareOutput();
                $gui->setSubTabs("properties");
                break;
            case "grp":
                $gui = new ilObjGroupGUI([], $this->courseSettings->getCourseId(), true);
                $gui->prepareOutput();
                $guiRefClass = new ReflectionClass($gui);
                $setSubTabsMethod = $guiRefClass->getMethod("setSubTabs");
                $setSubTabsMethod->setAccessible(true);
                $setSubTabsMethod->invoke($gui, "settings");
                break;
        }

        if ($gui) {
            $reflectionMethod = new ReflectionMethod($gui, 'setTitleAndDescription');
            $reflectionMethod->setAccessible(true);
            $reflectionMethod->invoke($gui);

            $this->dic['ilLocator']->addRepositoryItems($this->courseSettings->getCourseId());
        }

        $this->tabs->addSubTab(
            "matrix-chat-course-settings",
            $this->plugin->txt("matrix.chat.course.settings"),
            $this->ctrl->getLinkTargetByClass([
                ilUIPluginRouterGUI::class,
                ilMatrixChatClientUIHookGUI::class,
            ], self::getCommand("showSettings"))
        );

        $this->tabs->setForcePresentationOfSingleTab(true);
        $this->tabs->activateTab("settings");
        $this->tabs->activateSubTab("matrix-chat-course-settings");
    }

    public function showSettings(?ChatSettingsForm $form = null): void
    {
        $this->injectTabs();

        $courseSettings = $this->courseSettings;
        if (!$form) {
            $form = new ChatSettingsForm();

            if (
                $courseSettings->isChatIntegrationEnabled()
                && !$courseSettings->getMatrixRoom()
            ) {
                $this->uiUtil->sendFailure($this->plugin->txt("matrix.chat.room.notFoundEvenThoughEnabled"), true);
            }

            $form->setValuesByArray([
                "chatIntegrationEnabled" => $courseSettings->isChatIntegrationEnabled(),
            ], true);
        }

        $this->mainTpl->loadStandardTemplate();

        $this->mainTpl->setContent($form->getHTML());
        $this->mainTpl->printToStdOut();
    }

    public function saveSettings(): void
    {
        $form = new ChatSettingsForm();
        $courseSettings = $this->courseSettings;
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->showSettings($form);
        }

        $form->setValuesByPost();

        $enableChatIntegration = (bool) $form->getInput("chatIntegrationEnabled");

        $courseSettings->setChatIntegrationEnabled($enableChatIntegration);

        $room = $courseSettings->getMatrixRoom();

        if ($enableChatIntegration && (!$room || !$room->exists())) {
            $room = $this->matrixApi->admin->createRoom(ilObject::_lookupTitle(ilObject::_lookupObjId($courseSettings->getCourseId())));
            $courseSettings->setMatrixRoom($room);
        }

        if ($enableChatIntegration) {
            if ($room) {
                foreach ((ilCourseParticipants::getInstance($courseSettings->getCourseId()))->getParticipants() as $participantId) {
                    $participantId = (int) $participantId;
                    $userConfig = (new UserConfig(new ilObjUser($participantId)))->load();

                    if (!$userConfig->getMatrixUserId()) {
                        continue;
                    }

                    $matrixUser = $this->matrixApi->admin->loginUserWithAdmin(
                        $participantId,
                        $userConfig->getMatrixUserId()
                    );
                    if (!$matrixUser) {
                        continue;
                    }

                    if (!$this->matrixApi->admin->isUserMemberOfRoom($matrixUser, $room)) {
                        $this->matrixApi->admin->addUserToRoom($matrixUser, $room);
                    }

                    $userRoomAddQueue = $this->userRoomAddQueueRepo->read($participantId,
                        $courseSettings->getCourseId());
                    if ($userRoomAddQueue) {
                        $this->userRoomAddQueueRepo->delete($userRoomAddQueue);
                    }
                }
            }
        }

        try {
            $this->courseSettingsRepo->save($courseSettings);
        } catch (Exception $ex) {
            $this->uiUtil->sendFailure($this->plugin->txt("general.update.failed"), true);
            $this->redirectToCommand("showSettings");
        }

        if (!$enableChatIntegration && $room->exists()) {
            $this->redirectToCommand(
                "confirmDisableChatIntegrationForm",
                ["ref_id" => $courseSettings->getCourseId()]
            );
        }

        $this->uiUtil->sendSuccess($this->plugin->txt("general.update.success"), true);
        $this->redirectToCommand("showSettings");
    }

    public function confirmDisableChatIntegrationForm(?DisableChatIntegrationForm $form = null): void
    {
        $courseId = (int) $this->verifyQueryParameter("ref_id");

        $this->mainTpl->loadStandardTemplate();

        if (!$form) {
            $form = new DisableChatIntegrationForm($courseId);
        }
        $this->mainTpl->setContent($form->getHTML());
        $this->mainTpl->printToStdOut();
    }

    public function disableChatIntegrationForm(): void
    {
        $form = new DisableChatIntegrationForm();
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->confirmDisableCourseChatIntegration($form);
            return;
        }

        $courseSettings = $this->courseSettings;
        $form->setValuesByPost();
        $deleteRoom = (bool) $form->getInput("deleteChatRoom");

        if (!$deleteRoom) {
            $this->redirectToCommand("showSettings", [
                "ref_id" => $this->courseSettings->getCourseId()
            ]);
        }

        if (
            $courseSettings->getMatrixRoom()
            && $courseSettings->getMatrixRoom()->exists()
            && $this->matrixApi->admin->deleteRoom($courseSettings->getMatrixRoom())
        ) {
            $courseSettings->setMatrixRoom(null);
            if ($this->courseSettingsRepo->save($courseSettings)) {
                $this->uiUtil->sendSuccess($this->plugin->txt("matrix.chat.room.delete.success"), true);
                $this->redirectToCommand("showSettings", ["ref_id" => $courseSettings->getCourseId()]);
            }
        }

        $this->uiUtil->sendFailure($this->plugin->txt("matrix.chat.room.delete.failed"), true);
    }

    public function getCtrlClassesForCommand(string $cmd): array
    {
        return [ilUIPluginRouterGUI::class, ilMatrixChatClientUIHookGUI::class];
    }
}
