<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once '../config/database.php';
include_once '../models/Post.php';

$database = new Database();
$db = $database->getConnection();

$post = new Post($db);

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->user_id) && !empty($data->content)) {
    $post->user_id = $data->user_id;
    $post->content = $data->content;
    $post->media = !empty($data->media) ? $data->media : null;

    $post_id = $post->create();
    if($post_id) {
        // Traiter les hashtags si présents
        if(!empty($data->hashtags) && is_array($data->hashtags)) {
            foreach($data->hashtags as $tag) {
                $tag = trim($tag);
                if(!empty($tag)) {
                    $query = "INSERT INTO hashtags (tag, post_id) VALUES (?, ?)";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(1, $tag);
                    $stmt->bindParam(2, $post_id);
                    $stmt->execute();
                }
            }
        }

        http_response_code(201);
        echo json_encode(array("message" => "Post créé avec succès.", "post_id" => $post_id));
    } else {
        http_response_code(503);
        echo json_encode(array("message" => "Impossible de créer le post."));
    }
} else {
    http_response_code(400);
    echo json_encode(array("message" => "Impossible de créer le post. Données incomplètes."));
}
?> 