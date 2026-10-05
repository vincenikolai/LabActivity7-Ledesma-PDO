<?php
require 'auth.php';

$error = '';
$title = '';
$content = '';
$commentError = '';
$commentPostId = null;
$commentContent = '';
$currentUserId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid form submission. Please try again.';
    } elseif (isset($_POST['comment_post_id'])) {
        $commentPostId = filter_input(INPUT_POST, 'comment_post_id', FILTER_VALIDATE_INT);
        $commentContent = trim($_POST['comment'] ?? '');

        if (!$commentPostId || $commentContent === '') {
            $commentError = 'Comment content cannot be empty.';
        } elseif (strlen($commentContent) > 10000) {
            $commentError = 'Comment content must not exceed 10,000 characters.';
        } else {
            try {
                $statement = $pdo->prepare(
                    'INSERT INTO comments (post_id, user_id, content) VALUES (:post_id, :user_id, :content)'
                );
                $statement->execute([
                    ':post_id' => $commentPostId,
                    ':user_id' => $currentUserId,
                    ':content' => $commentContent,
                ]);

                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                error_log('Comment creation failed: ' . $e->getMessage());
                $commentError = 'Unable to add your comment. Please try again.';
            }
        }
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if ($title === '' || $content === '') {
            $error = 'Post title and content cannot be empty.';
        } elseif (strlen($title) > 255) {
            $error = 'Post title must not exceed 255 characters.';
        } elseif (strlen($content) > 10000) {
            $error = 'Post content must not exceed 10,000 characters.';
        } else {
            try {
                $statement = $pdo->prepare(
                    'INSERT INTO posts (user_id, title, content) VALUES (:user_id, :title, :content)'
                );
                $statement->execute([
                    ':user_id' => $currentUserId,
                    ':title' => $title,
                    ':content' => $content,
                ]);

                header('Location: index.php');
                exit;
            } catch (PDOException $e) {
                error_log('Post creation failed: ' . $e->getMessage());
                $error = 'Unable to publish your post. Please try again.';
            }
        }
    }
}

$postsStatement = $pdo->prepare(
    'SELECT posts.*, users.email AS author_email
     FROM posts
     LEFT JOIN users ON posts.user_id = users.id
     ORDER BY posts.created_at DESC'
);
$postsStatement->execute();
$posts = $postsStatement;

$commentsStatement = $pdo->prepare(
    'SELECT comments.*, users.email AS commenter_email
     FROM comments
     LEFT JOIN users ON comments.user_id = users.id
     WHERE comments.post_id = :post_id
     ORDER BY comments.created_at ASC'
);

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
    <title>Blog Feed</title>
</head>
<body>
    <header>
        <h1>Blog Feed</h1>
        <form method="post" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
            <button type="submit">Log out</button>
        </form>
    </header>

    <main>
        <section>
            <h2>Write a post</h2>

            <?php if ($error !== ''): ?>
                <p style="color: red;"><?= escapeHtml($error) ?></p>
            <?php endif; ?>

            <form method="post" action="index.php">
                <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                <div>
                    <label for="title">Title</label><br>
                    <input type="text" id="title" name="title" value="<?= escapeHtml($title) ?>" maxlength="255" required>
                </div>
                <div>
                    <label for="content">Post Content</label><br>
                    <textarea id="content" name="content" rows="6" maxlength="10000" required><?= escapeHtml($content) ?></textarea>
                </div>
                <button type="submit">Publish</button>
            </form>
        </section>

        <section>
            <h2>Recent posts</h2>

            <?php while ($post = $posts->fetch()): ?>
                <article style="border: 1px solid #ccc; padding: 15px; margin-bottom: 20px;">
                    <h3><?= escapeHtml((string) $post['title']) ?></h3>
                    <p>
                        By <?= escapeHtml((string) ($post['author_email'] ?? 'Unknown author')) ?>
                        on <?= escapeHtml((string) $post['created_at']) ?>
                        <?php if ((int) $post['is_edited'] === 1): ?>
                            <strong>(Edited)</strong>
                        <?php endif; ?>
                    </p>
                    <p><?= nl2br(escapeHtml((string) $post['content'])) ?></p>

                    <?php if ((int) $post['user_id'] === (int) $currentUserId): ?>
                        <a href="edit-post.php?id=<?= urlencode((string) $post['id']) ?>">Edit</a> | 
                        <form method="post" action="delete-post.php" style="display:inline;">
                            <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                            <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
                            <button type="submit" onclick="return confirm('Are you sure you want to delete this post?');">Delete post</button>
                        </form>
                    <?php endif; ?>

                    <section style="margin-top: 15px; border-top: 1px dashed #ddd; padding-top: 10px;">
                        <h4>Comments</h4>
                        <?php
                        $commentsStatement->execute([':post_id' => $post['id']]);
                        while ($comment = $commentsStatement->fetch()):
                        ?>
                            <div style="margin-bottom: 10px; background: #f9f9f9; padding: 8px;">
                                <p style="font-size: 0.9em; color: #555;">
                                    By <?= escapeHtml((string) ($comment['commenter_email'] ?? 'Unknown commenter')) ?>
                                    on <?= escapeHtml((string) $comment['created_at']) ?>
                                    <?php if ((int) $comment['is_edited'] === 1): ?>
                                        <strong>(Edited)</strong>
                                    <?php endif; ?>
                                </p>
                                <p><?= nl2br(escapeHtml((string) $comment['content'])) ?></p>

                                <?php if ((int) $comment['user_id'] === (int) $currentUserId): ?>
                                    <a href="edit-comment.php?id=<?= urlencode((string) $comment['id']) ?>">Edit</a> | 
                                    <form method="post" action="delete-comment.php" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                                        <input type="hidden" name="id" value="<?= (int) $comment['id'] ?>">
                                        <button type="submit" onclick="return confirm('Are you sure you want to delete this comment?');">Delete comment</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>

                        <?php if ($commentPostId === (int) $post['id'] && $commentError !== ''): ?>
                            <p style="color: red;"><?= escapeHtml($commentError) ?></p>
                        <?php endif; ?>

                        <form method="post" action="index.php" style="margin-top: 10px;">
                            <input type="hidden" name="csrf_token" value="<?= escapeHtml(csrfToken()) ?>">
                            <input type="hidden" name="comment_post_id" value="<?= (int) $post['id'] ?>">
                            <label for="comment-<?= (int) $post['id'] ?>">Add a comment</label><br>
                            <textarea id="comment-<?= (int) $post['id'] ?>" name="comment" rows="3" maxlength="10000" required><?php
                                if ($commentPostId === (int) $post['id']) {
                                    echo escapeHtml($commentContent);
                                }
                            ?></textarea><br>
                            <button type="submit">Comment</button>
                        </form>
                    </section>
                </article>
            <?php endwhile; ?>
        </section>
    </main>
</body>
</html>