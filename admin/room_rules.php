<?php
session_start();
include __DIR__ . "/../config.php";

// Redirect if not logged in
if (!isset($_SESSION["admin_id"])) {
    http_response_code(403);
    die("Access denied.");
}

$admin_id = $_SESSION["admin_id"];
$room_id = isset($_GET['room_id']) ? (int)$_GET['room_id'] : 0;
$type = isset($_GET['type']) && in_array($_GET['type'], ['item', 'rule']) ? $_GET['type'] : 'rule';

// Verify the admin owns this room
$verify_stmt = $conn->prepare("SELECT id FROM rooms WHERE id = ? AND boarding_code = ?");
$verify_stmt->bind_param("is", $room_id, $_SESSION['boarding_code']);
$verify_stmt->execute();
if ($verify_stmt->get_result()->num_rows === 0) {
    http_response_code(403);
    die("You do not have permission to manage this room.");
}

// Handle adding a rule
if (isset($_POST['add_item_or_rule']) && $type === 'item') {
    $item_name = trim($_POST['item_name']);
    $quantity = (int)$_POST['quantity'];
    $condition = $_POST['condition'];
    if (!empty($item_name) && $quantity > 0) {
        $stmt = $conn->prepare("INSERT INTO room_items (room_id, admin_id, item_name, quantity, `condition`) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisis", $room_id, $admin_id, $item_name, $quantity, $condition);
        $stmt->execute();
    }
} elseif (isset($_POST['add_item_or_rule']) && $type === 'rule') {
    $rule_text = trim($_POST['rule_text']);
    if (!empty($rule_text)) {
        $stmt = $conn->prepare("INSERT INTO room_rules (room_id, admin_id, type, rule_text) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $room_id, $admin_id, $type, $rule_text);
        $stmt->execute();
    }
}

// Handle deleting a rule
if (isset($_POST['delete_item_or_rule'])) {
    $item_id = (int)$_POST['item_id'];
    if ($type === 'item') {
        $stmt = $conn->prepare("DELETE FROM room_items WHERE id = ? AND admin_id = ?");
        $stmt->bind_param("ii", $item_id, $admin_id);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("DELETE FROM room_rules WHERE id = ? AND admin_id = ? AND type = ?");
        $stmt->bind_param("iis", $item_id, $admin_id, $type);
        $stmt->execute();
    }
}

// Fetch existing items or rules for the room
if ($type === 'item') {
    $items_stmt = $conn->prepare("SELECT * FROM room_items WHERE room_id = ? ORDER BY created_at ASC");
    $items_stmt->bind_param("i", $room_id);
    $items_stmt->execute();
    $items = $items_stmt->get_result();
} else {
    $rules_stmt = $conn->prepare("SELECT * FROM room_rules WHERE room_id = ? AND type = ? ORDER BY created_at ASC");
    $rules_stmt->bind_param("is", $room_id, $type);
    $rules_stmt->execute();
    $rules = $rules_stmt->get_result();
}
?>

<?php if ($type === 'item'): ?>
    <!-- Add Item Form -->
    <form id="addItemForm" method="POST" class="mb-4">
        <div class="row g-2">
            <div class="col-md-6">
                <input type="text" name="item_name" class="form-control" placeholder="e.g., Aircon, Double-deck bed" required>
            </div>
            <div class="col-md-2">
                <input type="number" name="quantity" class="form-control" placeholder="Qty" value="1" min="1" required>
            </div>
            <div class="col-md-4">
                <select name="condition" class="form-select">
                    <option>New</option>
                    <option>Used</option>
                </select>
            </div>
        </div>
        <button type="submit" name="add_item_or_rule" class="btn btn-primary mt-2 w-100">Add Item</button>
    </form>
<?php else: ?>
    <!-- Add Rule Form -->
    <form id="addRuleForm" method="POST" class="mb-4">
        <div class="input-group">
            <input type="text" name="rule_text" class="form-control" placeholder="e.g., No visitors after 10 PM" required>
            <button type="submit" name="add_item_or_rule" class="btn btn-primary">Add Rule</button>
        </div>
    </form>
<?php endif; ?>

<!-- List of Items or Rules -->
<div class="list-group">
    <?php if ($type === 'item' && $items->num_rows > 0): ?>
        <?php while ($item = $items->fetch_assoc()): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <div>
                    <?php echo htmlspecialchars($item['item_name']); ?>
                    <span class="badge bg-secondary ms-2">x<?php echo $item['quantity']; ?></span>
                    <span class="badge bg-info"><?php echo $item['condition']; ?></span>
                </div>
                <button 
                    class="btn btn-sm btn-outline-danger delete-item-or-rule" 
                    data-id="<?php echo $item['id']; ?>"
                    title="Delete <?php echo ucfirst($type); ?>">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        <?php endwhile; ?>
    <?php elseif ($type === 'rule' && $rules->num_rows > 0): ?>
        <?php while ($rule = $rules->fetch_assoc()): ?>
            <div class="list-group-item d-flex justify-content-between align-items-center">
                <?php echo htmlspecialchars($rule['rule_text']); ?>
                <button 
                    class="btn btn-sm btn-outline-danger delete-item-or-rule" 
                    data-id="<?php echo $rule['id']; ?>"
                    title="Delete <?php echo ucfirst($type); ?>">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="list-group-item text-muted">No <?php echo $type; ?>s have been specified for this room yet.</div>
    <?php endif; ?>
</div>

<div class="modal-footer mt-3">
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>