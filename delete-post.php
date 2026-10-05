<?php
require 'auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    header('Location: index.php');
    exit;
}

$postId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$postId) {
    header('Location: index.php');
    exit;
}

try {
    $statement = $pdo->prepare(
        'DELETE FROM posts
         WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute([
        ':id' => $postId,
        ':user_id' => $_SESSION['user_id'],
    ]);
} catch (PDOException $e) {
    error_log('Post deletion failed: ' . $e->getMessage());
}

header('Location: index.php');
exit;
