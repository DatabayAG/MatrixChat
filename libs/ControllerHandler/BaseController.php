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

use ReflectionClass;
use ILIAS\DI\Container;
use ilGlobalPageTemplate;
use ilMatrixChatClientPlugin;
use ilMatrixChatClientUIHookGUI;
use ilUIPluginRouterGUI;
use ilCtrl;
use ilTabsGUI;
use ilLanguage;
use ILIAS\Plugin\MatrixChatClient\Api\MatrixApiCommunicator;
use ilUtil;

/**
 * Class BaseController
 *
 * @package ILIAS\Plugin\MatrixChatClient\Controller
 * @author  Marvin Beym <mbeym@databay.de>
 */
abstract class BaseController
{
    /**
     * @var MatrixApiCommunicator
     */
    public $matrixApi;
    /**
     * @var ilCtrl
     */
    public $ctrl;
    /**
     * @var ilTabsGUI
     */
    public $tabs;
    /**
     * @var ilLanguage
     */
    public $lng;
    /**
     * @var ilMatrixChatClientPlugin
     */
    public $plugin;
    /**
     * @var ilGlobalPageTemplate
     */
    public $mainTpl;
    /**
     * @var Container
     */
    public $dic;

    /**
     * @var array<string, BaseController> $instances
     */
    private static $instances = [];

    public function __construct(Container $dic)
    {
        $this->dic = $dic;
        $this->mainTpl = $dic->ui()->mainTemplate();
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
        $this->lng = $this->dic->language();
        $this->lng->loadLanguageModule("crs");
        $this->ctrl = $this->dic->ctrl();
        $this->tabs = $this->dic->tabs();
        $this->matrixApi = $this->plugin->matrixApi;
    }

    public static function getInstance(?Container $dic = null) : BaseController
    {
        if (!$dic) {
            global $DIC;
            $dic = $DIC;
        }
        $className = static::class;

        if (!isset(self::$instances[$className])) {
            self::$instances[$className] = new $className($dic);
        }

        return self::$instances[$className];
    }

    public static function getCommand(string $cmd) : string
    {
        return (new ReflectionClass(static::class))->getShortName() . ".$cmd";
    }

    public function redirectToCommand(string $cmd, array $parameters = []) : void
    {
        foreach ($parameters as $key => $value) {
            $this->ctrl->setParameterByClass(ilMatrixChatClientUIHookGUI::class, $key, $value);
        }
        $this->ctrl->redirectByClass(
            [
                ilUIPluginRouterGUI::class,
                ilMatrixChatClientUIHookGUI::class
            ],
            static::getCommand($cmd)
        );
    }

    public function getCommandLink(string $cmd, array $parameters = [], bool $asFormAction = false) : string
    {
        foreach ($parameters as $key => $value) {
            $this->ctrl->setParameterByClass(ilMatrixChatClientUIHookGUI::class, $key, $value);
        }

        if ($asFormAction) {
            return $this->ctrl->getFormActionByClass(
                [
                    ilUIPluginRouterGUI::class,
                    ilMatrixChatClientUIHookGUI::class
                ],
                static::getCommand($cmd)
            );
        }

        return $this->ctrl->getLinkTargetByClass(
            [
                ilUIPluginRouterGUI::class,
                ilMatrixChatClientUIHookGUI::class
            ],
            static::getCommand($cmd)
        );
    }

    public function verifyQueryParameter(string $parameterName) : string
    {
        $query = $this->dic->http()->request()->getQueryParams();
        if (!isset($query[$parameterName])) {
            ilUtil::sendFailure(sprintf($this->plugin->txt("general.plugin.requiredParameterMissing"), $parameterName), true);
            $this->plugin->redirectToHome();
        }
        return $query[$parameterName];
    }
}
