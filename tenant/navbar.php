
<?php
// This block needs to be at the top of any page that includes this navbar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once __DIR__ . "/../config.php";

$unread_messages = 0;
if (isset($_SESSION['tenant_id'])) {
    $tenant_id_for_nav = $_SESSION['tenant_id'];
    $unread_stmt = $conn->prepare("SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND sender_type = 'admin' AND is_read = 0");
    $unread_stmt->bind_param("i", $tenant_id_for_nav);
    $unread_stmt->execute();
    $unread_messages = $unread_stmt->get_result()->fetch_assoc()['count'];
}
?>
<body>
  
<nav class="navbar navbar-expand-lg" style="background-color: #05445E;">
  <div class="container-fluid">
    <!-- Branding -->
    <a class="navbar-brand text-white fw-bold" href="dashboard.php">
      <img class="logo" src="../assets/img/logo remove.png" alt="" srcset="">
    </a>

    <!-- Toggle for mobile -->
    <button class="navbar-toggler text-white" type="button" data-bs-toggle="collapse" data-bs-target="#tenantNavbar">
      <span class="navbar-toggler-icon"></span>
    </button>

    <!-- Navbar Links -->
    <div class="collapse navbar-collapse justify-content-end" id="tenantNavbar">
      <ul class="navbar-nav text-center">
        <li class="nav-item mx-3">
          <a class="nav-link text-white" href="dashboard.php">
            <i class="fas fa-home fa-lg d-block"></i>
            <small>Dashboard</small>
          </a>
        </li>
        <li class="nav-item mx-3">
          <a class="nav-link text-white" href="tenant-room.php">
            <i class="fas fa-door-open fa-lg d-block"></i>
            <small>My Room</small>
          </a>
        </li>
        <li class="nav-item mx-3">
          <a class="nav-link text-white" href="payment.php">
            <i class="fas fa-credit-card fa-lg d-block"></i>
            <small>Payment</small>
          </a>
        </li>
        <li class="nav-item mx-3">
          <a class="nav-link text-white" href="tenant-announcement.php">
            <i class="fas fa-bullhorn fa-lg d-block"></i>
            <small>Announcements</small>
          </a>
        </li>
        <li class="nav-item mx-3">
          <a class="nav-link text-white" href="tenant-logout.php">
            <i class="fas fa-sign-out-alt fa-lg d-block"></i>
            <small>Logout</small>
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>
</body>