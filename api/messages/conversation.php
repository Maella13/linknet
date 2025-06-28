<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';
include_once '../models/Message.php';

$database = new Database();
$db = $database->getConnection();

$message = new Message($db);

$user1_id = isset($_GET['user1_id']) ? $_GET['user1_id'] : die();
$user2_id = isset($_GET['user2_id']) ? $_GET['user2_id'] : die();

$stmt = $message->readConversation($user1_id, $user2_id);
$num = $stmt->rowCount();

if($num > 0) {
    $messages_arr = array();
    $messages_arr["success"] = true;
    $messages_arr["message"] = "Conversation récupérée avec succès";
    $messages_arr["count"] = $num;
    $messages_arr["conversation_info"] = array(
        "user1_id" => (int)$user1_id,
        "user2_id" => (int)$user2_id,
        "conversation_id" => min($user1_id, $user2_id) . "_" . max($user1_id, $user2_id)
    );
    $messages_arr["data"] = array();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        extract($row);

        $message_item = array(
            "id" => (int)$id,
            "sender_id" => (int)$sender_id,
            "receiver_id" => (int)$receiver_id,
            "message" => $message,
            "is_read" => (bool)$is_read,
            "created_at" => $created_at,
            "sender_username" => $sender_username,
            "sender_picture" => $sender_picture,
            "message_type" => "text",
            "actions" => array(
                "reply_url" => "/api/messages/reply",
                "delete_url" => "/api/messages/" . $id . "/delete"
            )
        );

        array_push($messages_arr["data"], $message_item);
    }

    http_response_code(200);
    echo json_encode($messages_arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode(array(
        "success" => false,
        "message" => "Aucun message trouvé dans cette conversation.",
        "count" => 0,
        "conversation_info" => array(
            "user1_id" => (int)$user1_id,
            "user2_id" => (int)$user2_id,
            "conversation_id" => min($user1_id, $user2_id) . "_" . max($user1_id, $user2_id)
        ),
        "data" => []
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?> 