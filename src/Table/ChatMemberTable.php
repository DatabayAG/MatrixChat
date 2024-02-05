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

namespace ILIAS\Plugin\MatrixChatClient\Table;

use Exception;
use ilCtrlInterface;
use ILIAS\DI\Container;
use ILIAS\Plugin\MatrixChatClient\Controller\ChatController;
use ILIAS\Plugin\MatrixChatClient\Model\ChatMember;
use ILIAS\UI\Factory;
use ILIAS\UI\Renderer;
use ilLegacyFormElementsUtil;
use ilMatrixChatClientPlugin;
use ilMatrixChatClientUIHookGUI;
use ilObjRole;
use ilRbacReview;
use ilSelectInputGUI;
use ilTable2GUI;
use ilTextInputGUI;

class ChatMemberTable extends ilTable2GUI
{
    private ilMatrixChatClientPlugin $plugin;
    private Container $dic;
    private ChatController $controller;
    private ilRbacReview $rbacReview;
    private int $refId;
    private Renderer $uiRenderer;
    private Factory $uiFactory;

    public function __construct(int $refId, ChatController $controller)
    {
        global $DIC;
        $this->dic = $DIC;
        $this->refId = $refId;
        $this->controller = $controller;
        $this->plugin = ilMatrixChatClientPlugin::getInstance();
        $this->rbacReview = $this->dic->rbac()->review();
        $this->uiRenderer = $this->dic->ui()->renderer();
        $this->uiFactory = $this->dic->ui()->factory();

        $this->setId("ChatMemberTable");
        $this->setTitle($this->plugin->txt("matrix.chat.members"));

        parent::__construct(new ilMatrixChatClientUIHookGUI());

        $this->setExternalSorting(false);
        $this->setExternalSegmentation(false);

        $this->setDefaultOrderField("name");
        $this->setDefaultOrderDirection("desc");
        $this->setEnableHeader(true);

        $this->setFormAction($controller->getCommandLink(
            ChatController::CMD_SHOW_CHAT_MEMBERS,
            ["ref_id" => $this->refId],
            true
        ));
        $this->setRowTemplate($this->plugin->templatesFolder("table/tpl.chatMemberTable_row.html"));

        $this->addColumn('', '', "1%", true);
        $this->addColumns([
            $this->lng->txt("name") => "name",
            $this->lng->txt("username") => "username",
            $this->lng->txt("role") => "role",
            $this->plugin->txt("matrix.chat.status") => "status",
            $this->plugin->txt("matrix.user.account") => "matrixUserId",
            $this->plugin->txt("matrix.chat.invite") => "",
        ]);

        $this->setSelectAllCheckbox('userId');
        $this->addMultiCommand(
            ChatController::getCommand(ChatController::CMD_INVITE_SELECTED_PARTICIPANTS),
            $this->plugin->txt('matrix.chat.invite')
        );

        $this->setSelectAllCheckbox('userId');
        $this->initFilter();
    }

    private function addColumns(array $columns): void
    {
        foreach ($columns as $text => $sortField) {
            $this->addColumn($text, $sortField);
        }
    }

    /**
     * @param ChatMember[] $chatMembers
     */
    public function buildTableData(array $chatMembers): array
    {
        $tableData = [];

        foreach ($this->filterData($chatMembers) as $chatMember) {
            $inviteButton = null;
            if (in_array(
                $chatMember->getStatus(),
                [
                        ChatController::USER_STATUS_LEAVE,
                        ChatController::USER_STATUS_NO_INVITE,
                    ],
                true
            ) || ($chatMember->getStatus() === ChatController::USER_STATUS_QUEUE && $chatMember->getMatrixUserId())) {
                $inviteButton = $this->uiFactory->button()->standard(
                    $this->plugin->txt("matrix.chat.invite"),
                    $this->controller->getCommandLink(ChatController::CMD_INVITE_PARTICIPANT, [
                        "ref_id" => $this->refId,
                        "userId" => $chatMember->getUserId()
                    ]) . "&" . ilCtrlInterface::CMD_MODE_ASYNC
                );
            }

            switch ($chatMember->getStatus()) {
                case ChatController::USER_STATUS_JOIN:
                    $statusIcon = $this->uiFactory->symbol()->glyph()->apply();
                    break;
                case ChatController::USER_STATUS_INVITE:
                case ChatController::USER_STATUS_QUEUE:
                    $statusIcon = $this->uiFactory->symbol()->glyph()->time();
                    break;
                case ChatController::USER_STATUS_LEAVE:
                case ChatController::USER_STATUS_BAN:
                    $statusIcon = $this->uiFactory->symbol()->glyph()->close();
                    break;
                case ChatController::USER_STATUS_NO_INVITE:
                default:
                    $statusIcon = $this->uiFactory->symbol()->glyph()->help();
                    break;
            }

            $inviteText = "";
            if ($inviteButton && $chatMember->getMatrixUserId()) {
                $inviteText = "<span class='inviteButton-wrapper'>{$this->uiRenderer->render($inviteButton)}</span>";
            } elseif (!$chatMember->getMatrixUserId()) {
                $inviteText = $this->plugin->txt("matrix.chat.invite.notPossible");
            }

            $tableData[] = [
                "checkbox" => ilLegacyFormElementsUtil::formCheckbox(
                    false,
                    "userId[]",
                    (string) $chatMember->getUserId()
                ),
                "name" => $chatMember->getName(),
                "username" => $chatMember->getLogin(),
                "role" => $chatMember->getRoleText(),
                "status" => $this->uiRenderer->render($statusIcon) . " " . $this->plugin->txt("matrix.user.status.{$chatMember->getStatus()}"),
                "matrixUserId" => $chatMember->getMatrixUserId(),
                "invite" => $inviteText
            ];
        }
        return $tableData;
    }

    /**
     * @param ChatMember[] $chatMembers
     * @return ChatMember[]
     */
    private function filterData(array $chatMembers): array
    {
        $nameFilter = $this->getFilterValue($this->getFilterItemByPostVar("name"));
        $roleFilter = $this->getFilterValue($this->getFilterItemByPostVar("role"));


        $filteredChatMembers = [];
        foreach ($chatMembers as $chatMember) {
            if ($nameFilter === "" || str_contains($chatMember->getName(), $nameFilter)) {
                $nameMatch = true;
            } else {
                $nameMatch = false;
            }

            if ($roleFilter === "" || str_contains($chatMember->getRoleText(), $roleFilter)) {
                $roleMatch = true;
            } else {
                $roleMatch = false;
            }

            if ($nameMatch && $roleMatch) {
                $filteredChatMembers[] = $chatMember;
            }
        }

        return $filteredChatMembers;
    }

    /**
     * @throws Exception
     */
    public function initFilter(): void
    {
        $name = new ilTextInputGUI($this->lng->txt("name"), "name");

        $roleOptions = [
            "" => $this->lng->txt("all_roles")
        ];
        foreach ($this->rbacReview->getLocalRoles($this->refId) as $localRoleId) {
            $roleText = ilObjRole::_getTranslation(ilObjRole::_lookupTitle($localRoleId));
            $roleOptions[$roleText] = $roleText;
        }

        $role = new ilSelectInputGUI($this->lng->txt("role"), "role");
        $role->setOptions($roleOptions);
        $this->setFilterCommand(ChatController::getCommand(ChatController::CMD_APPLY_MEMBER_TABLE_FILTER));
        $this->setResetCommand(ChatController::getCommand(ChatController::CMD_RESET_MEMBER_TABLE_FILTER));
        $this->addFilterItem($name);
        $this->addFilterItem($role);

        $name->readFromSession();
        $role->readFromSession();

        parent::initFilter();
    }
}
