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

use ILIAS\Plugin\MatrixChatClient\Form\ChatCourseSettingsForm;
use ilRepositoryGUI;
use ilObjCourseGUI;
use ilUtil;
use Exception;
use ILIAS\DI\Container;
use ILIAS\Plugin\MatrixChatClient\Repository\CourseSettingsRepository;
use ilObjCourse;
use ILIAS\Plugin\MatrixChatClient\Form\DisableCourseChatIntegrationForm;
use ilCourseParticipants;
use ILIAS\Plugin\MatrixChatClient\Model\UserConfig;
use ilObjUser;
use ILIAS\Plugin\MatrixChatClient\Repository\UserRoomAddQueueRepository;

/**
 * Class ChatCourseSettingsController
 *
 * @package ILIAS\Plugin\MatrixChatClient\Controller
 * @author  Marvin Beym <mbeym@databay.de>
 */
class ChatCourseSettingsController extends BaseController
{
    /**
     * @var CourseSettingsRepository
     */
    private $courseSettingsRepo;
    /**
     * @var UserRoomAddQueueRepository
     */
    private $userRoomAddQueueRepo;

    public function __construct(Container $dic)
    {
        parent::__construct($dic);
        $this->courseSettingsRepo = CourseSettingsRepository::getInstance($dic->database());
        $this->userRoomAddQueueRepo = UserRoomAddQueueRepository::getInstance($dic->database());
    }

    public function showSettings(?ChatCourseSettingsForm $form = null) : void
    {
        $refId = (int) $this->verifyQueryParameter("ref_id");
        if (!$form) {
            $form = new ChatCourseSettingsForm();
            $courseSettings = $this->courseSettingsRepo->read($refId);

            if (
                $courseSettings->isChatIntegrationEnabled()
                && !$courseSettings->getMatrixRoom()
            ) {
                ilUtil::sendFailure($this->plugin->txt("matrix.chat.room.notFoundEvenThoughEnabled"), true);
            }

            $form->setValuesByArray([
                "chatIntegrationEnabled" => $courseSettings->isChatIntegrationEnabled(),
            ], true);
        }

        $this->mainTpl->setTitle($this->plugin->txt("matrix.chat.course.settings"));
        $this->mainTpl->loadStandardTemplate();

        $this->ctrl->setParameterByClass(ilObjCourseGUI::class, "ref_id", $refId);
        $this->tabs->setBackTarget(
            $this->lng->txt("crs_settings"),
            $this->ctrl->getLinkTargetByClass(
                [
                    ilRepositoryGUI::class,
                    ilObjCourseGUI::class
                ],
                "edit"
            )
        );

        $this->mainTpl->setContent($form->getHTML());
        $this->mainTpl->printToStdOut();
    }

    public function saveSettings() : void
    {
        $form = new ChatCourseSettingsForm();

        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->showSettings($form);
        }

        $form->setValuesByPost();

        $courseId = (int) $this->verifyQueryParameter("ref_id");
        $courseSettings = $this->courseSettingsRepo->read($courseId);

        $enableChatIntegration = (bool) $form->getInput("chatIntegrationEnabled");

        $courseSettings->setChatIntegrationEnabled($enableChatIntegration);

        $room = $courseSettings->getMatrixRoom();

        if ($enableChatIntegration && (!$room || !$room->exists())) {
            $room = $this->matrixApi->admin->createRoom(ilObjCourse::_lookupTitle(ilObjCourse::_lookupObjId($courseId)));
            $courseSettings->setMatrixRoom($room);
        }

        if ($enableChatIntegration) {
            if ($room) {
                foreach ((ilCourseParticipants::getInstance($courseId))->getParticipants() as $participantId) {
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

                    $userRoomAddQueue = $this->userRoomAddQueueRepo->read($participantId, $courseId);
                    if ($userRoomAddQueue) {
                        $this->userRoomAddQueueRepo->delete($userRoomAddQueue);
                    }
                }
            }
        }

        try {
            $this->courseSettingsRepo->save($courseSettings);
        } catch (Exception $ex) {
            ilUtil::sendFailure($this->plugin->txt("general.update.failed"), true);
            $this->redirectToCommand("showSettings");
        }

        if (!$enableChatIntegration && $room->exists()) {
            $this->redirectToCommand(
                "confirmDisableCourseChatIntegration",
                ["ref_id" => $courseSettings->getCourseId()]
            );
        }

        ilUtil::sendSuccess($this->plugin->txt("general.update.success"), true);
        $this->redirectToCommand("showSettings");
    }

    public function confirmDisableCourseChatIntegration(?DisableCourseChatIntegrationForm $form = null) : void
    {
        $courseId = (int) $this->verifyQueryParameter("ref_id");

        $this->mainTpl->loadStandardTemplate();

        if (!$form) {
            $form = new DisableCourseChatIntegrationForm($courseId);
        }
        $this->mainTpl->setContent($form->getHTML());
        $this->mainTpl->printToStdOut();
    }

    public function disableCourseChatIntegration() : void
    {
        $form = new DisableCourseChatIntegrationForm();
        if (!$form->checkInput()) {
            $form->setValuesByPost();
            $this->confirmDisableCourseChatIntegration($form);
        }

        $form->setValuesByPost();
        $courseId = (int) $this->verifyQueryParameter("ref_id");
        $deleteRoom = (bool) $form->getInput("deleteChatRoom");

        if (!$deleteRoom) {
            $this->redirectToCommand("showSettings", ["ref_id" => $courseId]);
        }

        $courseSettings = $this->courseSettingsRepo->read($courseId);
        if (!$courseSettings) {
            $this->redirectToCommand("showSettings", ["ref_id" => $courseId]);
        }

        if (
            $courseSettings->getMatrixRoom()
            && $courseSettings->getMatrixRoom()->exists()
            && $this->matrixApi->admin->deleteRoom($courseSettings->getMatrixRoom())
        ) {
            $courseSettings->setMatrixRoom(null);
            if ($this->courseSettingsRepo->save($courseSettings)) {
                ilUtil::sendSuccess($this->plugin->txt("matrix.chat.room.delete.success"), true);
                $this->redirectToCommand("showSettings", ["ref_id" => $courseId]);
            }
        }

        ilUtil::sendFailure($this->plugin->txt("matrix.chat.room.delete.failed"), true);
    }

    private function verifyRefIdQueryParameter() : int
    {
        $query = $this->dic->http()->request()->getQueryParams();
        if (!isset($query["ref_id"]) || !$query["ref_id"]) {
            ilUtil::sendFailure($this->plugin->txt("required_parameter_missing"), true);
            $this->plugin->redirectToHome();
        }
        return (int) $query["ref_id"];
    }
}
