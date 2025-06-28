<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';
include_once '../models/User.php';

$database = new Database();
$db = $database->getConnection();

$user = new User($db);
$stmt = $user->read();
$num = $stmt->rowCount();

if($num > 0) {
    $users_arr = array();
    $users_arr["success"] = true;
    $users_arr["message"] = "Utilisateurs récupérés avec succès";
    $users_arr["count"] = $num;
    $users_arr["data"] = array();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        extract($row);

        $user_item = array(
            "id" => (int)$id,
            "username" => $username,
            "email" => $email,
            "profile_picture" => $profile_picture,
            "bio" => $bio,
            "created_at" => $created_at,
            "profile_url" => "/profile/" . $id
        );

        array_push($users_arr["data"], $user_item);
    }

    http_response_code(200);
    echo json_encode($users_arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode(array(
        "success" => false,
        "message" => "Aucun utilisateur trouvé.",
        "count" => 0,
        "data" => []
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?> 