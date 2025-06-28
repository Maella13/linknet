<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';
include_once '../models/Comment.php';

$database = new Database();
$db = $database->getConnection();

$comment = new Comment($db);
$stmt = $comment->read();
$num = $stmt->rowCount();

if($num > 0) {
    $comments_arr = [
        "success" => true,
        "message" => "Commentaires récupérés avec succès",
        "count" => $num,
        "data" => []
    ];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $comments_arr["data"][] = $row;
    }
    http_response_code(200);
    echo json_encode($comments_arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode([
        "success" => false,
        "message" => "Aucun commentaire trouvé.",
        "count" => 0,
        "data" => []
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?> 