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

use ILIAS\Plugin\MatrixChatClient\Form\ChatCourseSettingsForm;
use ilRepositoryGUI;
use ilObjCourseGUI;
use ilUtil;
use ILIAS\Plugin\MatrixChatClient\Model\CourseSettings;
use Exception;
use ILIAS\DI\Container;
use ILIAS\Plugin\MatrixChatClient\Repository\CourseSettingsRepository;

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

    public function __construct(Container $dic)
    {
        parent::__construct($dic);
        $this->courseSettingsRepo = CourseSettingsRepository::getInstance($dic->database());
    }

    public function showSettings(?ChatCourseSettingsForm $form = null) : void
    {
        $refId = $this->verifyRefIdQueryParameter();
        if (!$form) {
            $form = new ChatCourseSettingsForm();
            $courseSettings = $this->courseSettingsRepo->read($refId) ?? new CourseSettings();
            $form->setValuesByArray(
                [
                    "matrixRoomId" => $courseSettings->getMatrixRoomId(),
                    "chatIntegrationEnabled" => $courseSettings->isChatIntegrationEnabled(),
                    "courseId" => $courseSettings->getCourseId()
                ],
                true
            );
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

        $courseId = (int) $this->verifyRefIdQueryParameter();
        $courseSettings = $this->courseSettingsRepo->read($courseId) ?? new CourseSettings();
        if (!$courseSettings) {
            $courseSettings = (new CourseSettings())
                ->setCourseId($courseId);
        }

        $courseSettings->setChatIntegrationEnabled((bool) $form->getInput("chatIntegrationEnabled"));

        try {
            $this->courseSettingsRepo->save($courseSettings);
            ilUtil::sendSuccess($this->plugin->txt("updateSuccessful"), true);
        } catch (Exception $ex) {
            ilUtil::sendFailure($this->plugin->txt("updateFailed"), true);
        }

        $this->redirectToCommand("showSettings");
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
