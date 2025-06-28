<?php
class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $username;
    public $email;
    public $password;
    public $profile_picture;
    public $bio;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    // Récupérer tous les utilisateurs
    public function read() {
        $query = "SELECT id, username, email, profile_picture, bio, created_at FROM " . $this->table_name . " ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt;
    }

    // Récupérer un utilisateur par ID
    public function readOne() {
        $query = "SELECT id, username, email, profile_picture, bio, created_at FROM " . $this->table_name . " WHERE id = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->id);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) {
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->profile_picture = $row['profile_picture'];
            $this->bio = $row['bio'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    // Récupérer un utilisateur par email (pour connexion)
    public function readByEmail() {
        $query = "SELECT id, username, email, password, profile_picture, bio, created_at FROM " . $this->table_name . " WHERE email = ? LIMIT 0,1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->email);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if($row) {
            $this->id = $row['id'];
            $this->username = $row['username'];
            $this->password = $row['password'];
            $this->profile_picture = $row['profile_picture'];
            $this->bio = $row['bio'];
            $this->created_at = $row['created_at'];
            return true;
        }
        return false;
    }

    // Créer un nouvel utilisateur
    public function create() {
        $query = "INSERT INTO " . $this->table_name . " SET username=:username, email=:email, password=:password, profile_picture=:profile_picture, bio=:bio";
        $stmt = $this->conn->prepare($query);

        // Nettoyer les données
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        $this->profile_picture = !empty($this->profile_picture) ? htmlspecialchars(strip_tags($this->profile_picture)) : "default_profile.jpg";
        $this->bio = !empty($this->bio) ? htmlspecialchars(strip_tags($this->bio)) : null;

        // Lier les paramètres
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":profile_picture", $this->profile_picture);
        $stmt->bindParam(":bio", $this->bio);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Mettre à jour un utilisateur
    public function update() {
        $query = "UPDATE " . $this->table_name . " SET username=:username, email=:email, profile_picture=:profile_picture, bio=:bio WHERE id=:id";
        $stmt = $this->conn->prepare($query);

        // Nettoyer les données
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->profile_picture = !empty($this->profile_picture) ? htmlspecialchars(strip_tags($this->profile_picture)) : "default_profile.jpg";
        $this->bio = !empty($this->bio) ? htmlspecialchars(strip_tags($this->bio)) : null;
        $this->id = htmlspecialchars(strip_tags($this->id));

        // Lier les paramètres
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":profile_picture", $this->profile_picture);
        $stmt->bindParam(":bio", $this->bio);
        $stmt->bindParam(":id", $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Mettre à jour le mot de passe
    public function updatePassword() {
        $query = "UPDATE " . $this->table_name . " SET password=:password WHERE id=:id";
        $stmt = $this->conn->prepare($query);

        $this->password = password_hash($this->password, PASSWORD_DEFAULT);
        $this->id = htmlspecialchars(strip_tags($this->id));

        $stmt->bindParam(":password", $this->password);
        $stmt->bindParam(":id", $this->id);

        if($stmt->execute()) {
            return true;
        }
        return false;
    }

    // Supprimer un utilisateur
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

    // Vérifier si l'email existe déjà
    public function emailExists() {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->email);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            return true;
        }
        return false;
    }

    // Vérifier si le nom d'utilisateur existe déjà
    public function usernameExists() {
        $query = "SELECT id FROM " . $this->table_name . " WHERE username = ?";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(1, $this->username);
        $stmt->execute();

        if($stmt->rowCount() > 0) {
            return true;
        }
        return false;
    }
}
?> 