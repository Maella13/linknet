<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';
include_once '../models/Notification.php';

$database = new Database();
$db = $database->getConnection();

$notification = new Notification($db);
$stmt = $notification->read();
$num = $stmt->rowCount();

if($num > 0) {
    $arr = [
        "success" => true,
        "message" => "Notifications récupérées avec succès",
        "count" => $num,
        "data" => []
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $arr["data"][] = $row;
    }
    http_response_code(200);
    echo json_encode($arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Aucune notification trouvée.",
        "count" => 0,
        "data" => []
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?> 