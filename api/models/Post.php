<?php
class Post {
    private $conn;
    private $table_name = "posts";

    public $id;
    public $user_id;
    public $content;
    public $media;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Récupérer tous les posts
    public function read() {
        $query = "SELECT p.*, u.username, u.profile_picture, 
                         (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
                         (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count
                  FROM " . $this->table_name . " p 
                  LEFT JOIN users u ON p.user_id = u.id 
                  ORDER BY p.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Récupérer les posts d'un utilisateur spécifique
    public function readByUser($user_id) {
        $query = "SELECT p.*, u.username, u.profile_picture,
                         (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
                         (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count
                  FROM " . $this->table_name . " p 
                  LEFT JOIN users u ON p.user_id = u.id 
                  WHERE p.user_id = ? 
                  ORDER BY p.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();
        return $stmt;
    }

    // Récupérer un post par ID
    public function readOne() {
        $query = "SELECT p.*, u.username, u.profile_picture,
                         (SELECT COUNT(*) FROM likes WHERE post_id = p.id) as likes_count,
                         (SELECT COUNT(*) FROM comments WHERE post_id = p.id) as comments_count
                  FROM " . $this->table_name . " p 
                  LEFT JOIN users u ON p.user_id = u.id 
                  WHERE p.id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) {
            $this->user_id = $row['user_id'];
            $this->content = $row['content'];
            $this->media = $row['media'];
            $this->created_at = $row['created_at'];
            return $row;
        }
        return false;
    }

    // Créer un nouveau post
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " SET user_id=:user_id, content=:content, media=:media";
        $stmt = $this->conn->prepare($query);

        // Nettoyer les données
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $this->content = htmlspecialchars(strip_tags($this->content));
        $this->media = !empty($this->media) ? htmlspecialchars(strip_tags($this->media)) : null;

        // Lier les paramètres
        $stmt->bindParam(":user_id", $this->user_id);
        $stmt->bindParam(":content", $this->content);
        $stmt->bindParam(":media", $this->media);

        if($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    // Mettre à jour un post
    public function update() {
        $query = "UPDATE " . $this->table_name . " SET content=:content, media=:media WHERE id=:id AND user_id=:user_id";
        $stmt = $this->conn->prepare($query);

        // Nettoyer les données
        $this->content = htmlspecialchars(strip_tags($this->content));
        $this->media = !empty($this->media) ? htmlspecialchars(strip_tags($this->media)) : null;
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));

        // Lier les paramètres
        $stmt->bindParam(":content", $this->content);
        $stmt->bindParam(":media", $this->media);
        $stmt->bindParam(":id", $this->id);
        $stmt->bindParam(":user_id", $this->user_id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Supprimer un post
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ? AND user_id = ?";
        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $this->user_id = htmlspecialchars(strip_tags($this->user_id));
        $stmt->bindParam(1, $this->id);
        $stmt->bindParam(2, $this->user_id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Vérifier si un utilisateur a liké un post
    public function isLikedByUser($post_id, $user_id) {
        $query = "SELECT id FROM likes WHERE post_id = ? AND user_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $post_id);
        $stmt->bindParam(2, $user_id);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            return true;
        }
        return false;
    }

    // Récupérer les hashtags d'un post
    public function getHashtags($post_id) {
        $query = "SELECT tag FROM hashtags WHERE post_id = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $post_id);
        $stmt->execute();
        return $stmt;
    }
}
?> 