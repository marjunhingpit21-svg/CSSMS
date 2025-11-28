<?php
include '../database/db.php';
session_start();

// Redirect if already logged in as cashier
if (isset($_SESSION['employee_id']) && $_SESSION['role'] === 'cashier') {
    header('Location: cashier_dashboard.php');
    exit();
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_number = trim($_POST['employee_number'] ?? '');
    $password = $_POST['password'] ?? '';
    $branch_name = trim($_POST['branch'] ?? '');

    // Basic validation
    if (empty($employee_number) || empty($password) || empty($branch_name)) {
        $error = 'Please fill in all fields.';
    } else {
        try {
            // Query: Find active cashier with matching employee_number and branch_name
            $stmt = $conn->prepare("
                SELECT 
                    e.employee_id,
                    e.employee_number,
                    e.first_name,
                    e.last_name,
                    e.password_hash,
                    e.position,
                    e.branch_id,
                    e.is_active,
                    b.branch_name
                FROM employees e
                INNER JOIN branches b ON e.branch_id = b.branch_id
                WHERE e.employee_number = ?
                  AND b.branch_name = ?
                  AND e.position = 'cashier'
                LIMIT 1
            ");
            $stmt->bind_param("ss", $employee_number, $branch_name);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();

                // Check if account is active
                if (!$user['is_active']) {
                    $error = 'Your account has been deactivated. Please contact your manager.';
                }
                // Verify password
                elseif (password_verify($password, $user['password_hash'])) {
                    // Success! Create session
                    $_SESSION['employee_id'] = $user['employee_id'];
                    $_SESSION['employee_number'] = $user['employee_number'];
                    $_SESSION['cashier_name'] = $user['first_name'] . ' ' . $user['last_name'];
                    $_SESSION['branch_id'] = $user['branch_id'];
                    $_SESSION['branch_name'] = $user['branch_name'];
                    $_SESSION['role'] = 'cashier';

                    // Update last login timestamp
                    $update = $conn->prepare("UPDATE employees SET last_login = NOW() WHERE employee_id = ?");
                    $update->bind_param("i", $user['employee_id']);
                    $update->execute();
                    $update->close();

                    // Redirect to cashier dashboard
                    header('Location: cashier_dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid password. Please try again.';
                }
            } else {
                $error = 'Invalid Employee ID or Branch. Only cashiers can log in here.';
            }

            $stmt->close();
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $error = 'System error. Please try again later.';
        }
    }
}

// Fetch active branches for dropdown
try {
    $branches_result = $conn->query("SELECT branch_name FROM branches WHERE is_active = 1 ORDER BY branch_name");
    $branches = [];
    while ($row = $branches_result->fetch_assoc()) {
        $branches[] = $row['branch_name'];
    }
} catch (Exception $e) {
    $branches = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cashier Portal - TrendyWear</title>
    <link rel="stylesheet" href="css/cashier_login.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <div class="login-landscape">
        <!-- Left Panel - Branding -->
        <div class="branding-panel">
            <div class="branding-overlay"></div>
            <div class="branding-content">
                <div class="branding-logo">
                    <div class="logo-icon-large">
                        <svg width="56" height="56" viewBox="0 0 40 40" fill="none">
                            <path d="M20 5L10 15H16V30H24V15H30L20 5Z" fill="white"/>
                            <circle cx="20" cy="35" r="1.5" fill="white"/>
                        </svg>
                    </div>
                    <h1>TrendyWear</h1>
                    <p>Stock Management System</p>
                </div>

                <div class="feature-list-compact">
                    <div class="feature-item-compact">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <span>Real-time Sales</span>
                    </div>
                    <div class="feature-item-compact">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <span>Secure Login</span>
                    </div>
                    <div class="feature-item-compact">
                        <svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        <span>Multi-branch</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel - Form -->
        <div class="form-panel">
            <div class="form-content">
                <div class="login-header">
                    <div class="brand-mini">
                        <div class="brand-icon">
                            <svg width="32" height="32" viewBox="0 0 40 40" fill="none">
                                <path d="M20 5L10 15H16V30H24V15H30L20 5Z" fill="#e91e63"/>
                                <circle cx="20" cy="35" r="1.5" fill="#e91e63"/>
                            </svg>
                        </div>
                        <div class="brand-text">
                            <span class="brand-name">TrendyWear</span>
                            <span class="brand-subtitle">Cashier Portal</span>
                        </div>
                    </div>
                </div>

                <div class="welcome-section">
                    <h1>Welcome Back</h1>
                    <p>Sign in to start your shift</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                        </svg>
                        <span><?php echo htmlspecialchars($error); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="cashier_login.php" class="login-form">
                    <div class="form-row">
                        <div class="input-group">
                            <label for="employee_number">Employee ID</label>
                            <div class="input-wrapper">
                                <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                                </svg>
                                <input 
                                    type="text" 
                                    id="employee_number" 
                                    name="employee_number"
                                    value="<?php echo htmlspecialchars($employee_number ?? ''); ?>"
                                    placeholder="EMP-XXXX"
                                    required
                                    autocomplete="username"
                                >
                            </div>
                        </div>

                        <div class="input-group">
                            <label for="branch">Branch Location</label>
                            <div class="input-wrapper">
                                <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                </svg>
                                <select id="branch" name="branch" required>
                                    <option value="">Select branch</option>
                                    <?php foreach ($branches as $b): ?>
                                        <option value="<?php echo htmlspecialchars($b); ?>" 
                                            <?php echo (isset($_POST['branch']) && $_POST['branch'] === $b) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($b); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="input-group">
                        <label for="password">Password</label>
                        <div class="input-wrapper">
                            <svg class="input-icon" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd"/>
                            </svg>
                            <input 
                                type="password" 
                                id="password" 
                                name="password"
                                placeholder="Enter your password"
                                required
                                autocomplete="current-password"
                            >
                        </div>
                    </div>

                    <div class="form-options">
                        <label class="checkbox-label">
                            <input type="checkbox" name="remember" id="remember">
                            <span class="checkbox-custom"></span>
                            <span class="checkbox-text">Remember me</span>
                        </label>
                        <a href="forgot_password.php" class="link-primary">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn-primary">
                        <span>Sign In</span>
                        <svg width="18" height="18" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                        </svg>
                    </button>
                </form>

                <div class="form-footer">
                    <p>Need help? <a href="contact_support.php" class="link-secondary">Contact Support</a></p>
                    <p class="divider-text">or</p>
                    <p>Manager/Supervisor login? <a href="../admin/login.php" class="link-secondary">Go to Admin Portal</a></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>