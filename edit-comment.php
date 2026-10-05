<?php
require 'auth.php';

$commentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$currentUserId = $_SESSION['user_id'];

if (!$commentId) {
    header('Location: index.php');
    exit;
}

$statement = $pdo->prepare(
    'SELECT id, content
     FROM comments
     WHERE id = :id AND user_id = :user_id'
);
$statement->execute([
    ':id' => $commentId,
    ':user_id' => $currentUserId,
]);
$comment = $statement->fetch();

if (!$comment) {
    header('Location: index.php');
    exit;
}

$error = '';
$content = $comment['content'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['comment'] ?? '');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid form submission. Please try again.';
    } elseif ($content === '') {
        $error = 'Comment content cannot be empty.';
    } elseif (strlen($content) > 10000) {
        $error = 'Comment content must not exceed 10,000 characters.';
    } else {
        try {
            $update = $pdo->prepare(
                'UPDATE comments
                 SET content = :content, is_edited = 1
                 WHERE id = :id AND user_id = :user_id'
            );
            $update->execute([
                ':content' => $content,
                ':id' => $commentId,
                ':user_id' => $currentUserId,
            ]);

            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            error_log('Comment update failed: ' . $e->getMessage());
            $error = 'Unable to update the comment. Please try again.';
        }
    }
}

function escapeHtml(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit comment</title>
</head>
<body>
    <h1>Edit comment</h1>

    <?php if ($error !== ''): ?>
        <p><?= escapeHtml($error) ?></p>
    <?php endif; ?>

    <form method="post" action="edit-comment.php?id=<?= (int) $commentId ?>">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
        <label for="comment">Comment</label>
        <textarea id="comment" name="comment" rows="4" maxlength="10000" required><?= escapeHtml($content) ?></textarea>
        <button type="submit">Save changes</button>
    </form>

    <p><a href="index.php">Cancel</a></p>
</body>
</html>