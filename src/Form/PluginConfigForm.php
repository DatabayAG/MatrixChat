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

namespace ILIAS\Plugin\MatrixChat\Form;

use ilCheckboxGroupInputGUI;
use ilCheckboxInputGUI;
use ilCheckboxOption;
use ilFormSectionHeaderGUI;
use ilGlobalPageTemplate;
use ILIAS\DI\Container;
use ilMatrixChatConfigGUI;
use ilMatrixChatPlugin;
use ilNumberInputGUI;
use ilPasswordInputGUI;
use ilPropertyFormGUI;
use ilTextInputGUI;
use ilUriInputGUI;

class PluginConfigForm extends ilPropertyFormGUI
{
    private ilMatrixChatPlugin $plugin;
    private Container $dic;
    private ilGlobalPageTemplate $mainTpl;

    public const SPECIFY_OTHER_MATRIX_ACCOUNT = "specifyOtherMatrixAccount";
    public const CREATE_ON_CONFIGURED_HOMESERVER = "createOnConfiguredHomeserver";

    public function __construct()
    {
        parent::__construct();
        $this->plugin = ilMatrixChatPlugin::getInstance();
        $this->dic = $this->plugin->dic;
        $this->mainTpl = $this->dic->ui()->mainTemplate();
        $this->mainTpl->addCss($this->plugin->cssFolder("style.css"));

        $this->setFormAction(
            $this->ctrl->getFormActionByClass(
                ilMatrixChatConfigGUI::class,
                ilMatrixChatConfigGUI::CMD_SHOW_SETTINGS
            )
        );
        $this->setId("{$this->plugin->getId()}_{$this->plugin->getPluginName()}_plugin_config_form");
        $this->setTitle($this->plugin->txt("general.plugin.settings"));

        $serverReachable = $this->plugin->getMatrixApi()->serverReachable();

        $allowedUsernameSchemeCharacters = array_map(static function ($char) {
            return "<span style='color: blue; font-weight: bold'>$char</span>";
        }, ["a-z", "0-9", "=", "_", "-", ".", "/", "'"]);

        $this->addGeneralSection();
        $this->addServerSection($serverReachable);
        $this->addAdminUserSection($serverReachable);
        $this->addRestApiUserSection($serverReachable);
        $this->addExternalUserSection($allowedUsernameSchemeCharacters);
        $this->addLocalUserSection($allowedUsernameSchemeCharacters);
        $this->addRoomSection();

        $this->addCommandButton(ilMatrixChatConfigGUI::CMD_SAVE_SETTINGS, $this->lng->txt("save"));
    }

    protected function addGeneralSection(): void
    {
        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->plugin->txt("config.section.general"));
        $this->addItem($section);

        $supportedObjectTypes = new ilCheckboxGroupInputGUI(
            $this->plugin->txt("config.supportedObjectTypes.title"),
            "supportedObjectTypes"
        );
        $supportedObjectTypes->setInfo($this->plugin->txt("config.supportedObjectTypes.info"));

        $supportedObjectTypes->addOption(new ilCheckboxOption(
            $this->lng->txt("crs"),
            "crs"
        ));

        $supportedObjectTypes->addOption(new ilCheckboxOption(
            $this->lng->txt("grp"),
            "grp"
        ));
        $this->addItem($supportedObjectTypes);
    }

    protected function addServerSection($serverReachable): void
    {
        $section = new ilFormSectionHeaderGUI();
        if ($serverReachable) {
            $section->setTitle(sprintf(
                $this->plugin->txt("config.section.server.reachable"),
                $this->plugin->txt("matrix.server.reachable")
            ));
        } else {
            $section->setTitle(sprintf(
                $this->plugin->txt("config.section.server.unreachable"),
                $this->plugin->txt("matrix.server.unreachable")
            ));
        }
        $this->addItem($section);

        $matrixServerUrl = new ilUriInputGUI($this->plugin->txt("matrix.server.url"), "matrixServerUrl");
        $matrixServerUrl->setRequired(true);
        $this->addItem($matrixServerUrl);

        $sharedSecret = new ilPasswordInputGUI($this->plugin->txt("config.sharedSecret.title"), "sharedSecret");
        $sharedSecret->setRequired(true);
        $sharedSecret->setInfo($this->plugin->txt("config.sharedSecret.info"));
        $sharedSecret->setSkipSyntaxCheck(true);
        $sharedSecret->setRetype(false);
        $this->addItem($sharedSecret);
    }

    protected function addAdminUserSection(bool $serverReachable): void
    {
        $section = new ilFormSectionHeaderGUI();
        if ($this->plugin->getMatrixApi()->checkAdminUser()) {
            $section->setTitle(sprintf(
                $this->plugin->txt("config.section.adminAuthentication.valid"),
                $this->plugin->txt("matrix.admin.login.valid")
            ));
        } else {
            if (!$serverReachable) {
                $section->setTitle(sprintf(
                    $this->plugin->txt("config.section.adminAuthentication.invalid"),
                    $this->plugin->txt("matrix.server.unreachable")
                ));
            } else {
                $section->setTitle(sprintf(
                    $this->plugin->txt("config.section.adminAuthentication.invalid"),
                    $this->plugin->txt("matrix.admin.login.invalid")
                ));
            }
        }
        $this->addItem($section);

        $matrixAdminApiToken = new ilPasswordInputGUI($this->plugin->txt("config.admin.apiToken.title"), "matrixAdminApiToken");
        $matrixAdminApiToken->setRequired(true);
        $matrixAdminApiToken->setInfo($this->plugin->txt("config.admin.apiToken.info"));
        $matrixAdminApiToken->setSkipSyntaxCheck(true);
        $matrixAdminApiToken->setRetype(false);
        $this->addItem($matrixAdminApiToken);

        $matrixAdminPasswordRemoveRateLimit = new ilCheckboxInputGUI(
            $this->plugin->txt("config.removeRateLimit"),
            "matrixAdminPasswordRemoveRateLimit"
        );
        $matrixAdminPasswordRemoveRateLimit->setInfo($this->plugin->txt("config.removeRateLimit.info"));
        $this->addItem($matrixAdminPasswordRemoveRateLimit);
    }

    protected function addRestApiUserSection(bool $serverReachable): void
    {
        $section = new ilFormSectionHeaderGUI();
        if ($this->plugin->getMatrixApi()->checkRestApiUser()) {
            $section->setTitle(sprintf(
                $this->plugin->txt("config.section.restApiAuthentication.valid"),
                $this->plugin->txt("matrix.restApiUser.login.valid")
            ));
        } elseif (!$serverReachable) {
            $section->setTitle(sprintf(
                $this->plugin->txt("config.section.restApiAuthentication.invalid"),
                $this->plugin->txt("matrix.server.unreachable")
            ));
        } else {
            $section->setTitle(sprintf(
                $this->plugin->txt("config.section.restApiAuthentication.invalid"),
                $this->plugin->txt("matrix.restApiUser.login.invalid")
            ));
        }
        $this->addItem($section);

        $matrixRestApiUserApiToken = new ilPasswordInputGUI($this->plugin->txt("config.restApiUser.apiToken.title"), "matrixRestApiUserApiToken");
        $matrixRestApiUserApiToken->setRequired(true);
        $matrixRestApiUserApiToken->setInfo($this->plugin->txt("config.restApiUser.apiToken.info"));
        $matrixRestApiUserApiToken->setSkipSyntaxCheck(true);
        $matrixRestApiUserApiToken->setRetype(false);
        $this->addItem($matrixRestApiUserApiToken);

        $matrixRestApiUserRemoveRateLimit = new ilCheckboxInputGUI(
            $this->plugin->txt("config.removeRateLimit"),
            "matrixRestApiUserRemoveRateLimit"
        );
        $matrixRestApiUserRemoveRateLimit->setInfo($this->plugin->txt("config.removeRateLimit.info"));
        $this->addItem($matrixRestApiUserRemoveRateLimit);
    }

    protected function addExternalUserSection(array $allowedCharacters): void
    {
        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->plugin->txt("config.section.user.external"));
        $this->addItem($section);

        $accountOptions = new ilCheckboxGroupInputGUI(
            $this->plugin->txt("config.accountOptions.title"),
            "externalUserOptions"
        );

        $createOnConfiguredHomeserver = new ilCheckboxOption(
            $this->plugin->txt("config.accountOptions.createOnConfiguredHomeserver"),
            self::CREATE_ON_CONFIGURED_HOMESERVER
        );
        $accountOptions->addOption($createOnConfiguredHomeserver);

        $usernameScheme = new ilTextInputGUI(
            $this->plugin->txt("config.usernameScheme.external"),
            "externalUserScheme"
        );
        $usernameScheme->setRequired(true);

        $usernameScheme->setInfo(sprintf(
            $this->plugin->txt("config.usernameScheme.info"),
            implode(", ", $allowedCharacters),
            "- " . implode("<br>- ", array_map(static function ($variable): string {
                return "<span>{</span>$variable<span>}</span>";
            }, array_keys($this->plugin->getUsernameSchemeVariables())))
        ));
        $createOnConfiguredHomeserver->addSubItem($usernameScheme);

        $accountOptions->addOption(new ilCheckboxOption(
            $this->plugin->txt("config.accountOptions.specifyOtherMatrixAccount"),
            self::SPECIFY_OTHER_MATRIX_ACCOUNT
        ));

        $this->addItem($accountOptions);
    }

    protected function addLocalUserSection(array $allowedCharacters): void
    {
        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->plugin->txt("config.section.user.local"));
        $this->addItem($section);

        $accountOptions = new ilCheckboxGroupInputGUI(
            $this->plugin->txt("config.accountOptions.title"),
            "localUserOptions"
        );

        $createOnConfiguredHomeserver = new ilCheckboxOption(
            $this->plugin->txt("config.accountOptions.createOnConfiguredHomeserver"),
            self::CREATE_ON_CONFIGURED_HOMESERVER
        );
        $accountOptions->addOption($createOnConfiguredHomeserver);

        $usernameScheme = new ilTextInputGUI(
            $this->plugin->txt("config.usernameScheme.local"),
            "localUserScheme"
        );
        $usernameScheme->setRequired(true);

        $usernameScheme->setInfo(sprintf(
            $this->plugin->txt("config.usernameScheme.info"),
            implode(", ", $allowedCharacters),
            "- " . implode("<br>- ", array_map(static function ($variable): string {
                return "<span>{</span>$variable<span>}</span>";
            }, array_keys($this->plugin->getUsernameSchemeVariables())))
        ));
        $createOnConfiguredHomeserver->addSubItem($usernameScheme);

        $accountOptions->addOption(new ilCheckboxOption(
            $this->plugin->txt("config.accountOptions.specifyOtherMatrixAccount"),
            self::SPECIFY_OTHER_MATRIX_ACCOUNT
        ));

        $this->addItem($accountOptions);
    }

    protected function addRoomSection(): void
    {
        $section = new ilFormSectionHeaderGUI();
        $section->setTitle($this->plugin->txt("config.section.room"));
        $this->addItem($section);

        $roomPrefix = new ilTextInputGUI(
            $this->plugin->txt("config.room.prefix"),
            "roomPrefix"
        );
        $roomPrefix->setInfo(sprintf(
            $this->plugin->txt("config.room.prefix.info"),
            "- " . implode("<br>- ", array_map(static function ($variable): string {
                return "<span>{</span>$variable<span>}</span>";
            }, array_keys($this->plugin->getRoomSchemeVariables())))
        ));
        $this->addItem($roomPrefix);

        $spaceName = new ilTextInputGUI(
            $this->plugin->txt("config.space.name"),
            "matrixSpaceName"
        );
        $spaceName->setInfo($this->plugin->txt("config.space.name.info"));
        $this->addItem($spaceName);
        $matrixSpaceId = new ilTextInputGUI(
            $this->plugin->txt("config.space.id"),
            "matrixSpaceId"
        );
        $matrixSpaceId->setInfo($this->plugin->txt("config.space.id.info"));

        $matrixSpaceId->setDisabled(true);
        $this->addItem($matrixSpaceId);

        $enableRoomEncryption = new ilCheckboxInputGUI(
            $this->plugin->txt("config.room.encryption.enable.title"),
            "enableRoomEncryption"
        );
        $enableRoomEncryption->setInfo($this->plugin->txt("config.room.encryption.enable.info"));
        $this->addItem($enableRoomEncryption);

        $modifyParticipantPowerLevel = new ilCheckboxInputGUI(
            $this->plugin->txt("config.room.powerLevel.modify.title"),
            "modifyParticipantPowerLevel"
        );
        $modifyParticipantPowerLevel->setInfo($this->plugin->txt("config.room.powerLevel.modify.info"));
        $this->addItem($modifyParticipantPowerLevel);

        $adminPowerLevel = new ilNumberInputGUI(
            $this->lng->txt("il_crs_admin") . " | " . $this->lng->txt("il_grp_admin"),
            "adminPowerLevel"
        );
        $modifyParticipantPowerLevel->addSubItem($adminPowerLevel);

        $tutorPowerLevel = new ilNumberInputGUI(
            $this->lng->txt("il_crs_tutor"),
            "tutorPowerLevel"
        );
        $modifyParticipantPowerLevel->addSubItem($tutorPowerLevel);

        $memberPowerLevel = new ilNumberInputGUI(
            $this->lng->txt("il_crs_member") . " | " . $this->lng->txt("il_grp_member"),
            "memberPowerLevel"
        );
        $modifyParticipantPowerLevel->addSubItem($memberPowerLevel);
    }
}
