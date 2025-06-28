<?php
class Message {
    private $conn;
    private $table_name = "messages";

    public $id;
    public $sender_id;
    public $receiver_id;
    public $group_id;
    public $message;
    public $is_read;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Récupérer tous les messages d'un utilisateur
    public function readByUser($user_id) {
        $query = "SELECT m.*, u.username as sender_username, u.profile_picture as sender_picture 
                  FROM " . $this->table_name . " m 
                  LEFT JOIN users u ON m.sender_id = u.id 
                  WHERE m.receiver_id = ? OR m.sender_id = ? 
                  ORDER BY m.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->bindParam(2, $user_id);
        $stmt->execute();
        return $stmt;
    }

    // Récupérer la conversation entre deux utilisateurs
    public function readConversation($user1_id, $user2_id) {
        $query = "SELECT m.*, u.username as sender_username, u.profile_picture as sender_picture 
                  FROM " . $this->table_name . " m 
                  LEFT JOIN users u ON m.sender_id = u.id 
                  WHERE (m.sender_id = ? AND m.receiver_id = ?) 
                     OR (m.sender_id = ? AND m.receiver_id = ?) 
                  ORDER BY m.created_at ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user1_id);
        $stmt->bindParam(2, $user2_id);
        $stmt->bindParam(3, $user2_id);
        $stmt->bindParam(4, $user1_id);
        $stmt->execute();
        return $stmt;
    }

    // Récupérer un message par ID
    public function readOne() {
        $query = "SELECT m.*, u.username as sender_username, u.profile_picture as sender_picture 
                  FROM " . $this->table_name . " m 
                  LEFT JOIN users u ON m.sender_id = u.id 
                  WHERE m.id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) {
            $this->sender_id = $row['sender_id'];
            $this->receiver_id = $row['receiver_id'];
            $this->group_id = $row['group_id'];
            $this->message = $row['message'];
            $this->is_read = $row['is_read'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    // Créer un nouveau message
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " SET sender_id=:sender_id, receiver_id=:receiver_id, message=:message";
        $stmt = $this->conn->prepare($query);

        // Nettoyer les données
        $this->sender_id = htmlspecialchars(strip_tags($this->sender_id));
        $this->receiver_id = htmlspecialchars(strip_tags($this->receiver_id));
        $this->message = htmlspecialchars(strip_tags($this->message));

        // Lier les paramètres
        $stmt->bindParam(":sender_id", $this->sender_id);
        $stmt->bindParam(":receiver_id", $this->receiver_id);
        $stmt->bindParam(":message", $this->message);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Marquer un message comme lu
    public function markAsRead() {
        $query = "UPDATE " . $this->table_name . " SET is_read = 1 WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(1, $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Marquer tous les messages d'un utilisateur comme lus
    public function markAllAsRead($receiver_id) {
        $query = "UPDATE " . $this->table_name . " SET is_read = 1 WHERE receiver_id = ? AND is_read = 0";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $receiver_id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Supprimer un message
    public function delete() {
        $query = "DELETE FROM " . $this->table_name . " WHERE id = ?";
        $stmt = $this->conn->prepare($query);
        $this->id = htmlspecialchars(strip_tags($this->id));
        $stmt->bindParam(1, $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Compter les messages non lus
    public function countUnread($user_id) {
        $query = "SELECT COUNT(*) as count FROM " . $this->table_name . " WHERE receiver_id = ? AND is_read = 0";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $user_id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['count'];
    }
}
?> 