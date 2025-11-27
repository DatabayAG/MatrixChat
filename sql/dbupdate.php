<#1>
<?php
/** @var $ilDB \ilDBInterface */
if (!$ilDB->tableExists("mcc_course_settings")) {
    $ilDB->createTable("mcc_course_settings", [
        "course_id" => [
            "type" => ilDBConstants::T_INTEGER,
            "length" => 8,
            "notnull" => true,
        ],
        "chat_integration_enabled" => [
            "type" => ilDBConstants::T_INTEGER,
            "length" => 1,
            "notnull" => true,
            "default" => 0,
        ],
        "matrix_room_id" => [
            "type" => ilDBConstants::T_TEXT,
            "length" => 92,
            "notnull" => false,
        ]
    ]);
    $ilDB->addPrimaryKey("mcc_course_settings", ["course_id"]);
}
?>
<#2>
<?php
if (!$ilDB->tableExists("mcc_user_device")) {
    $ilDB->createTable("mcc_user_device", [
        "user_id" => [
            "type" => ilDBConstants::T_INTEGER,
            "length" => 8,
            "notnull" => true,
        ],
        "device_id" => [
            "type" => ilDBConstants::T_TEXT,
            "length" => 96,
            "notnull" => true,
        ],
    ]);
    $ilDB->addPrimaryKey("mcc_user_device", ["user_id"]);
}
?>
<#3>
<?php
if ($ilDB->tableExists("mcc_user_device")) {
    $ilDB->dropTable("mcc_user_device");
}
if (!$ilDB->tableExists("mcc_user_data")) {
    $ilDB->createTable("mcc_user_data", [
        "ilias_user_id" => [
            "type" => ilDBConstants::T_INTEGER,
            "length" => 8,
            "notnull" => true,
        ],
        "matrix_user_id" => [
            "type" => ilDBConstants::T_TEXT,
            "length" => 96,
            "notnull" => true,
        ],
        "matrix_device_id" => [
            "type" => ilDBConstants::T_TEXT,
            "length" => 96,
            "notnull" => true,
        ],
    ]);
    $ilDB->addPrimaryKey("mcc_user_data", ["ilias_user_id"]);
}
?>
<#4>
<?php
if ($ilDB->tableExists("mcc_user_data")) {
    $ilDB->dropTable("mcc_user_data");
}
?>
<#5>
<?php
if (!$ilDB->tableExists("mcc_usr_room_add_queue")) {
    $ilDB->createTable("mcc_usr_room_add_queue", [
        "user_id" => [
            "type" => ilDBConstants::T_INTEGER,
            "length" => 8,
            "notnull" => true,
        ],
        "ref_id" => [
            "type" => ilDBConstants::T_INTEGER,
            "length" => 8,
            "notnull" => true,
        ],
    ]);
    $ilDB->addPrimaryKey("mcc_usr_room_add_queue", ["user_id", "ref_id"]);
}
?>
<#6>
<?php
if (
    $ilDB->tableExists("mcc_course_settings")
    && $ilDB->tableColumnExists("mcc_course_settings", "chat_integration_enabled")
) {
    $ilDB->dropTableColumn(
        "mcc_course_settings",
        "chat_integration_enabled"
    );
}
?>
<#7>
<?php
if ($ilDB->tableExists("mcc_usr_room_add_queue")) {
    $ilDB->renameTable("mcc_usr_room_add_queue", "mcc_queued_invites");
}
?>
<#8>
<?php
if (!$ilDB->tableExists("mcc_usr_matrix_user_history")) {
    $ilDB->createTable("mcc_matrix_usr_history", [
        "id" => [
            "type" => ilDBConstants::T_INTEGER,
            "length" => 8,
            "notnull" => true,
        ],
        "user_id" => [
            "type" => ilDBConstants::T_INTEGER,
            "length" => 8,
            "notnull" => true,
        ],
        "matrix_user_id" => [
            "type" => ilDBConstants::T_TEXT,
            "length" => 255,
            "notnull" => true,
        ],
        "created_at" => [
            "type" => ilDBConstants::T_INTEGER,
            "length" => 8,
            "notnull" => true,
        ],
    ]);
    $ilDB->addPrimaryKey("mcc_matrix_usr_history", ["id"]);
    $ilDB->createSequence("mcc_matrix_usr_history");
}
?>
<#9>
<?php
if (!$ilDB->tableExists("mcc_mail_templates")) {
    $ilDB->createTable("mcc_mail_templates", [
        "language" => [
            "type" => ilDBConstants::T_TEXT,
            "length" => 64,
            "notnull" => true,
        ],
        "no_matrix_account_content" => [
            "type" => ilDBConstants::T_CLOB,
            "notnull" => true,
        ],
        "matrix_account_content" => [
            "type" => ilDBConstants::T_CLOB,
            "notnull" => true,
        ]
    ]);
    $ilDB->addPrimaryKey("mcc_mail_templates", ["language"]);
}
?>
<#10>
<?php
if ($ilDB->tableExists("mcc_mail_templates")) {
    $ilDB->dropTable("mcc_mail_templates");
    $ilDB->createTable("mcc_mail_templates", [
        "template_id" => [
            "type" => ilDBConstants::T_TEXT,
            "length" => 64,
            "notnull" => true,
        ],
        "language" => [
            "type" => ilDBConstants::T_TEXT,
            "length" => 8,
            "notnull" => true,
        ],
        "subject" => [
            "type" => ilDBConstants::T_CLOB,
            "notnull" => true,
        ],
        "content" => [
            "type" => ilDBConstants::T_CLOB,
            "notnull" => true,
        ]
    ]);
    $ilDB->addPrimaryKey("mcc_mail_templates", ["template_id", "language"]);
}
?>
<#11>
<?php
if ($ilDB->tableExists("mcc_mail_templates")) {
    if (!$ilDB->tableColumnExists("mcc_mail_templates", "active")) {
        $ilDB->addTableColumn(
            "mcc_mail_templates",
            "active",
            [
                "type" => ilDBConstants::T_INTEGER,
                "length" => 1,
                "notnull" => true,
                "default" => true
            ]
        );
    }
}
?>
