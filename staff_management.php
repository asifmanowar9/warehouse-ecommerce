<?php
// Start of staff_management.php

require 'db.php';
require 'functions.php';

requireLogin();
requireAdmin(); // Only allow admins to access this page

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_staff':
            // Add new staff member
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if ($username && filter_var($email, FILTER_VALIDATE_EMAIL) && $password) {
                try {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'staff')");
                    $stmt->execute([$username, $email, $hash]);
                    $successMsg = "Staff member added successfully";
                } catch (PDOException $e) {
                    $errorMsg = "Error adding staff member: " . $e->getMessage();
                }
            } else {
                $errorMsg = "Please fill all fields with valid data";
            }
            break;
            
        case 'change_role':
            // Change role of existing user
            $userId = intval($_POST['user_id'] ?? 0);
            $newRole = $_POST['role'] ?? '';
            
            if ($userId && in_array($newRole, ['admin', 'staff', 'user'])) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                    $stmt->execute([$newRole, $userId]);
                    $successMsg = "User role updated successfully";
                } catch (PDOException $e) {
                    $errorMsg = "Error updating role: " . $e->getMessage();
                }
            } else {
                $errorMsg = "Invalid user or role";
            }
            break;
            
        case 'delete_user':
            // Delete a user
            $userId = intval($_POST['user_id'] ?? 0);
            
            if ($userId) {
                // Check if trying to delete yourself
                if ($userId == $_SESSION['uid']) {
                    $errorMsg = "You cannot delete your own account";
                    break;
                }
                
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $successMsg = "User deleted successfully";
                } catch (PDOException $e) {
                    $errorMsg = "Error deleting user: " . $e->getMessage();
                }
            } else {
                $errorMsg = "Invalid user ID";
            }
            break;
    }
}

// Get all staff and admin users
try {
    $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE role IN ('staff', 'admin') ORDER BY role, username");
    $stmt->execute();
    $staffUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMsg = "Error fetching staff users: " . $e->getMessage();
    $staffUsers = [];
}

// Get regular users
try {
    $stmt = $pdo->prepare("SELECT id, username, email, role FROM users WHERE role = 'user' ORDER BY username");
    $stmt->execute();
    $regularUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMsg = "Error fetching regular users: " . $e->getMessage();
    $regularUsers = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff & User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>
    <!-- Include the navbar -->
    <?php include 'includes/navbar.php'; ?>

    <header>
        <div class="container">
            <h1 class="display-5 fw-bold mb-3">USER MANAGEMENT</h1>
            <p class="lead mb-0">Manage staff accounts, users and permissions | <?= date('Y-m-d') ?> | Admin: <?= htmlspecialchars($_SESSION['username']) ?></p>
        </div>
    </header>

    <main class="container py-5">
        <?php if (isset($successMsg)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($successMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($errorMsg)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($errorMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Add Staff Button -->
        <div class="mb-4">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
                <i class="bi bi-person-plus-fill me-1"></i> Add New Staff Member
            </button>
        </div>
        
        <!-- Staff and Admins Section -->
        <div class="card-glass p-4 mb-4">
            <h4 class="fw-bold mb-3">STAFF & ADMINS</h4>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>USERNAME</th>
                            <th>EMAIL</th>
                            <th>ROLE</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($staffUsers) > 0): ?>
                            <?php foreach ($staffUsers as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $user['role'] === 'admin' ? 'primary' : 'info' ?>">
                                            <?= strtoupper($user['role']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#editRoleModal" 
                                                    data-user-id="<?= $user['id'] ?>" 
                                                    data-username="<?= htmlspecialchars($user['username']) ?>" 
                                                    data-role="<?= $user['role'] ?>">
                                                <i class="bi bi-pencil-square me-1"></i> Role
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['uid']): // Don't allow deleting yourself ?>
                                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal" 
                                                    data-user-id="<?= $user['id'] ?>" 
                                                    data-username="<?= htmlspecialchars($user['username']) ?>">
                                                <i class="bi bi-trash me-1"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No staff members found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Regular Users Section -->
        <div class="card-glass p-4">
            <h4 class="fw-bold mb-3">REGULAR USERS</h4>
            <div class="table-responsive">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>USERNAME</th>
                            <th>EMAIL</th>
                            <th>ACTIONS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($regularUsers) > 0): ?>
                            <?php foreach ($regularUsers as $user): ?>
                                <tr>
                                    <td><?= $user['id'] ?></td>
                                    <td><?= htmlspecialchars($user['username']) ?></td>
                                    <td><?= htmlspecialchars($user['email']) ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#editRoleModal" 
                                                    data-user-id="<?= $user['id'] ?>" 
                                                    data-username="<?= htmlspecialchars($user['username']) ?>" 
                                                    data-role="<?= $user['role'] ?>">
                                                <i class="bi bi-person-gear me-1"></i> Promote
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal" 
                                                    data-user-id="<?= $user['id'] ?>" 
                                                    data-username="<?= htmlspecialchars($user['username']) ?>">
                                                <i class="bi bi-trash me-1"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center">No regular users found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Add Staff Modal -->
    <div class="modal fade" id="addStaffModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content card-glass text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Add New Staff Member</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addStaffForm" method="post" action="staff_management.php">
                        <input type="hidden" name="action" value="add_staff">
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control search-box" id="username" name="username" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control search-box" id="email" name="email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control search-box" id="password" name="password" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="addStaffForm" class="btn btn-primary">Add Staff Member</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Role Modal -->
    <div class="modal fade" id="editRoleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content card-glass text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Change User Role</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editRoleForm" method="post" action="staff_management.php">
                        <input type="hidden" name="action" value="change_role">
                        <input type="hidden" name="user_id" id="editUserId">
                        
                        <p>Change role for <strong id="editUsername"></strong>:</p>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="role" id="roleUser" value="user">
                            <label class="form-check-label" for="roleUser">
                                Regular User
                            </label>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="role" id="roleStaff" value="staff">
                            <label class="form-check-label" for="roleStaff">
                                Staff
                            </label>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="role" id="roleAdmin" value="admin">
                            <label class="form-check-label" for="roleAdmin">
                                Administrator
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="editRoleForm" class="btn btn-primary">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content card-glass text-white">
                <div class="modal-header border-0">
                    <h5 class="modal-title">Delete User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                    <p class="mt-3 mb-1">Are you sure you want to delete this user?</p>
                    <p class="fw-bold mb-3" id="deleteUsername"></p>
                    <p class="small text-danger">This action cannot be undone and will delete all data associated with this user.</p>
                    
                    <form id="deleteUserForm" method="post" action="staff_management.php">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" id="deleteUserId">
                    </form>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" form="deleteUserForm" class="btn btn-danger">Delete User</button>
                </div>
            </div>
        </div>
    </div>

    <footer class="mt-auto text-center py-3 text-light">
        <div class="container d-flex justify-content-between align-items-center">
            <span>© <?= date('Y') ?> WAREHOUSE MANAGEMENT SYSTEM</span>
            <span>ADMIN: <?= htmlspecialchars($_SESSION['username']) ?></span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit Role Modal
        document.getElementById('editRoleModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const username = button.getAttribute('data-username');
            const role = button.getAttribute('data-role');
            
            document.getElementById('editUserId').value = userId;
            document.getElementById('editUsername').textContent = username;
            
            // Set the appropriate radio button
            document.getElementById('roleUser').checked = role === 'user';
            document.getElementById('roleStaff').checked = role === 'staff';
            document.getElementById('roleAdmin').checked = role === 'admin';
        });
        
        // Delete User Modal
        document.getElementById('deleteUserModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const username = button.getAttribute('data-username');
            
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUsername').textContent = username;
        });
    </script>
</body>
</html>