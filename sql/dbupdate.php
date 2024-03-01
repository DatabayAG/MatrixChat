<#1>
<?php
/** @var $ilDB \ilDBInterface */
if (!$ilDB->tableExists("mcc_course_settings")) {
    $ilDB->createTable("mcc_course_settings", [
        'course_id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
        ],
        "chat_integration_enabled" => [
            "type" => "integer",
            "length" => 1,
            'notnull' => true,
            "default" => 0,
        ],
        "matrix_room_id" => [
            "type" => "text",
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
        'user_id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
        ],
        "device_id" => [
            "type" => "text",
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
        'ilias_user_id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
        ],
        "matrix_user_id" => [
            "type" => "text",
            "length" => 96,
            "notnull" => true,
        ],
        "matrix_device_id" => [
            "type" => "text",
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
        'user_id' => [
            'type' => 'integer',
            'length' => 8,
            'notnull' => true,
        ],
        "ref_id" => [
            "type" => "integer",
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
            "type" => "integer",
            "length" => 8,
            "notnull" => true,
        ],
        "user_id" => [
            "type" => "integer",
            "length" => 8,
            "notnull" => true,
        ],
        "matrix_user_id" => [
            "type" => "text",
            "length" => 255,
            "notnull" => true,
        ],
        "created_at" => [
            "type" => "timestamp",
            "notnull" => true,
        ],
    ]);
    $ilDB->addPrimaryKey("mcc_matrix_usr_history", ["id"]);
    $ilDB->createSequence("mcc_matrix_usr_history");
}
?>
