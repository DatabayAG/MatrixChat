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

use ILIAS\DI\Container;
use ILIAS\Plugin\MatrixChatClient\Libs\JsonTranslationLoader\JsonTranslationLoader;
use ILIAS\Plugin\MatrixChatClient\Model\PluginConfig;
use ILIAS\Plugin\MatrixChatClient\Api\MatrixApiCommunicator;
use ILIAS\Plugin\MatrixChatClient\Libs\IliasConfigLoader\Exception\ConfigLoadException;

require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Class ilMatrixChatClientPlugin
 */
class ilMatrixChatClientPlugin extends ilUserInterfaceHookPlugin
{
    /** @var string */
    public const CTYPE = "Services";
    /** @var string */
    public const CNAME = "UIComponent";
    /** @var string */
    public const SLOT_ID = "uihk";

    /** @var string */
    public const PNAME = "MatrixChatClient";

    /**
     * @var ilMatrixChatClientPlugin|null
     */
    private static $instance = null;
    /**
     * @var PluginConfig
     */
    private $pluginConfig;
    /**
     * @var MatrixApiCommunicator
     */
    public $matrixApi;
    /**
     * @var Container
     */
    public $dic;
    /**
     * @var ilCtrl
     */
    private $ctrl;
    /**
     * @var ilSetting
     */
    public $settings;

    /**
     * @throws ConfigLoadException
     */
    public function __construct()
    {
        global $DIC;
        $this->dic = $DIC;
        $this->ctrl = $this->dic->ctrl();
        $this->settings = new ilSetting(self::class);
        $this->pluginConfig = (new PluginConfig($this->settings))->load();
        $this->matrixApi = new MatrixApiCommunicator($this, $this->pluginConfig->getmatrixServerUrl());
        parent::__construct();
    }

    public function getPluginName() : string
    {
        return self::PNAME;
    }

    /**
     * @return ilMatrixChatClientPlugin
     * @noinspection PhpIncompatibleReturnTypeInspection
     */
    public static function getInstance() : ilMatrixChatClientPlugin
    {
        return self::$instance ?? (self::$instance = ilPluginAdmin::getPluginObject(
            self::CTYPE,
            self::CNAME,
            self::SLOT_ID,
            self::PNAME
        ));
    }

    public function assetsFolder(string $file = "") : string
    {
        return $this->getDirectory() . "/assets/$file";
    }

    public function cssFolder(string $file = "") : string
    {
        return $this->assetsFolder("css/$file");
    }

    public function imagesFolder(string $file = "") : string
    {
        return $this->assetsFolder("images/$file");
    }

    public function templatesFolder(string $file = "") : string
    {
        return $this->assetsFolder("templates/$file");
    }

    public function jsFolder(string $file = "") : string
    {
        return $this->assetsFolder("js/$file");
    }

    public function redirectToHome() : void
    {
        if ($this->isAtLeastIlias6()) {
            $this->ctrl->redirectByClass("ilDashboardGUI", "show");
        } else {
            $this->ctrl->redirectByClass("ilPersonalDesktopGUI");
        }
    }

    public function isUserAdmin(?int $userId = null, ?int $roleId = null) : bool
    {
        if ($userId === null) {
            $userId = $this->dic->user->getId();
        }

        if ($roleId === null) {
            if (defined("SYSTEM_ROLE_ID")) {
                $roleId = (int) SYSTEM_ROLE_ID;
            } else {
                $roleId = 2;
            }
        }

        $roleIds = [];

        foreach ($this->dic->rbac()->review()->assignedGlobalRoles($userId) as $id) {
            $roleIds[] = (int) $id;
        }

        return in_array($roleId, $roleIds, true);
    }

    public function isAtLeastIlias6() : bool
    {
        return version_compare(ILIAS_VERSION_NUMERIC, "6", ">=");
    }

    public function denyConfigIfPluginNotActive() : void
    {
        if (!$this->isActive()) {
            ilUtil::sendFailure($this->txt("general.plugin.notActivated"), true);
            $this->ctrl->redirectByClass(ilObjComponentSettingsGUI::class, "view");
        }
    }

    protected function beforeUninstall() : bool
    {
        $this->settings->deleteAll();
        return parent::beforeUninstall();
    }

    public function updateLanguages($a_lang_keys = null) : void
    {
        try {
            $jsonTranslationLoader = new JsonTranslationLoader($this->getDirectory() . "/lang");
            $jsonTranslationLoader->load();
        } catch (Exception $e) {
        }
        parent::updateLanguages($a_lang_keys);
    }

    public function getPluginConfig() : PluginConfig
    {
        return $this->pluginConfig;
    }

    /**
     * Called by ilias event system to hook into afterLogin event.
     *
     * @param $a_component
     * @param $a_event
     * @param $a_parameter
     * @return void
     * @throws JsonException
     */
    public function handleEvent($a_component, $a_event, $a_parameter) : void
    {
        if (
            $a_event !== "afterLogin"
            || PHP_SAPI === 'cli'
        ) {
            return;
        }

        //Make sure matrixUser in session is always unset first.
        ilSession::set("matrixUser", null);


        if (!$this->pluginConfig->isUseLdapAutoLogin()
            || $this->pluginConfig->getLoginMethod() !== "byLdap") {
            return;
        }

        $post = $this->dic->http()->request()->getParsedBody();

        if (!isset($post["username"], $post["password"])) {
            return;
        }

        try {
            $matrixUser = $this->matrixApi->user->login(
                $this->dic->user()->getId(),
                $post["username"],
                $post["password"]
            );
            if (!$matrixUser) {
                return;
            }
        } catch (Exception $e) {
            return;
        }

        ilSession::set("matrixUser", json_encode($matrixUser, JSON_THROW_ON_ERROR));

        //ToDo: Continue here - check if user can now immediately access chat (user needs to be added to course first)
    }
}
