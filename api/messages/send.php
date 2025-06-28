<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../models/Message.php';

$database = new Database();
$db = $database->getConnection();

$message = new Message($db);

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->sender_id) && !empty($data->receiver_id) && !empty($data->message)) {
    $message->sender_id = $data->sender_id;
    $message->receiver_id = $data->receiver_id;
    $message->message = $data->message;

    if($message->create()) {
        http_response_code(201);
        echo json_encode(array("message" => "Message envoyé avec succès."));
    } else {
        http_response_code(503);
        echo json_encode(array("message" => "Impossible d'envoyer le message."));
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Impossible d'envoyer le message. Données incomplètes."));
}
?> 