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

if(!empty($data->email) && !empty($data->password)) {
    $user->email = $data->email;
    $password = $data->password;

    if($user->readByEmail()) {
        if(password_verify($password, $user->password)) {
            $user_arr = array(
                "id" => $user->id,
                "username" => $user->username,
                "email" => $user->email,
                "profile_picture" => $user->profile_picture,
                "bio" => $user->bio,
                "created_at" => $user->created_at
            );

            http_response_code(200);
            echo json_encode(array(
                "message" => "Connexion réussie.",
                "user" => $user_arr
            ));
        } else {
            http_response_code(401);
            echo json_encode(array("message" => "Mot de passe incorrect."));
        }
    } else {
        http_response_code(404);
        echo json_encode(array("message" => "Utilisateur non trouvé."));
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Email et mot de passe requis."));
}
?> 