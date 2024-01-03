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

require_once __DIR__ . '/../vendor/autoload.php';

use ILIAS\DI\Container;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Plugin\MatrixChatClient\Libs\ControllerHandler\ControllerHandler;
use ILIAS\Plugin\MatrixChatClient\Controller\ChatCourseSettingsController;
use ILIAS\Plugin\MatrixChatClient\Controller\ChatClientController;
use ILIAS\Plugin\MatrixChatClient\Repository\CourseSettingsRepository;
use ILIAS\Plugin\MatrixChatClient\Controller\UserConfigController;
use ILIAS\Plugin\MatrixChatClient\Utils\UiUtil;
use ILIAS\Refinery\Factory;

/**
 * Class ilMatrixChatClientUIHookGUI
 *
 * @ilCtrl_isCalledBy  ilMatrixChatClientUIHookGUI: ilUIPluginRouterGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilObjCourseGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilCourseRegistrationGUI, ilCourseObjectivesGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilObjCourseGroupingGUI, ilInfoScreenGUI, ilLearningProgressGUI, ilPermissionGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilRepositorySearchGUI, ilConditionHandlerGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilCourseContentGUI, ilPublicUserProfileGUI, ilMemberExportGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilObjectCustomUserFieldsGUI, ilMemberAgreementGUI, ilSessionOverviewGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilColumnGUI, ilContainerPageGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilObjectCopyGUI, ilObjStyleSheetGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilCourseParticipantsGroupsGUI, ilExportGUI, ilCommonActionDispatcherGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilDidacticTemplateGUI, ilCertificateGUI, ilObjectServiceSettingsGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilContainerStartObjectsGUI, ilContainerStartObjectsPageGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilMailMemberSearchGUI, ilBadgeManagementGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilLOPageGUI, ilObjectMetaDataGUI, ilNewsTimelineGUI, ilContainerNewsSettingsGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilCourseMembershipGUI, ilPropertyFormGUI, ilContainerSkillGUI, ilCalendarPresentationGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilMemberExportSettingsGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilLTIProviderObjectSettingGUI, ilObjectTranslationGUI, ilBookingGatewayGUI, ilRepUtilGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilObjGroupGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilGroupRegistrationGUI, ilPermissionGUI, ilInfoScreenGUI, ilLearningProgressGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilPublicUserProfileGUI, ilObjCourseGroupingGUI, ilObjStyleSheetGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilCourseContentGUI, ilColumnGUI, ilContainerPageGUI, ilObjectCopyGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilObjectCustomUserFieldsGUI, ilMemberAgreementGUI, ilExportGUI, ilMemberExportGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilCommonActionDispatcherGUI, ilObjectServiceSettingsGUI, ilSessionOverviewGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilGroupMembershipGUI, ilBadgeManagementGUI, ilMailMemberSearchGUI, ilNewsTimelineGUI, ilContainerNewsSettingsGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilContainerSkillGUI, ilCalendarPresentationGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilLTIProviderObjectSettingGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilObjectMetaDataGUI, ilObjectTranslationGUI, ilPropertyFormGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilPersonalSettingsGUI
 * @ilCtrl_Calls       ilMatrixChatClientUIHookGUI: ilMailOptionsGUI
 */
class ilMatrixChatClientUIHookGUI extends ilUIHookPluginGUI
{
    private ilMatrixChatClientPlugin $plugin;
    private Container $dic;
    private ilCtrl $ctrl;
    private ControllerHandler $controllerHandler;
    private UiUtil $uiUtil;
    private WrapperFactory $httpWrapper;
    private Factory $refinery;

    public function __construct()
    {
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
        $this->dic = $this->plugin->dic;
        $this->ctrl = $this->dic->ctrl();
        $this->uiUtil = new UiUtil();
        $this->controllerHandler = new ControllerHandler($this->plugin);
        $this->httpWrapper = $this->dic->http()->wrapper();
        $this->refinery = $this->dic->refinery();
    }

    public function modifyGUI($a_comp, $a_part, $a_par = array()): void
    {
        if ($a_part !== "sub_tabs") {
            return;
        }

        /**
         * @var ilTabsGUI $tabs
         */
        $tabs = $a_par["tabs"];

        if (!$tabs->hasTabs()) {
            return;
        }

        global $DIC;

        $this->injectChatIntegrationTab($DIC);
        $this->injectChatIntegrationConfigTab($DIC);
        $this->injectChatUserConfigTab($DIC);

        parent::modifyGUI($a_comp, $a_part, $a_par);
    }

    public function getHTML($a_comp, $a_part, $a_par = []) : array
    {
        $tplId = $a_par["tpl_id"] ?? null;
        $html = $a_par["html"] ?? null;

        if (!$html || !$tplId) {
            return $this->uiHookResponse();
        }

        return $this->uiHookResponse();
    }

    public function executeCommand() : void
    {
        $cmdClass = $this->ctrl->getCmdClass();
        $nextClass = $this->ctrl->getNextClass();
        $cmd = $this->ctrl->getCmd();

        $refId = $this->httpWrapper->query()->retrieve(
            'refId',
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        if ($nextClass) {
            foreach ($this->dic->http()->request()->getQueryParams() as $key => $value) {
                $this->ctrl->setParameterByClass($cmdClass, $key, $value);
            }

            if ($nextClass === strtolower(ilPersonalSettingsGUI::class)) {
                $this->ctrl->redirectByClass([ilDashboardGUI::class, $cmdClass], $cmd);
            }

            $objGuiClass = $this->plugin->getObjGUIClassByType(ilObject::_lookupType($refId, true));
            if (!$objGuiClass) {
                $this->dic->logger()->root()->error("Unable to redirect to gui class. Type '{$refId}' is not supported");
                $this->plugin->redirectToHome();
            }

            $this->ctrl->redirectByClass([ilRepositoryGUI::class, $objGuiClass, $cmdClass], $cmd);
        }

        $this->controllerHandler->handleCommand($cmd);
    }

    private function injectChatUserConfigTab(Container $dic) : void
    {
        $tabs = $dic->tabs();
        $referrer = $this->httpWrapper->query()->retrieve(
            'referrer',
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->string(),
                $this->refinery->always(null)
            ])
        );
        if (
            !in_array(
                $this->ctrl->getCmdClass(),
                [
                    ilPersonalSettingsGUI::class,
                    strtolower(ilPersonalSettingsGUI::class),
                ],
                true
            )
            && !$referrer
            && !in_array(
                $referrer,
                [
                    ilPersonalSettingsGUI::class,
                    strtolower(ilPersonalSettingsGUI::class)
                ],
                true
            )
        ) {
            return;
        }

        $tabs->addTab(
            "chat-user-config",
            $this->plugin->txt("config.user.title"),
            $dic->ctrl()->getLinkTargetByClass([
                ilUIPluginRouterGUI::class,
                self::class,
            ], UserConfigController::getCommand("showGeneralConfig"))
        );
    }

    private function injectChatIntegrationTab(Container $dic) : void
    {
        $tabs = $dic->tabs();

        $refId = $this->httpWrapper->query()->retrieve(
            'refId',
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );


        if (!$refId) {
            return;
        }

        if (!$this->plugin->getObjGUIClassByType(ilObject::_lookupType($refId, true))) {
            return;
        }

        $courseSettings = CourseSettingsRepository::getInstance($this->dic->database())->read($refId);

        $viewTabFound = false;
        foreach ($tabs->target as $target) {
            if ($target["id"] === "view_content") {
                $viewTabFound = true;
                break;
            }
        }

        if (
            $viewTabFound
            && $this->plugin->getMatrixCommunicator()->general->serverReachable()
            && $courseSettings->isChatIntegrationEnabled()
            && $this->ctrl->getCmd() !== ChatClientController::getCommand("showChat")
        ) {
            $dic->ctrl()->setParameterByClass(self::class, "ref_id", $refId);

            $tabs->addTab(
                "matrix-chat",
                $this->plugin->txt("chat"),
                $dic->ctrl()->getLinkTargetByClass([
                    ilUIPluginRouterGUI::class,
                    self::class,
                ], ChatClientController::getCommand("showChat"))
            );
        }
    }

    private function injectChatIntegrationConfigTab(Container $dic) : void
    {
        $tabs = $dic->tabs();

        $refId = $this->httpWrapper->query()->retrieve(
            'refId',
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        if (!$refId || $tabs->getActiveTab() !== "settings") {
            return;
        }

        $chatSettingsTabFound = false;
        foreach ($tabs->sub_target as $target) {
            if ($target["id"] === "matrix-chat-course-settings") {
                $chatSettingsTabFound = true;
                break;
            }
        }

        $guiClass = $this->plugin->getObjGUIClassByType(ilObject::_lookupType($refId, true));

        if (!$guiClass || $chatSettingsTabFound) {
            return;
        }



        $dic->ctrl()->setParameterByClass(self::class, "ref_id", $refId);

        if ($this->plugin->getMatrixCommunicator()->general->serverReachable()) {
            //ToDo: gets marked as active together with the the "Multilanguage tab" on group settings tab
            $tabs->addSubTab(
                "matrix-chat-course-settings",
                $this->plugin->txt("matrix.chat.course.settings"),
                $dic->ctrl()->getLinkTargetByClass([
                    ilUIPluginRouterGUI::class,
                    self::class,
                ], ChatCourseSettingsController::getCommand("showSettings"))
            );
        }
    }

    /**
     * @return string[]
     */
    protected function uiHookResponse(string $mode = ilUIHookPluginGUI::KEEP, string $html = "") : array
    {
        return ['mode' => $mode, 'html' => $html];
    }
}
