<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HouseMaster — Utility Bills</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="side.css">
</head>
<body>
  <!-- Sidebar -->
  <?php include("navbar.php"); ?>

  <!-- Main Content -->
  <div class="main">
    <h3 class="fw-bold text-dark">📑 Utility Bills</h3>
    <div class="card shadow-sm border-0 mt-3">
      <div class="card-body">
        <table class="table table-bordered align-middle">
          <thead>
            <tr>
              <th>Month</th>
              <th>Electricity</th>
              <th>Water</th>
              <th>Internet</th>
              <th>Total</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>January 2025</td>
              <td>₱15,000</td>
              <td>₱7,500</td>
              <td>₱5,000</td>
              <td><b>₱27,500</b></td>
            </tr>
            <tr>
              <td>February 2025</td>
              <td>₱14,200</td>
              <td>₱8,000</td>
              <td>₱5,200</td>
              <td><b>₱27,400</b></td>
            </tr>
            <tr>
              <td>March 2025</td>
              <td>₱16,000</td>
              <td>₱7,800</td>
              <td>₱5,100</td>
              <td><b>₱28,900</b></td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    <footer class="mt-4">HouseMaster © 2025 — Boarding House & Dormitory Management System</footer>
  </div>
</body>
</html>
