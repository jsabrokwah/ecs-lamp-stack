<?php
require_once 'config.php';
require_once 'database.php';

$pdo = getDatabaseConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_task'])) {
        $task = trim($_POST['task']);
        if ($task !== '') {
            $stmt = $pdo->prepare("INSERT INTO todo (task) VALUES (:task)");
            $stmt->execute(['task' => $task]);
        }
    } elseif (isset($_POST['update_status'])) {
        $id = (int)$_POST['id'];
        $status = $_POST['status'] === 'Completed' ? 'Completed' : 'Pending';
        $stmt = $pdo->prepare("UPDATE todo SET status = :status WHERE id = :id");
        $stmt->execute(['status' => $status, 'id' => $id]);
    } elseif (isset($_POST['delete_task'])) {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM todo WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch todo items
$stmt = $pdo->query("SELECT * FROM todo ORDER BY created_at DESC");
$todo_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Simple Todo App</title>
<style>
    body {
        font-family: Arial, sans-serif;
        max-width: 600px;
        margin: 2rem auto;
        padding: 1rem;
        background: #f9f9f9;
        border-radius: 8px;
    }
    h1 {
        text-align: center;
        color: #333;
    }
    form {
        margin-bottom: 1rem;
        display: flex;
        gap: 0.5rem;
    }
    input[type="text"] {
        flex-grow: 1;
        padding: 0.5rem;
        font-size: 1rem;
    }
    button {
        padding: 0.5rem 1rem;
        font-size: 1rem;
        cursor: pointer;
        background-color: #007bff;
        border: none;
        color: white;
        border-radius: 4px;
    }
    button:hover {
        background-color: #0056b3;
    }
    ul {
        list-style: none;
        padding: 0;
    }
    li {
        background: white;
        margin-bottom: 0.5rem;
        padding: 0.75rem;
        border-radius: 4px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .task {
        flex-grow: 1;
    }
    .completed {
        text-decoration: line-through;
        color: gray;
    }
    .actions form {
        display: inline;
        margin: 0 0.25rem;
    }
    select {
        padding: 0.25rem;
        font-size: 1rem;
    }
</style>
</head>
<body>
<h1>Simple Todo App</h1>

<form method="POST" action="">
    <input type="text" name="task" placeholder="Enter new task" required />
    <button type="submit" name="add_task">Add Task</button>
</form>

<ul>
<?php foreach ($todo_items as $item): ?>
    <li>
        <span class="task <?php echo $item['status'] === 'Completed' ? 'completed' : ''; ?>">
            <?php echo htmlspecialchars($item['task']); ?>
        </span>
        <div class="actions">
            <form method="POST" action="" style="display:inline;">
                <input type="hidden" name="id" value="<?php echo $item['id']; ?>" />
                <select name="status" onchange="this.form.submit()">
                    <option value="Pending" <?php if ($item['status'] === 'Pending') echo 'selected'; ?>>Pending</option>
                    <option value="In Progress" <?php if ($item['status'] === 'In Progress') echo 'selected'; ?>>In Progress</option>
                    <option value="Completed" <?php if ($item['status'] === 'Completed') echo 'selected'; ?>>Completed</option>
                </select>
                <input type="hidden" name="update_status" value="1" />
            </form>
            <form method="POST" action="" style="display:inline;">
                <input type="hidden" name="id" value="<?php echo $item['id']; ?>" />
                <button type="submit" name="delete_task" onclick="return confirm('Delete this task?');">Delete</button>
            </form>
        </div>
    </li>
<?php endforeach; ?>
</ul>

</body>
</html>
