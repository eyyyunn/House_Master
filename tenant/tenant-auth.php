<?php
session_start();
include __DIR__ . "/../config.php"; // go up one level to load config

$login_error = '';
$signup_error = '';
$signup_success = '';

// ✅ Handle Login
if (isset($_POST["login"])) {
    $email = $_POST["login_email"];
    $password = $_POST["login_password"];

    // Check for a success message from registration
    if (isset($_SESSION['signup_success'])) {
        $signup_success = $_SESSION['signup_success'];
        unset($_SESSION['signup_success']);
    }

    $stmt = $conn->prepare("SELECT * FROM tenants WHERE email=? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $tenant = $result->fetch_assoc();
        if (password_verify($password, $tenant["password"])) {
            // ✅ Check if the account is active
            if ($tenant['status'] === 'inactive') {
                $login_error = "Your account has been deactivated. Please contact your landlord.";
            } elseif ($tenant['status'] === 'pending') {
                $login_error = "Your account is pending approval from the administrator.";
            } else {
                $_SESSION["tenant_id"] = $tenant["id"];   // store tenant ID
                $_SESSION["tenant_name"] = $tenant["fullname"]; // optional: store name
                header("Location: dashboard.php");
                exit();
            }
        } else {
            $login_error = "Invalid email or password!";
        }
    } else {
        $login_error = "No account found with that email!";
    }
}

// ✅ Handle Signup
if (isset($_POST["signup"])) {
    $fullname = $_POST["fullname"];
    $email = $_POST["email"];
    $password = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $age = $_POST["age"];
    $phone = $_POST["phone"];
    $room_id = $_POST["room_id"]; // Changed from room_code to room_id
    $emergency_contact_person = $_POST['emergency_contact_person'];
    $emergency_contact_phone = $_POST['emergency_contact_phone'];

    // 1. Check if email already exists
    $email_check_stmt = $conn->prepare("SELECT id FROM tenants WHERE email = ?");
    $email_check_stmt->bind_param("s", $email);
    $email_check_stmt->execute();
    $email_result = $email_check_stmt->get_result();
    
    if ($email_result->num_rows > 0) {
        $signup_error = "This email is already registered. Please use a different email or log in.";
    } else {
        // 2. Check if room ID exists and get room details
        $room_check_stmt = $conn->prepare("SELECT r.capacity, r.boarding_code, a.id as admin_id, COUNT(tr.id) as current_tenants FROM rooms r JOIN admins a ON r.boarding_code = a.boarding_code LEFT JOIN tenant_rooms tr ON r.id = tr.room_id WHERE r.id = ? GROUP BY r.id");
        $room_check_stmt->bind_param("i", $room_id);
        $room_check_stmt->execute();
        $room_result = $room_check_stmt->get_result();

        if ($room_result->num_rows === 0) {
            $signup_error = "Invalid Room Signup Code. Please ask your landlord for the correct code.";
        } else {
            $room_data = $room_result->fetch_assoc();
            $admin_id = $room_data['admin_id'];
            $boarding_code = $room_data['boarding_code'];

            if ($room_data['current_tenants'] >= $room_data['capacity']) {
                $signup_error = "Sorry, the room for this code is already full.";
            } else {
                // 3. Save tenant and assign to room
                $stmt = $conn->prepare("INSERT INTO tenants (fullname, email, password, age, phone, emergency_contact_person, emergency_contact_phone, boarding_code, admin_id, requested_room_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->bind_param("sssisssisi", $fullname, $email, $password, $age, $phone, $emergency_contact_person, $emergency_contact_phone, $boarding_code, $admin_id, $room_id);

                if ($stmt->execute()) {
                    // Set a success message to be displayed on the signup panel
                    $signup_success = "Registration successful! Your account is now pending for administrator approval. You will be notified once the admin confirms your account.";

                } else {
                    $signup_error = "An error occurred during registration. Please try again.";
                }
                $stmt->close();
            }
        }
    }
}

// Determine if we should show the signup panel by default (e.g. after a failed signup attempt)
$show_signup_panel = (isset($_GET['action']) && $_GET['action'] === 'signup') || (isset($_POST['signup']) && (!empty($signup_error) || !empty($signup_success)));

// Consolidate error for toast notification
$toast_message = '';
$toast_title = '';

if (!empty($login_error)) {
    $toast_message = $login_error;
    $toast_title = 'Login Error';
} elseif (!empty($signup_error)) {
    $toast_message = $signup_error;
    $toast_title = 'Signup Error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>HouseMaster — Tenant Portal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* :root {
        --primary-color: #05445E;
        --secondary-color: #189AB4;
        --accent-color: #75E6DA;
    } */
    body {
      background: white;
      font-family: 'Poppins', sans-serif;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 20px;
    }
    .auth-wrapper {
      width: 100%;
      max-width: 900px;
      height: 600px;
      background: #fff;
      border-radius: 20px;
      box-shadow: -5px 8px 6px 0px rgba(0, 0, 0, 0.15);
      display: flex;
      overflow: hidden;
      position: relative;
    }
    .auth-left {
      width: 50%;
      position: relative;
      overflow: hidden;
      background: #fff;
    }
    .form-slide {
      position: absolute;
      top: 0; left: 0;
      width: 100%; height: 100%;
      padding: 50px 40px;
      transition: all 0.6s ease-in-out;
      display: flex;
      flex-direction: column;
      justify-content: center;
      overflow-y: auto;
    }
    /* Hide scrollbar */
    .form-slide::-webkit-scrollbar { display: none; }
    .form-slide { -ms-overflow-style: none; scrollbar-width: none; }

    .login-panel { z-index: 2; opacity: 1; }
    .signup-panel { transform: translateX(100%); z-index: 1; opacity: 0; }
    
    .auth-wrapper.show-signup .login-panel { transform: translateX(-100%); opacity: 0; }
    .auth-wrapper.show-signup .signup-panel { transform: translateX(0); z-index: 2; opacity: 1; }
    
    .auth-branding {
      width: 50%;
      background: #05445E;
      color: #fff;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      text-align: center;
      padding: 40px;
      position: relative;
    }
    .auth-branding .logo {
        width: 120px;
        scale: 200%;
        margin-bottom: 1.5rem;
        filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));
    }
    .auth-branding h2 {
        font-weight: 700;
        font-size: 2rem;
        margin-bottom: 0.5rem;
    }
    .btn-secondary {
      background-color: #05445E !important;
    }
    .btn-disabled {
      color: var(--bs-btn-disabled-color);
    pointer-events: none;
    background-color: var(--bs-btn-disabled-bg);
    
    }
    .auth-branding p {
        font-size: 1rem;
        opacity: 0.9;
    }

    /* Form Styles */
    h3 {
        color: var(--primary-color);
        font-weight: 700;
        margin-bottom: 1.5rem;
    }
    .form-control, .form-select {
        border-radius: 10px;
        padding: 12px 15px;
        border: 1px solid #e1e5eb;
        background-color: #f8f9fa;
        font-size: 0.95rem;
    }
    .form-control:focus, .form-select:focus {
        background-color: #fff;
        border-color: var(--secondary-color);
        box-shadow: 0 0 0 4px rgba(24, 154, 180, 0.1);
    }
    .form-label {
        font-weight: 500;
        font-size: 0.9rem;
        color: #555;
        margin-bottom: 0.4rem;
    }
    .btn-primary {
        background-color: #05445E;
        border: none;
        border-radius: 10px;
        padding: 12px;
        font-weight: 600;
        letter-spacing: 0.5px;
        transition: all 0.3s;
    }
    .btn-primary:hover {
        background-color: #03364a;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(5, 68, 94, 0.3);
    }
    .btn-success {
        --bs-btn-color: #fff;
    --bs-btn-bg: blue;
    --bs-btn-border-color: blue;
    --bs-btn-hover-color: #fff;
    --bs-btn-hover-bg: blue;
    --bs-btn-hover-border-color: blue;
    --bs-btn-focus-shadow-rgb: 60, 153, 110;
    --bs-btn-active-color: #fff;
    --bs-btn-active-bg: blue;
    --bs-btn-active-border-color: blue;
    --bs-btn-active-shadow: inset 0 3px 5px rgba(0, 0, 0, 0.125);
    --bs-btn-disabled-color: #fff;
    --bs-btn-disabled-bg: blue;
    --bs-btn-disabled-border-color: blue
    }
    .btn:disabled {
      background-color: #0051ffeb !important;
          
      border-color: #0051ffeb !important;
    }
    .btn-success:hover {
        background-color: #13849a;
        transform: translateY(-2px);
    }
    .btn-secondary {
        border-radius: 10px;
        padding: 12px;
    }
    .link-primary {
        color: var(--secondary-color);
        text-decoration: none;
        font-weight: 600;
        cursor: pointer;
    }
    .link-primary:hover {
        color: var(--primary-color);
        text-decoration: underline;
    }
    
    /* Steps */
    .step { display: none; animation: fadeIn 0.4s; }
    .step.active { display: block; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

    @media (max-width: 768px) {
        .auth-wrapper {
            flex-direction: column;
            height: auto;
            min-height: 100vh;
            border-radius: 0;
        }
        .auth-branding {
            width: 100%;
            padding: 30px 20px;
            order: -1;
        }
        .auth-left {
            width: 100%;
            height: auto;
            min-height: 500px;
        }
        .form-slide {
            position: relative;
            height: auto;
            padding: 30px;
        }
        .signup-panel {
            transform: none;
            display: none;
        }
        .auth-wrapper.show-signup .login-panel {
            display: none;
        }
        .auth-wrapper.show-signup .signup-panel {
            display: block;
            transform: none;
        }
    }
  </style>
</head>
<body>
  <div class="auth-wrapper <?php echo $show_signup_panel ? 'show-signup' : ''; ?>" id="authBox">
    <!-- Left: Forms -->
    <div class="auth-left">
      <!-- Login -->
      <div class="form-slide login-panel">
        <h3>Welcome Back</h3>
        <?php if (!empty($signup_success)) : ?>
          <div class="alert alert-success"><?php echo $signup_success; ?></div>
        <?php endif; ?>
        <form method="POST">
          <div class="mb-3">
            <label class="form-label">Email Address</label>
            <input type="email" name="login_email" class="form-control" placeholder="name@example.com" required autocomplete="email">
          </div>
          <div class="mb-4">
            <label class="form-label">Password</label>
            <div class="input-group">
              <input type="password" name="login_password" id="login_password" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
              <button class="btn btn-outline-secondary bg-light" type="button" onclick="togglePasswordVisibility('login_password', this)">
                  <i class="fas fa-eye"></i></button>
            </div>
          </div>
          <button type="submit" name="login" class="btn btn-primary w-100 mb-3">Sign In</button>
          <p class="text-center">
            <a href="tenant-forgot-password.php" class="text-muted small">Forgot Password?</a><br>
            <span class="small">Don’t have an account? <span class="link-primary" onclick="toggleSignup()">Sign Up</span></span>
          </p>
        </form>
      </div>

      <!-- Signup -->
      <div class="form-slide signup-panel">
        <h3>Create Account</h3>
        <?php if (!empty($signup_success)) : ?>
            <div class="alert alert-success text-center"><?php echo $signup_success; ?></div>
            <p class="text-center"><a href="../login.php" class="link-primary text-decoration-none">Click here to Login</a></p>
        <?php else: // Only show the form if there is no success message ?>
            <form method="POST">
              <!-- Step 1 -->
              <div class="step active" id="step1">
                <div class="row"><div class="col-md-8 mb-3"><label class="form-label">Full Name</label><input type="text" name="fullname" id="fullname" class="form-control" required></div><div class="col-md-4 mb-3"><label class="form-label">Age</label><input type="number" name="age" id="age" class="form-control" required></div></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" id="email" class="form-control" required></div>
                <div class="row">
                  <div class="col-md-6 mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                      <input type="password" name="password" id="password" class="form-control" minlength="6" required autocomplete="new-password">
                      <button class="btn btn-outline-secondary bg-light" type="button" onclick="togglePasswordVisibility('password', this)">
                          <i class="fas fa-eye"></i></button>
                    </div>
                  </div><div class="col-md-6 mb-3"><label class="form-label">Phone</label><input type="text" name="phone" id="phone" class="form-control" required></div></div>
                <div class="row"><div class="col-md-6 mb-3"><label class="form-label">Emergency Contact</label><input type="text" name="emergency_contact_person" id="emergency_contact_person" class="form-control" placeholder="Name" required></div><div class="col-md-6 mb-3"><label class="form-label">Emergency Phone</label><input type="text" name="emergency_contact_phone" id="emergency_contact_phone" class="form-control" placeholder="Number" required></div></div>
                <button type="button" class="btn btn-primary w-100" onclick="nextStepWithValidation(event, 1);">Next</button>
                <p class="text-center mt-3 small"><a href="../login.php" class="link-primary text-decoration-none">Back to Login</a></p>
              </div>
              <!-- Step 2 -->
              <div class="step" id="step2">
                <div class="mb-3"><label class="form-label">Boarding House Code</label><input type="text" name="boarding_code" id="boarding_code" class="form-control" placeholder="Enter code from your admin" required onkeyup="getRooms()" oninput="getRooms()"></div>
                <div id="room-selection-wrapper" class="mb-3"><label class="form-label">Available Rooms</label><select name="room_id" id="room_id" class="form-select" required disabled><option value="">Enter a valid code first</option></select></div>
                <div id="boarding-code-error" class="text-danger mt-1 mb-2" style="display:none;"></div>
                <button type="button" class="btn btn-secondary w-100 mb-2" onclick="prevStep(2)">Back</button> 
                <button type="submit" name="signup" class="btn btn-success w-100" disabled>Sign Up</button>
              </div>
            </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right: Branding -->
    <div class="auth-branding">
      <img src="../assets/img/logo remove.png" alt="HouseMaster Logo" class="logo">
      
      <p>Boarding House & Dormitory Management System</p>
    </div>
  </div>

  <!-- Toast for Errors -->
  <div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100">
    <div id="errorToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="toast-header bg-danger text-white">
        <strong class="me-auto" id="errorToastTitle"></strong>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
      <div class="toast-body" id="errorToastBody">
        <!-- Error message will be injected here -->
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    const authBox = document.getElementById("authBox");
    let currentStep = 1;

    function togglePasswordVisibility(inputId, button) {
        const passwordInput = document.getElementById(inputId);
        const icon = button.querySelector('i');
        
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        } else {
            passwordInput.type = 'password';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        }
    }
    function toggleSignup() {
      authBox.classList.toggle("show-signup");
      // Reset to step 1 when toggling
      document.getElementById('step1').classList.add('active');
      document.getElementById('step2').classList.remove('active');
      currentStep = 1;
    }

    function nextStepWithValidation(event, step) {
        event.preventDefault(); // Prevent the form from submitting
        const currentStepDiv = document.getElementById('step' + step);
        const inputs = currentStepDiv.querySelectorAll('input, select, textarea');
        let isStepValid = true;

        // Loop through all inputs in the current step and check their validity
        for (const input of inputs) {
            if (!input.checkValidity()) {
                // Trigger the browser's validation UI for the invalid field
                input.reportValidity(); 
                isStepValid = false;
                // Stop the loop and function execution at the first invalid field
                return; 
            }
        }

        // If all fields are valid, proceed to the next step
        if (isStepValid) {
            nextStep(step);
        }
    }

    function nextStep(step) {
        document.getElementById('step' + step).classList.remove('active');
        document.getElementById('step' + (step + 1)).classList.add('active');
        currentStep = step + 1;
    }

    function prevStep(step) {
        // Hide room selection when going back
        document.getElementById('step' + step).classList.remove('active');
        document.getElementById('step' + (step - 1)).classList.add('active');
        currentStep = step - 1;
    }

    function getRooms() {
        const boardingCode = document.getElementById('boarding_code').value.trim();
        const roomSelect = document.getElementById('room_id');
        const errorCode = document.getElementById('boarding-code-error');
        const signupButton = document.querySelector('#step2 button[name="signup"]');

        // Reset state and disable signup button on every input change
        errorCode.style.display = 'none'; // Hide error message
        signupButton.disabled = true; // Disable button by default
        roomSelect.innerHTML = '<option value="">Enter a valid code first</option>';
        
        if (boardingCode.length > 0) { // Check if the input is not empty, instead of a fixed length
            fetch(`get-rooms.php?boarding_code=${boardingCode}`)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        roomSelect.innerHTML = '<option value="">Invalid code</option>';
                        errorCode.textContent = data.error;
                        roomSelect.disabled = true; // Keep dropdown disabled
                        signupButton.disabled = true; // Ensure button is disabled on error
                        errorCode.style.display = 'block';
                    } else if (data.length > 0) {
                        roomSelect.innerHTML = ''; // Clear placeholder
                        data.forEach(room => {
                            const option = document.createElement('option');
                            option.value = room.id;
                            option.textContent = `${room.room_label} (${room.tenants}/${room.capacity})`;
                            roomSelect.appendChild(option);
                        });
                        roomSelect.disabled = false;
                        signupButton.disabled = false; // Enable the button since rooms are available
                        errorCode.style.display = 'none';
                    } else {
                        roomSelect.innerHTML = '<option value="">No rooms available</option>';
                        roomSelect.disabled = true;
                        signupButton.disabled = true; // Ensure button is disabled if no rooms
                        errorCode.textContent = 'No available rooms for this boarding house.';
                        errorCode.style.display = 'block';
                    }
                });
        } else {
            // If the input is empty, ensure everything is reset and disabled.
            signupButton.disabled = true;
            roomSelect.disabled = true;
        }
    }

    <?php if (!empty($toast_message)): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const errorToastEl = document.getElementById('errorToast');
        const errorToastTitle = document.getElementById('errorToastTitle');
        const errorToastBody = document.getElementById('errorToastBody');
        
        errorToastTitle.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i> ' + <?php echo json_encode($toast_title); ?>;
        errorToastBody.innerHTML = <?php echo json_encode($toast_message); ?>;
        
        const errorToast = new bootstrap.Toast(errorToastEl);
        errorToast.show();
    });
    <?php endif; ?>

    // Add an event listener to the room select dropdown
    document.getElementById('room_id').addEventListener('change', function() {
        const signupButton = document.querySelector('#step2 button[name="signup"]');
        // Enable the signup button if a valid room is selected (i.e., the value is not empty)
        if (this.value) {
            signupButton.disabled = false;
        } else {
            signupButton.disabled = true;
        }
    });
  </script>
</body>
</html>
