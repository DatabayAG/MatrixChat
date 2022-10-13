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
use ILIAS\Plugin\MatrixChatClient\Libs\ControllerHandler\ControllerHandler;

/**
* Class ilMatrixChatClientUIHookGUI
* @ilCtrl_isCalledBy ilMatrixChatClientUIHookGUI: ilUIPluginRouterGUI
*/
class ilMatrixChatClientUIHookGUI extends ilUIHookPluginGUI
{
    /**
     * @var ilMatrixChatClientPlugin
     */
    private $plugin;
    /**
     * @var Container
     */
    private $dic;
    /**
     * @var ilCtrl
     */
    private $ctrl;
    /**
     * @var ControllerHandler
     */
    private $controllerHandler;

    public function __construct()
    {
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
        $this->dic = $this->plugin->dic;
        $this->ctrl = $this->dic->ctrl();
        $this->controllerHandler = new ControllerHandler($this->plugin);
    }

    public function getHTML($a_comp, $a_part, $a_par = []) : array
    {
        $tplId = $a_par["tpl_id"];
        $html = $a_par["html"];

        if (!$tplId || !$html) {
            return $this->uiHookResponse();
        }

        return $this->uiHookResponse();
    }

    public function executeCommand() : void
    {
        $this->controllerHandler->handleCommand($this->plugin->dic->ctrl()->getCmd());
    }

    /**
    * Returns the array used to replace the html content
    * @param string $mode
    * @param string $html
    * @return string[]
    */
    protected function uiHookResponse(string $mode = ilUIHookPluginGUI::KEEP, string $html = "") : array
    {
        return ['mode' => $mode, 'html' => $html];
    }
}
