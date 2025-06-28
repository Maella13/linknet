# API LinkNet - Documentation

## Vue d'ensemble

Cette API REST permet de gérer une plateforme de messagerie sociale comme Facebook. Elle fournit des endpoints pour gérer les utilisateurs, les messages, les posts, et toutes les fonctionnalités sociales associées.

## Configuration de la base de données

L'API utilise la base de données `linknet` existante avec les tables suivantes :
- `users` - Utilisateurs de la plateforme
- `messages` - Messages privés entre utilisateurs
- `posts` - Publications des utilisateurs
- `likes` - J'aime sur les posts
- `comments` - Commentaires sur les posts
- `friends` - Relations d'amitié
- `notifications` - Notifications système
- `hashtags` - Tags des posts

## Endpoints disponibles

### 🔐 **Authentification et Utilisateurs**

#### 1. Inscription d'un utilisateur

**POST** `/api/users/register`

**Corps de la requête :**
```json
{
    "username": "nouveau_utilisateur",
    "email": "nouveau@email.com",
    "password": "motdepasse123",
    "profile_picture": "photo.jpg",
    "bio": "Ma bio personnelle"
}
```

**Réponse :**
```json
{
    "message": "Utilisateur créé avec succès."
}
```

#### 2. Connexion d'un utilisateur

**POST** `/api/users/login`

**Corps de la requête :**
```json
{
    "email": "utilisateur@email.com",
    "password": "motdepasse123"
}
```

**Réponse :**
```json
{
    "message": "Connexion réussie.",
    "user": {
        "id": 1,
        "username": "utilisateur",
        "email": "utilisateur@email.com",
        "profile_picture": "photo.jpg",
        "bio": "Ma bio",
        "created_at": "2024-01-15 10:30:00"
    }
}
```

#### 3. Récupérer tous les utilisateurs

**GET** `/api/users/`

**Réponse :**
```json
{
    "records": [
        {
            "id": 1,
            "username": "utilisateur1",
            "email": "user1@email.com",
            "profile_picture": "photo1.jpg",
            "bio": "Bio utilisateur 1",
            "created_at": "2024-01-15 10:30:00"
        }
    ]
}
```

#### 4. Récupérer un utilisateur par ID

**GET** `/api/users/{id}`

**Exemple :** `/api/users/1`

### 💬 **Messages**

#### 1. Envoyer un message

**POST** `/api/messages/send`

**Corps de la requête :**
```json
{
    "sender_id": 1,
    "receiver_id": 2,
    "message": "Salut ! Comment ça va ?"
}
```

**Réponse :**
```json
{
    "message": "Message envoyé avec succès."
}
```

#### 2. Récupérer une conversation

**GET** `/api/messages/conversation?user1_id=1&user2_id=2`

**Réponse :**
```json
{
    "records": [
        {
            "id": 1,
            "sender_id": 1,
            "receiver_id": 2,
            "message": "Salut !",
            "is_read": 0,
            "created_at": "2024-01-15 10:30:00",
            "sender_username": "utilisateur1",
            "sender_picture": "photo1.jpg"
        }
    ]
}
```

### 📝 **Posts**

#### 1. Créer un nouveau post

**POST** `/api/posts/create`

**Corps de la requête :**
```json
{
    "user_id": 1,
    "content": "Mon nouveau post avec #hashtag !",
    "media": "image.jpg",
    "hashtags": ["hashtag", "monpost"]
}
```

**Réponse :**
```json
{
    "message": "Post créé avec succès.",
    "post_id": 5
}
```

#### 2. Récupérer tous les posts

**GET** `/api/posts/`

**Réponse :**
```json
{
    "records": [
        {
            "id": 1,
            "user_id": 1,
            "username": "utilisateur1",
            "profile_picture": "photo1.jpg",
            "content": "Mon premier post !",
            "media": "image.jpg",
            "created_at": "2024-01-15 10:30:00",
            "likes_count": 5,
            "comments_count": 2,
            "hashtags": ["premier", "post"]
        }
    ]
}
```

## Codes de statut HTTP

- **200** : Succès
- **201** : Créé avec succès
- **400** : Requête incorrecte (données manquantes ou invalides)
- **401** : Non autorisé (mot de passe incorrect)
- **404** : Ressource non trouvée
- **503** : Erreur serveur

## Exemples d'utilisation avec JavaScript

### Inscription d'un utilisateur
```javascript
fetch('/api/users/register', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        username: 'nouveau_user',
        email: 'nouveau@email.com',
        password: 'motdepasse123',
        bio: 'Ma bio personnelle'
    })
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Erreur:', error));
```

### Connexion
```javascript
fetch('/api/users/login', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        email: 'utilisateur@email.com',
        password: 'motdepasse123'
    })
})
.then(response => response.json())
.then(data => {
    if(data.user) {
        localStorage.setItem('user', JSON.stringify(data.user));
    }
    console.log(data);
})
.catch(error => console.error('Erreur:', error));
```

### Envoyer un message
```javascript
fetch('/api/messages/send', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        sender_id: 1,
        receiver_id: 2,
        message: 'Salut ! Comment ça va ?'
    })
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Erreur:', error));
```

### Créer un post
```javascript
fetch('/api/posts/create', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
    },
    body: JSON.stringify({
        user_id: 1,
        content: 'Mon nouveau post avec #hashtag !',
        hashtags: ['hashtag', 'monpost']
    })
})
.then(response => response.json())
.then(data => console.log(data))
.catch(error => console.error('Erreur:', error));
```

## Exemples d'utilisation avec cURL

### Inscription
```bash
curl -X POST http://localhost/linknet/api/users/register \
  -H "Content-Type: application/json" \
  -d '{
    "username": "test_user",
    "email": "test@email.com",
    "password": "password123",
    "bio": "Test bio"
  }'
```

### Connexion
```bash
curl -X POST http://localhost/linknet/api/users/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "test@email.com",
    "password": "password123"
  }'
```

### Envoyer un message
```bash
curl -X POST http://localhost/linknet/api/messages/send \
  -H "Content-Type: application/json" \
  -d '{
    "sender_id": 1,
    "receiver_id": 2,
    "message": "Salut !"
  }'
```

### Créer un post
```bash
curl -X POST http://localhost/linknet/api/posts/create \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 1,
    "content": "Mon post de test #test",
    "hashtags": ["test", "post"]
  }'
```

## Structure du projet

```
api/
├── config/
│   └── database.php          # Configuration de la base de données
├── models/
│   ├── User.php              # Modèle Utilisateur
│   ├── Message.php           # Modèle Message
│   └── Post.php              # Modèle Post
├── users/
│   ├── read.php              # Récupérer tous les utilisateurs
│   ├── read_one.php          # Récupérer un utilisateur par ID
│   ├── register.php          # Inscription
│   └── login.php             # Connexion
├── messages/
│   ├── send.php              # Envoyer un message
│   └── conversation.php      # Récupérer une conversation
├── posts/
│   ├── read.php              # Récupérer tous les posts
│   └── create.php            # Créer un nouveau post
├── .htaccess                 # Configuration des routes
└── README.md                 # Cette documentation
```

## Fonctionnalités à venir

- Gestion des likes et commentaires
- Système d'amis et demandes d'amis
- Notifications en temps réel
- Upload de fichiers (images, vidéos)
- Recherche d'utilisateurs et de posts
- Système de hashtags avancé

## Sécurité

- Tous les mots de passe sont hashés avec bcrypt
- Les données sont nettoyées et échappées
- Utilisation de requêtes préparées PDO
- Validation des données côté serveur
- En-têtes CORS configurés

## Support

Pour toute question ou problème, veuillez consulter la documentation ou contacter l'équipe de développement. 