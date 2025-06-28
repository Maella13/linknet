<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");

include_once '../config/database.php';
include_once '../models/Post.php';

$database = new Database();
$db = $database->getConnection();

$post = new Post($db);
$stmt = $post->read();
$num = $stmt->rowCount();

if($num > 0) {
    $posts_arr = array();
    $posts_arr["success"] = true;
    $posts_arr["message"] = "Posts récupérés avec succès";
    $posts_arr["count"] = $num;
    $posts_arr["data"] = array();

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        extract($row);

        // Récupérer les hashtags pour ce post
        $hashtags_stmt = $post->getHashtags($id);
        $hashtags = array();
        while($hashtag_row = $hashtags_stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($hashtags, $hashtag_row['tag']);
        }

        $post_item = array(
            "id" => (int)$id,
            "user_id" => (int)$user_id,
            "username" => $username,
            "profile_picture" => $profile_picture,
            "content" => $content,
            "media" => $media,
            "created_at" => $created_at,
            "likes_count" => (int)$likes_count,
            "comments_count" => (int)$comments_count,
            "hashtags" => $hashtags,
            "actions" => array(
                "like_url" => "/api/posts/" . $id . "/like",
                "comment_url" => "/api/posts/" . $id . "/comments",
                "share_url" => "/api/posts/" . $id . "/share"
            )
        );

        array_push($posts_arr["data"], $post_item);
    }

    http_response_code(200);
    echo json_encode($posts_arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} else {
    http_response_code(404);
    echo json_encode(array(
        "success" => false,
        "message" => "Aucun post trouvé.",
        "count" => 0,
        "data" => []
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}
?> 