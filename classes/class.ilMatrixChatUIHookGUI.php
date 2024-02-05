<?php

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

declare(strict_types=1);

require_once __DIR__ . "/../vendor/autoload.php";

use ILIAS\DI\Container;
use ILIAS\HTTP\Wrapper\WrapperFactory;
use ILIAS\Plugin\Libraries\ControllerHandler\ControllerHandler;
use ILIAS\Plugin\Libraries\ControllerHandler\UiUtils;
use ILIAS\Plugin\MatrixChat\Controller\BaseUserConfigController;
use ILIAS\Plugin\MatrixChat\Controller\ChatController;
use ILIAS\Plugin\MatrixChat\Controller\ExternalUserConfigController;
use ILIAS\Plugin\MatrixChat\Controller\LocalUserConfigController;
use ILIAS\Plugin\MatrixChat\Repository\CourseSettingsRepository;
use ILIAS\Refinery\Factory;

/**
 * @ilCtrl_isCalledBy  ilMatrixChatUIHookGUI: ilUIPluginRouterGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilObjCourseGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilCourseRegistrationGUI, ilCourseObjectivesGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilObjCourseGroupingGUI, ilInfoScreenGUI, ilLearningProgressGUI,
 *                     ilPermissionGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilRepositorySearchGUI, ilConditionHandlerGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilCourseContentGUI, ilPublicUserProfileGUI, ilMemberExportGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilObjectCustomUserFieldsGUI, ilMemberAgreementGUI,
 *                     ilSessionOverviewGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilColumnGUI, ilContainerPageGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilObjectCopyGUI, ilObjectContentStyleSettingsGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilCourseParticipantsGroupsGUI, ilExportGUI,
 *                     ilCommonActionDispatcherGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilDidacticTemplateGUI, ilCertificateGUI, ilObjectServiceSettingsGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilContainerStartObjectsGUI, ilContainerStartObjectsPageGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilMailMemberSearchGUI, ilBadgeManagementGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilLOPageGUI, ilObjectMetaDataGUI, ilNewsTimelineGUI,
 *                     ilContainerNewsSettingsGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilCourseMembershipGUI, ilPropertyFormGUI, ilContainerSkillGUI,
 *                     ilCalendarPresentationGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilMemberExportSettingsGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilLTIProviderObjectSettingGUI, ilObjectTranslationGUI,
 *                     ilBookingGatewayGUI, ilRepUtilGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilObjGroupGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilGroupRegistrationGUI, ilPermissionGUI, ilInfoScreenGUI,
 *                     ilLearningProgressGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilPublicUserProfileGUI, ilObjCourseGroupingGUI, ilObjStyleSheetGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilCourseContentGUI, ilColumnGUI, ilContainerPageGUI,
 *                     ilObjectCopyGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilObjectCustomUserFieldsGUI, ilMemberAgreementGUI, ilExportGUI,
 *                     ilMemberExportGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilCommonActionDispatcherGUI, ilObjectServiceSettingsGUI,
 *                     ilSessionOverviewGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilGroupMembershipGUI, ilBadgeManagementGUI, ilMailMemberSearchGUI,
 *                     ilNewsTimelineGUI, ilContainerNewsSettingsGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilContainerSkillGUI, ilCalendarPresentationGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilLTIProviderObjectSettingGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilObjectMetaDataGUI, ilObjectTranslationGUI, ilPropertyFormGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilPersonalSettingsGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilMailOptionsGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilObjFileUploadHandlerGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilFormPropertyDispatchGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilFormPropertyDispatchGUI
 * @ilCtrl_Calls       ilMatrixChatUIHookGUI: ilObjectMetaDataGUI, ilAdvancedMDSettingsGUI, ilPropertyFormGUI,
 *                     ilTaxMDGUI, ilObjTaxonomyGUI
 */
class ilMatrixChatUIHookGUI extends ilUIHookPluginGUI
{
    private ilMatrixChatPlugin $plugin;
    private Container $dic;
    private ilCtrl $ctrl;
    private ControllerHandler $controllerHandler;
    private UiUtils $uiUtil;
    private WrapperFactory $httpWrapper;
    private Factory $refinery;
    private ilAccessHandler $access;

    public function __construct()
    {
        $this->plugin = ilMatrixChatPlugin::getInstance();
        $this->dic = $this->plugin->dic;
        $this->ctrl = $this->dic->ctrl();
        $this->uiUtil = new UiUtils();
        $this->httpWrapper = $this->dic->http()->wrapper();
        $this->refinery = $this->dic->refinery();
        $this->access = $this->dic->access();
        $this->controllerHandler = new ControllerHandler(
            "ILIAS\Plugin\MatrixChat\Controller",
            $this->plugin->txt("general.cmd.undefined"),
            $this->plugin->txt("general.cmd.notFound"),
            $this->plugin->txt("general.plugin.requiredParameterMissing")
        );
    }

    public function modifyGUI($a_comp, $a_part, $a_par = array()): void
    {
        if ($a_part !== "sub_tabs") {
            return;
        }

        /** @var ilTabsGUI $tabs */
        $tabs = $a_par["tabs"];

        if (!$tabs->hasTabs()) {
            return;
        }

        global $DIC;

        $this->injectChatTab($DIC);
        $this->injectChatUserConfigTab($DIC);

        parent::modifyGUI($a_comp, $a_part, $a_par);
    }

    public function getHTML($a_comp, $a_part, $a_par = []): array
    {
        $tplId = $a_par["tpl_id"] ?? null;
        $html = $a_par["html"] ?? null;

        if (!$html || !$tplId) {
            return $this->uiHookResponse();
        }

        return $this->uiHookResponse();
    }

    public function executeCommand(): void
    {
        $cmdClass = $this->ctrl->getCmdClass();
        $nextClass = $this->ctrl->getNextClass();
        $cmd = $this->ctrl->getCmd();

        $refId = $this->httpWrapper->query()->retrieve(
            "ref_id",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        if ($nextClass) {
            if (!$refId) {
                $this->uiUtil->sendFailure(sprintf(
                    $this->plugin->txt("general.plugin.requiredParameterMissing"),
                    "ref_id"
                ), true);
                $this->plugin->redirectToHome();
            }

            $objType = ilObject::_lookupType($refId, true);

            $this->ctrl->setParameterByClass($cmdClass, "ref_id", $refId);

            switch ($nextClass) {
                case strtolower(ilPersonalSettingsGUI::class):
                    $this->ctrl->redirectByClass([ilDashboardGUI::class, $cmdClass], $cmd);
                    break;
                case strtolower(ilMailOptionsGUI::class):
                    $this->ctrl->setParameterByClass($cmdClass, "referrer", ilPersonalSettingsGUI::class);
                    $this->ctrl->redirectByClass(
                        [ilDashboardGUI::class, ilPersonalSettingsGUI::class, $cmdClass],
                        $cmd
                    );
                    break;
                case strtolower(ilObjectMetaDataGUI::class):
                    $this->ctrl->redirectByClass([
                        ilRepositoryGUI::class,
                        $objType === "crs" ? ilObjCourseGUI::class : ilObjGroupGUI::class,
                        ilObjectMetaDataGUI::class,
                        ilMDEditorGUI::class
                    ], $cmd);
                    break;
            }

            $objGuiClass = $this->plugin->getObjGUIClassByType($objType);
            if (!$objGuiClass) {
                $this->dic->logger()->root()->error("Unable to redirect to gui class. Type '{$refId}' is not supported");
                $this->plugin->redirectToHome();
            }

            $this->ctrl->redirectByClass(array_unique([ilRepositoryGUI::class, $objGuiClass, $cmdClass]), $cmd);
        }

        $this->controllerHandler->handleCommand($cmd);
    }

    private function injectChatUserConfigTab(Container $dic): void
    {
        $tabs = $dic->tabs();
        $referrer = $this->httpWrapper->query()->retrieve(
            "referrer",
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

        /** @var LocalUserConfigController $localUserConfigController */
        $localUserConfigController = $this->controllerHandler->getController(LocalUserConfigController::class);

        /** @var ExternalUserConfigController $externalUserConfigController */
        $externalUserConfigController = $this->controllerHandler->getController(ExternalUserConfigController::class);

        $tabs->addTab(
            BaseUserConfigController::TAB_USER_CHAT_CONFIG,
            $this->plugin->txt("config.user.title"),
            (int) $this->dic->user()->getAuthMode() === ilAuthUtils::AUTH_LOCAL
                ? $localUserConfigController->getCommandLink(BaseUserConfigController::CMD_SHOW_USER_CHAT_CONFIG)
                : $externalUserConfigController->getCommandLink(BaseUserConfigController::CMD_SHOW_USER_CHAT_CONFIG)
        );
    }

    private function injectChatTab(Container $dic): void
    {
        $tabs = $dic->tabs();

        $refId = $this->httpWrapper->query()->retrieve(
            "ref_id",
            $this->refinery->byTrying([
                $this->refinery->kindlyTo()->int(),
                $this->refinery->always(null)
            ])
        );

        if (!$refId) {
            return;
        }

        $pluginConfig = $this->plugin->getPluginConfig();

        $objType = ilObject::_lookupType($refId, true);
        if (!in_array($objType, $pluginConfig->getSupportedObjectTypes(), true)) {
            return;
        }

        if (!$this->plugin->getObjGUIClassByType($objType)) {
            return;
        }

        $courseSettings = CourseSettingsRepository::getInstance($this->dic->database())->read($refId);

        if (
            (
                $courseSettings->getMatrixRoomId()
                || $this->access->checkAccess("write", "", $refId)
            ) && !str_starts_with($this->ctrl->getCmd(), "ChatController.")
        ) {
            /** @var ChatController $chatController */
            $chatController = $this->controllerHandler->getController(ChatController::class);

            $tabs->addTab(
                ChatController::TAB_CHAT,
                $this->plugin->txt("chat"),
                $chatController->getCommandLink(ChatController::CMD_SHOW_CHAT, [
                    "ref_id" => $refId
                ])
            );
        }
    }

    /** @return string[] */
    protected function uiHookResponse(string $mode = ilUIHookPluginGUI::KEEP, string $html = ""): array
    {
        return ["mode" => $mode, "html" => $html];
    }
}
