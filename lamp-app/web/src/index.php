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
        $allowed_statuses = ['Pending', 'In Progress', 'Completed'];
        $status = in_array($_POST['status'], $allowed_statuses) ? $_POST['status'] : 'Pending';
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
<title>TaskFlow - Modern Todo App</title>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
    :root {
        --primary: #667eea;
        --primary-dark: #5a67d8;
        --secondary: #764ba2;
        --success: #48bb78;
        --warning: #ed8936;
        --danger: #f56565;
        --light: #f7fafc;
        --dark: #2d3748;
        --text: #2d3748;
        --text-light: #718096;
        --border: #e2e8f0;
        --shadow: 0 10px 25px rgba(0,0,0,0.1);
        --shadow-lg: 0 20px 40px rgba(0,0,0,0.15);
    }

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding: 2rem 1rem;
        color: var(--text);
    }

    .container {
        max-width: 800px;
        margin: 0 auto;
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 20px;
        box-shadow: var(--shadow-lg);
        overflow: hidden;
    }

    .header {
        background: linear-gradient(135deg, var(--primary), var(--secondary));
        color: white;
        padding: 3rem 2rem;
        text-align: center;
        position: relative;
    }

    .header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="%23ffffff" opacity="0.1"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>') repeat;
        opacity: 0.3;
    }

    .header h1 {
        font-size: 2.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
        position: relative;
        z-index: 1;
    }

    .header p {
        font-size: 1.1rem;
        opacity: 0.9;
        position: relative;
        z-index: 1;
    }

    .content {
        padding: 2rem;
    }

    .add-task-form {
        background: var(--light);
        padding: 1.5rem;
        border-radius: 15px;
        margin-bottom: 2rem;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
    }

    .form-group {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .form-input {
        flex: 1;
        padding: 1rem 1.5rem;
        border: 2px solid var(--border);
        border-radius: 12px;
        font-size: 1rem;
        transition: all 0.3s ease;
        background: white;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        transform: translateY(-1px);
    }

    .btn {
        padding: 1rem 2rem;
        border: none;
        border-radius: 12px;
        font-size: 1rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
    }

    .btn-primary {
        background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        color: white;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    }

    .btn-danger {
        background: var(--danger);
        color: white;
        padding: 0.5rem 1rem;
        font-size: 0.875rem;
    }

    .btn-danger:hover {
        background: #e53e3e;
        transform: translateY(-1px);
    }

    .tasks-container {
        display: grid;
        gap: 1rem;
    }

    .task-item {
        background: white;
        border-radius: 15px;
        padding: 1.5rem;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        border: 1px solid var(--border);
        transition: all 0.3s ease;
        animation: slideIn 0.3s ease;
    }

    .task-item:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
    }

    .task-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .task-text {
        flex: 1;
        font-size: 1.1rem;
        line-height: 1.5;
    }

    .task-completed {
        text-decoration: line-through;
        color: var(--text-light);
        opacity: 0.7;
    }

    .task-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .status-select {
        padding: 0.5rem 1rem;
        border: 2px solid var(--border);
        border-radius: 8px;
        font-size: 0.875rem;
        background: white;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .status-select:focus {
        outline: none;
        border-color: var(--primary);
    }

    .status-pending { border-left: 4px solid var(--warning); }
    .status-progress { border-left: 4px solid var(--primary); }
    .status-completed { border-left: 4px solid var(--success); }

    .empty-state {
        text-align: center;
        padding: 3rem 2rem;
        color: var(--text-light);
    }

    .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
        opacity: 0.3;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .footer {
        background: linear-gradient(135deg, var(--dark), var(--text));
        color: white;
        padding: 2rem;
        margin-top: 2rem;
    }

    .footer-content {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 2rem;
        margin-bottom: 1.5rem;
    }

    .footer-section h4 {
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
        color: var(--primary);
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .footer-section p {
        font-size: 0.9rem;
        opacity: 0.8;
        margin-bottom: 0.25rem;
    }

    .footer-bottom {
        text-align: center;
        padding-top: 1.5rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        font-size: 0.9rem;
        opacity: 0.7;
    }

    .footer-bottom a {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
        transition: color 0.3s ease;
    }

    .footer-bottom a:hover {
        color: white;
    }

    @media (max-width: 768px) {
        .container {
            margin: 1rem;
            border-radius: 15px;
        }
        
        .header {
            padding: 2rem 1rem;
        }
        
        .header h1 {
            font-size: 2rem;
        }
        
        .content {
            padding: 1rem;
        }
        
        .form-group {
            flex-direction: column;
        }
        
        .task-content {
            flex-direction: column;
            align-items: stretch;
        }
        
        .task-actions {
            justify-content: space-between;
        }

        .footer {
            padding: 1.5rem 1rem;
        }

        .footer-content {
            grid-template-columns: 1fr;
            gap: 1rem;
        }
    }
</style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-tasks"></i> TaskFlow</h1>
            <p>Organize your life, one task at a time</p>
        </div>
        
        <div class="content">
            <div class="add-task-form">
                <form method="POST" action="">
                    <div class="form-group">
                        <input type="text" name="task" class="form-input" placeholder="What needs to be done?" required />
                        <button type="submit" name="add_task" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Add Task
                        </button>
                    </div>
                </form>
            </div>

            <div class="tasks-container">
                <?php if (empty($todo_items)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No tasks yet</h3>
                        <p>Add your first task above to get started!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($todo_items as $item): ?>
                        <div class="task-item status-<?php echo strtolower(str_replace(' ', '', $item['status'])); ?>">
                            <div class="task-content">
                                <div class="task-text <?php echo $item['status'] === 'Completed' ? 'task-completed' : ''; ?>">
                                    <?php if ($item['status'] === 'Completed'): ?>
                                        <i class="fas fa-check-circle" style="color: var(--success); margin-right: 0.5rem;"></i>
                                    <?php elseif ($item['status'] === 'In Progress'): ?>
                                        <i class="fas fa-clock" style="color: var(--primary); margin-right: 0.5rem;"></i>
                                    <?php else: ?>
                                        <i class="far fa-circle" style="color: var(--warning); margin-right: 0.5rem;"></i>
                                    <?php endif; ?>
                                    <?php echo htmlspecialchars($item['task']); ?>
                                </div>
                                <div class="task-actions">
                                    <form method="POST" action="" style="display:inline;">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>" />
                                        <select name="status" class="status-select" onchange="this.form.submit()">
                                            <option value="Pending" <?php if ($item['status'] === 'Pending') echo 'selected'; ?>>📋 Pending</option>
                                            <option value="In Progress" <?php if ($item['status'] === 'In Progress') echo 'selected'; ?>>⏳ In Progress</option>
                                            <option value="Completed" <?php if ($item['status'] === 'Completed') echo 'selected'; ?>>✅ Completed</option>
                                        </select>
                                        <input type="hidden" name="update_status" value="1" />
                                    </form>
                                    <form method="POST" action="" style="display:inline;">
                                        <input type="hidden" name="id" value="<?php echo $item['id']; ?>" />
                                        <button type="submit" name="delete_task" class="btn btn-danger" onclick="return confirm('Are you sure you want to delete this task?');">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="footer">
            <div class="footer-content">
                <div class="footer-section">
                    <h4><i class="fas fa-tasks"></i> TaskFlow</h4>
                    <p>Organize your life, one task at a time</p>
                    <p>Modern productivity made simple</p>
                </div>
                <div class="footer-section">
                    <h4><i class="fas fa-chart-line"></i> Stats</h4>
                    <p><?php echo count($todo_items); ?> total tasks</p>
                    <p><?php echo count(array_filter($todo_items, fn($item) => $item['status'] === 'Completed')); ?> completed</p>
                    <p><?php echo count(array_filter($todo_items, fn($item) => $item['status'] === 'In Progress')); ?> in progress</p>
                </div>
                <div class="footer-section">
                    <h4><i class="fas fa-code"></i> Tech Stack</h4>
                    <p>Built with PHP & MySQL</p>
                    <p>Modern LAMP Architecture</p>
                    <p>Docker Containerized</p>
                    <p>Running on AWS ECS</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <?php echo date('Y'); ?> TaskFlow | Proudly built in Amalitech, Accra Ghana by <a href="https://github.com/jsabrokwah" target="_blank">J. S. Abrokwah</a> | Made with <i class="fas fa-heart" style="color: var(--danger);"></i> for productivity</p>
            </div>
        </div>
    </div>
</body>
</html>
