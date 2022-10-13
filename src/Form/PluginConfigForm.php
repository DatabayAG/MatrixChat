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

namespace ILIAS\Plugin\MatrixChatClient\Form;

use ilPropertyFormGUI;
use ilMatrixChatClientPlugin;
use ilTextInputGUI;
use ilPasswordInputGUI;
use ilMatrixChatClientConfigGUI;

/**
 * Class PluginConfigForm
 * @package ILIAS\Plugin\MatrixChatClient\Form
 * @author  Marvin Beym <mbeym@databay.de>
 */
class PluginConfigForm extends ilPropertyFormGUI
{
    /**
     * @var ilMatrixChatClientPlugin
     */
    private $plugin;

    public function __construct()
    {
        parent::__construct();
        $this->plugin = ilMatrixChatClientPlugin::getInstance();

        $this->addCommandButton("saveSettings", $this->lng->txt("save"));
    }
}
