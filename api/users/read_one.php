<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';
include_once '../models/User.php';

$database = new Database();
$db = $database->getConnection();

$user = new User($db);

$user->id = isset($_GET['id']) ? $_GET['id'] : die();

if($user->readOne()) {
    $user_arr = array(
        "id" => $user->id,
        "username" => $user->username,
        "email" => $user->email,
        "profile_picture" => $user->profile_picture,
        "bio" => $user->bio,
        "created_at" => $user->created_at
    );

    http_response_code(200);
    echo json_encode($user_arr);
} else {
    http_response_code(404);
    echo json_encode(array("message" => "Utilisateur non trouvé."));
}
?> 