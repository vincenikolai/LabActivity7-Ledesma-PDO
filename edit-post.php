<?php
require 'auth.php';

$postId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$currentUserId = $_SESSION['user_id'];

if (!$postId) {
    header('Location: index.php');
    exit;
}

$statement = $pdo->prepare(
    'SELECT id, title, content
     FROM posts
     WHERE id = :id AND user_id = :user_id'
);
$statement->execute([
    ':id' => $postId,
    ':user_id' => $currentUserId,
]);
$post = $statement->fetch();

if (!$post) {
    header('Location: index.php');
    exit;
}

$error = '';
$title = $post['title'];
$content = $post['content'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid form submission. Please try again.';
    } elseif ($title === '' || $content === '') {
        $error = 'Post title and content cannot be empty.';
    } elseif (strlen($title) > 255) {
        $error = 'Post title must not exceed 255 characters.';
    } elseif (strlen($content) > 10000) {
        $error = 'Post content must not exceed 10,000 characters.';
    } else {
        try {
            $update = $pdo->prepare(
                'UPDATE posts
                 SET title = :title, content = :content, is_edited = 1
                 WHERE id = :id AND user_id = :user_id'
            );
            $update->execute([
                ':title' => $title,
                ':content' => $content,
                ':id' => $postId,
                ':user_id' => $currentUserId,
            ]);

            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            error_log('Post update failed: ' . $e->getMessage());
            $error = 'Unable to update the post. Please try again.';
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
    <title>Edit post</title>
</head>
<body>
    <h1>Edit post</h1>

    <?php if ($error !== ''): ?>
        <p><?= escapeHtml($error) ?></p>
    <?php endif; ?>

    <form method="post" action="edit-post.php?id=<?= (int) $postId ?>">
        <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?= escapeHtml($title) ?>"
               maxlength="255" required>
        <label for="content">Post</label>
        <textarea id="content" name="content" rows="6" maxlength="10000" required><?= escapeHtml($content) ?></textarea>
        <button type="submit">Save changes</button>
    </form>

    <p><a href="index.php">Cancel</a></p>
</body>
</html>