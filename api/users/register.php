<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../models/User.php';

$database = new Database();
$db = $database->getConnection();

$user = new User($db);

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->username) && !empty($data->email) && !empty($data->password)) {
    $user->username = $data->username;
    $user->email = $data->email;
    $user->password = $data->password;
    $user->profile_picture = !empty($data->profile_picture) ? $data->profile_picture : "default_profile.jpg";
    $user->bio = !empty($data->bio) ? $data->bio : null;

    // Vérifier si l'email existe déjà
    if($user->emailExists()) {
        http_response_code(400);
        echo json_encode(array("message" => "Cet email est déjà utilisé."));
        return;
    }

    // Vérifier si le nom d'utilisateur existe déjà
    if($user->usernameExists()) {
        http_response_code(400);
        echo json_encode(array("message" => "Ce nom d'utilisateur est déjà pris."));
        return;
    }

    if($user->create()) {
        http_response_code(201);
        echo json_encode(array("message" => "Utilisateur créé avec succès."));
    } else {
        http_response_code(503);
        echo json_encode(array("message" => "Impossible de créer l'utilisateur."));
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Impossible de créer l'utilisateur. Données incomplètes."));
}
?> 