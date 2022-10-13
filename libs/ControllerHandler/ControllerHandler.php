<?php declare(strict_types=1);
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

namespace ILIAS\Plugin\MatrixChatClient\Libs\ControllerHandler;

use ilUtil;
use ilPlugin;
use ilMatrixChatClientPlugin;
use ILIAS\Plugin\MatrixChatClient\Controller\BaseController;

/**
 * Class ControllerHandler
 *
 * @author Marvin Beym <mbeym@databay.de>
 */
class ControllerHandler
{
    /**
     * @var ilMatrixChatClientPlugin
     */
    private $plugin;

    public function __construct(ilMatrixChatClientPlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function handleCommand(string $cmd) : void
    {
        if (!isset($cmd)) {
            ilUtil::sendFailure($this->plugin->txt("general.cmd.undefined"), true);
            $this->plugin->redirectToHome();
        }
        if (method_exists($this, $cmd)) {
            $this->{$cmd}();
        } else {
            $match = [];
            if (preg_match("/([a-zA-Z0-9]*)\.([a-zA-Z0-9]*)/", $cmd, $match)) {
                $controller = $match[1];
                $controllerCmd = $match[2];

                $namespace = "ILIAS\\Plugin\\MatrixChatClient\\Controller";

                $className = "$namespace\\$controller";
                if (class_exists($className)) {
                    global $DIC;
                    $class = new $className($DIC);

                    if (method_exists($class, $controllerCmd) && $class instanceof BaseController) {
                        $class->{$controllerCmd}();
                        return;
                    }
                }
            }
            ilUtil::sendFailure(sprintf($this->plugin->txt("general.cmd.notFound"), $cmd), true);
            $this->plugin->redirectToHome();
        }
    }
}