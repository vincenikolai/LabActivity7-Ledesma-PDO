<?php
require 'auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST'
    || !verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    header('Location: index.php');
    exit;
}

$commentId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$commentId) {
    header('Location: index.php');
    exit;
}

try {
    $statement = $pdo->prepare(
        'DELETE FROM comments
         WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute([
        ':id' => $commentId,
        ':user_id' => $_SESSION['user_id'],
    ]);
} catch (PDOException $e) {
    error_log('Comment deletion failed: ' . $e->getMessage());
}

header('Location: index.php');
exit;
