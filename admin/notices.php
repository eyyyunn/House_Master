<?php
// This header file now handles session starting, config inclusion,
// and all account status enforcement (suspended/payment_due checks).
require_once 'header.php';

$admin_id = $_SESSION['admin_id'];
$boarding_code = $_SESSION['boarding_code'];
$message = "";

// ✅ Handle add notice
if (isset($_POST["add_notice"]) && !in_array($account_status, ['pending', 'restricted'])) {
    $title = trim($_POST['title']);
    $body = trim($_POST['body']);

    if (!empty($title) && !empty($body)) {
        $stmt = $conn->prepare("INSERT INTO notices (title, body, boarding_code, admin_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $title, $body, $boarding_code, $admin_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Announcement added successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $conn->error . "</div>";
        }
    } else {
        $message = "<div class='alert alert-warning'>Please fill in all fields.</div>";
    }
}

// ✅ Handle delete notice
if (isset($_POST["delete_notice"]) && !in_array($account_status, ['pending', 'restricted'])) {
    $id = intval($_POST["id"]);
    $stmt = $conn->prepare("DELETE FROM notices WHERE id = ? AND admin_id = ?");
    $stmt->bind_param("ii", $id, $admin_id);
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success'>Announcement deleted successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger'>Error deleting announcement.</div>";
    }
}

// ✅ Handle update notice
if (isset($_POST["update_notice"]) && !in_array($account_status, ['pending', 'restricted'])) {
    $id = intval($_POST["id"]);
    $title = trim($_POST['title']);
    $body = trim($_POST['body']);
    if (!empty($title) && !empty($body)) {
        $stmt = $conn->prepare("UPDATE notices SET title = ?, body = ? WHERE id = ? AND admin_id = ?");
        $stmt->bind_param("ssii", $title, $body, $id, $admin_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success'>Announcement updated successfully!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error updating announcement.</div>";
        }
    }
}

// ✅ Fetch all notices
$stmt = $conn->prepare("SELECT * FROM notices WHERE boarding_code = ? ORDER BY created_at DESC");
$stmt->bind_param("s", $boarding_code);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Announcements — HouseMaster</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="side.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #05445E;
            --secondary-color: #189AB4;
            --accent-color: #75E6DA;
            --light-bg: #f4f6f9;
        }
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .main {
            padding: 2rem;
        }
        .page-header {
            margin-bottom: 2rem;
        }
        .page-title {
            color: var(--primary-color);
            font-weight: 800;
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }
        .page-subtitle {
            color: #6c757d;
            font-size: 0.95rem;
        }
        .content-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.03);
            background: #fff;
            overflow: hidden;
            margin-bottom: 2rem;
        }
        .content-card-header {
            background: #fff;
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .card-title-text {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.1rem;
            margin: 0;
        }
        .notice-card {
            border: none;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            transition: all 0.3s ease;
            height: 100%;
            border-left: 5px solid var(--secondary-color);
        }
        .notice-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }
        .notice-date {
            font-size: 0.8rem;
            color: #8898aa;
            margin-bottom: 0.5rem;
            display: block;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .notice-title {
            font-weight: 700;
            color: #32325d;
            font-size: 1.15rem;
            margin-bottom: 0.75rem;
        }
        .notice-body {
            color: #525f7f;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        .btn-primary:hover {
            background-color: #03364a;
            border-color: #03364a;
        }
        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            transition: all 0.2s;
            margin-left: 5px;
            border: none;
        }
        .btn-icon:hover {
            transform: translateY(-2px);
        }
        .btn-edit { color: #05445E; background: #b6b7b846;}
        .btn-edit:hover { background: #05445E; color: white; }
        .btn-delete { color: #dc3545; background: rgba(220, 53, 69, 0.1); }
        .btn-delete:hover { background: #dc3545; color: #fff; }
        .form-control:focus {
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 0.25rem rgba(24, 154, 180, 0.25);
        }
    </style>
</head>
<body>
  <?php include("navbar.php"); ?>
  <div class="main">
    <div class="page-header">
        <h3 class="page-title">Announcements</h3>
        <p class="page-subtitle">Post updates and news for your tenants.</p>
    </div>

    <?= $message ?>

    <div class="row">
        <!-- Left Column: Add Form (if allowed) -->
        <?php if (!in_array($account_status, ['pending', 'restricted'])): ?>
        <div class="col-lg-4 mb-4">
            <div class="content-card h-100">
                <div class="content-card-header">
                    <h5 class="card-title-text"><i class="fas fa-plus-circle me-2"></i>Create New</h5>
                </div>
                <div class="p-4">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">Title</label>
                            <input type="text" name="title" class="form-control" placeholder="e.g. Maintenance Schedule" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-uppercase text-muted">Content</label>
                            <textarea name="body" rows="6" class="form-control" placeholder="Write your announcement here..." required></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="add_notice" class="btn btn-primary fw-bold">Post Announcement</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Right Column: List -->
        <div class="<?php echo !in_array($account_status, ['pending', 'restricted']) ? 'col-lg-8' : 'col-12'; ?>">
             <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-secondary m-0">Recent Posts</h5>
                <span class="badge bg-light text-dark border"><?php echo $result->num_rows; ?> Total</span>
            </div>
            
            <div class="row g-3">
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <div class="col-12">
                            <div class="notice-card p-4 position-relative">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="notice-date"><i class="far fa-calendar-alt me-1"></i> <?= date("F j, Y, g:i a", strtotime($row['created_at'])) ?></span>
                                        <h5 class="notice-title"><?= htmlspecialchars($row['title']) ?></h5>
                                    </div>
                                    <div class="d-flex">
                                        <?php if (!in_array($account_status, ['pending', 'restricted'])): ?>
                                        <button class="btn-icon btn-edit" title="Edit"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#editModal" 
                                                data-id="<?= $row['id'] ?>" 
                                                data-title="<?= htmlspecialchars($row['title'], ENT_QUOTES, 'UTF-8') ?>" 
                                                data-body='<?= json_encode($row['body']) ?>'>
                                            <i class="fas fa-pen"></i> 
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                            <input type="hidden" name="id" value="<?= $row['id'] ?>">
                                            <button type="submit" name="delete_notice" title="Delete" class="btn-icon btn-delete">
                                                <i class="fas fa-trash"></i> 
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="notice-body mt-2">
                                    <?= nl2br(htmlspecialchars($row['body'])) ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="text-center py-5 text-muted bg-white rounded-3 border border-dashed">
                            <i class="fas fa-bullhorn fa-3x mb-3 opacity-25"></i>
                            <p class="mb-0">No announcements posted yet.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="modal fade" id="editModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg" style="border-radius: 16px;">
        <form method="POST">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold text-primary">Edit Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id" id="edit-id">
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">Title</label>
                    <input type="text" name="title" id="edit-title" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold small text-uppercase text-muted">Body</label>
                    <textarea name="body" id="edit-body" rows="4" class="form-control" required></textarea>
                </div>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light text-muted" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="update_notice" class="btn btn-primary px-4">Save Changes</button>
            </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    
    var editModal = document.getElementById('editModal');
    editModal.addEventListener('show.bs.modal', function (event) {
      var button = event.relatedTarget;
      var id = button.getAttribute('data-id');
      var title = button.getAttribute('data-title');
      var body = JSON.parse(button.getAttribute('data-body')); // Safely parse the JSON string

      document.getElementById('edit-id').value = id;
      document.getElementById('edit-title').value = title;
      document.getElementById('edit-body').value = body;
    });
  </script>
</body>
</html>
