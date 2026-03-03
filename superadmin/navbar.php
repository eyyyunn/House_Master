<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($conn)) {
    include_once __DIR__ . "/../config.php";
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
  <div class="logo-hold">
    <img class="logo" src="../assets/img/logo remove.png" alt="HouseMaster Logo">
  </div>
  <a href="index.php" class="<?= $current_page == 'index.php' ? 'active' : '' ?>"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
  <a href="payments.php" class="<?= $current_page == 'payments.php' ? 'active' : '' ?>"><i class="fas fa-history"></i> Payments History</a>
  <a href="plans.php" class="<?= $current_page == 'plans.php' ? 'active' : '' ?>"><i class="fas fa-clipboard-list"></i> Plans</a>
  <a href="settings.php" class="<?= $current_page == 'settings.php' ? 'active' : '' ?>"><i class="fas fa-cog"></i> Settings</a>
  <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>