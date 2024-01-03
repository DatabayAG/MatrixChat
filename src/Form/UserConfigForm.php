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

namespace ILIAS\Plugin\MatrixChatClient\Form;

use ilPropertyFormGUI;
use ilMatrixChatClientPlugin;
use ilRadioGroupInputGUI;
use ilRadioOption;
use ILIAS\DI\Container;
use ilGlobalPageTemplate;
use ILIAS\Plugin\MatrixChatClient\Controller\UserConfigController;

/**
 * Class LocalAuthModeRegisterForm
 *
 * @package ILIAS\Plugin\MatrixChatClient\Form
 * @author  Marvin Beym <mbeym@databay.de>
 */
class UserConfigForm extends ilPropertyFormGUI
{
    private ilMatrixChatClientPlugin $plugin;
    private ilGlobalPageTemplate $mainTpl;
    private Container $dic;

    public function __construct()
    {
        global $DIC;
        parent::__construct();
        $this->dic = $DIC;
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
        $this->mainTpl = $this->dic->ui()->mainTemplate();
        $this->setTitle($this->plugin->txt("config.user.generalSettings"));
        $this->setFormAction(UserConfigController::getInstance()->getCommandLink("showGeneralConfig", [], true));

        $matrixAuthMethod = new ilRadioGroupInputGUI($this->plugin->txt("config.user.authMethod"), "authMethod");

        $matrixAuthMethod->addOption(new ilRadioOption(
            $this->plugin->txt("config.user.useExternalAccount"),
            "usingExternal"
        ));
        $matrixAuthMethod->addOption(
            new ilRadioOption(
                $this->plugin->txt("config.user.loginOrCreate"),
                "loginOrCreate"
            )
        );

        $this->addItem($matrixAuthMethod);

        $this->addCommandButton(UserConfigController::getCommand("saveGeneralConfig"), $this->lng->txt("save"));
    }
}
