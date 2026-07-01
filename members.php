<?php
/**
 * Members Management Page
 */
require_once __DIR__ . '/includes/config.php';
requireAuth();
requirePermission('view_members');

$db = getConnection();
$currentUser = getCurrentUser();
$pageTitle = 'Members';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    // Add / Edit member
    if (isset($_POST['save_member'])) {
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $nationalId = trim($_POST['national_id'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $gender = $_POST['gender'] ?? '';
        $dateJoined = $_POST['date_joined'] ?? date('Y-m-d');
        $status = $_POST['status'] ?? 'active';

        $errors = [];

        if (empty($firstName)) $errors[] = 'First name is required';
        if (empty($lastName)) $errors[] = 'Last name is required';
        if (empty($phone)) $errors[] = 'Phone is required';

        // Server-side validation for cascading location FKs
        $countyId = (int)($_POST['county_id'] ?? 0);
        $subCountyId = (int)($_POST['sub_county_id'] ?? 0);
        $wardId = (int)($_POST['ward_id'] ?? 0);
        if ($countyId) {
            $check = $db->prepare("SELECT id FROM counties WHERE id=?");
            $check->execute([$countyId]);
            if (!$check->fetch()) $errors[] = 'Invalid county selected';
        }
        if ($subCountyId) {
            $check = $db->prepare("SELECT id FROM sub_counties WHERE id=? AND county_id=?");
            $check->execute([$subCountyId, $countyId]);
            if (!$check->fetch()) $errors[] = 'Invalid sub-county for selected county';
        }
        if ($wardId) {
            $check = $db->prepare("SELECT id FROM wards WHERE id=? AND sub_county_id=?");
            $check->execute([$wardId, $subCountyId]);
            if (!$check->fetch()) $errors[] = 'Invalid ward for selected sub-county';
        }

        if (empty($errors)) {
            try {
                $data = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'national_id' => $nationalId ?: null,
                    'phone' => $phone,
                    'email' => $email ?: null,
                    'gender' => $gender ?: null,
                    'date_joined' => $dateJoined,
                    'status' => $status,
                    'occupation' => trim($_POST['occupation'] ?? ''),
                    'employer' => trim($_POST['employer'] ?? ''),
                    'address' => trim($_POST['address'] ?? ''),
                    'county' => trim($_POST['county'] ?? ''),
                    'sub_county' => trim($_POST['sub_county'] ?? ''),
                    'ward' => trim($_POST['ward'] ?? ''),
                    'county_id' => !empty($_POST['county_id']) ? (int)$_POST['county_id'] : null,
                    'sub_county_id' => !empty($_POST['sub_county_id']) ? (int)$_POST['sub_county_id'] : null,
                    'ward_id' => !empty($_POST['ward_id']) ? (int)$_POST['ward_id'] : null,
                    'date_of_birth' => $_POST['date_of_birth'] ?: null,
                    'emergency_name' => trim($_POST['emergency_name'] ?? ''),
                    'emergency_phone' => trim($_POST['emergency_phone'] ?? ''),
                    'emergency_relation' => trim($_POST['emergency_relation'] ?? ''),
                    'group_code' => $_SESSION['group_code'],
                    'passport' => trim($_POST['passport'] ?? ''),
                ];

                // Handle photo upload
                if (!empty($_FILES['photo']['name'])) {
                    $photo = uploadFile($_FILES['photo'], __DIR__ . '/uploads/members', ['jpg', 'jpeg', 'png', 'gif']);
                    if ($photo) $data['photo'] = $photo;
                }

                if ($id && hasPermission('edit_members')) {
                    // Update
                    $sets = [];
                    $params = [];
                    foreach ($data as $key => $value) {
                        $sets[] = "$key = ?";
                        $params[] = $value;
                    }
                    $params[] = $id;
                    $db->prepare("UPDATE members SET " . implode(', ', $sets) . " WHERE id = ?")->execute($params);

                    logAudit($_SESSION['user_id'], $_SESSION['username'], 'update', 'members', $id, null, $data, 'Updated member');
                    setFlash('success', 'Member updated successfully');
                } elseif (hasPermission('create_members')) {
                    // Insert
                    $data['member_no'] = generateMemberNumber();
                    $data['created_by'] = $_SESSION['user_id'];

                    $columns = implode(', ', array_keys($data));
                    $placeholders = implode(', ', array_fill(0, count($data), '?'));
                    $stmt = $db->prepare("INSERT INTO members ($columns) VALUES ($placeholders)");
                    $stmt->execute(array_values($data));
                    $newId = $db->lastInsertId();

                    logAudit($_SESSION['user_id'], $_SESSION['username'], 'create', 'members', $newId, null, $data, 'Created new member');
                    setFlash('success', 'Member added successfully. Member No: ' . $data['member_no']);
                }

                redirect('members.php');
            } catch (Exception $e) {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            $errorMsg = implode('<br>', $errors);
            setFlash('danger', $errorMsg);
        }
    }

    // Delete member
    if (isset($_POST['delete_member'])) {
        if ($_SESSION['role_id'] != 1) {
            setFlash('danger', 'Only Super Administrators can delete members.');
            redirect('members.php');
        }
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("SELECT id, first_name, last_name, member_no FROM members WHERE id=? AND group_code=?");
        $stmt->execute([$id, $_SESSION['group_code']]);
        $member = $stmt->fetch();
        if (!$member) { setFlash('danger', 'Member not found.'); redirect('members.php'); }

        // CASCADE foreign keys handle related records cleanup (loans, contributions, shares, attendance, etc.)
        try {
            $db->prepare("DELETE FROM members WHERE id=? AND group_code=?")->execute([$id, $_SESSION['group_code']]);
            logAudit($_SESSION['user_id'], $_SESSION['username'], 'delete', 'members', $id, ['first_name' => $member['first_name'], 'last_name' => $member['last_name'], 'member_no' => $member['member_no']], null, 'Deleted member');
            setFlash('success', 'Member ' . $member['first_name'] . ' ' . $member['last_name'] . ' deleted successfully.');
        } catch (Exception $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
        redirect('members.php');
    }

    // Toggle status
    if (isset($_POST['toggle_status']) && hasPermission('suspend_members')) {
        $newStatus = $_POST['new_status'] ?? 'active';
        $db->prepare("UPDATE members SET status = ? WHERE id = ? AND group_code = ?")->execute([$newStatus, $id, $_SESSION['group_code']]);

        // Sync linked user status: deactivate when member is not active, reactivate when member becomes active
        $userSync = $db->prepare("UPDATE users SET status = ? WHERE member_id = ? AND group_code = ?");
        $userStatus = ($newStatus === 'active') ? 'active' : 'inactive';
        $userSync->execute([$userStatus, $id, $_SESSION['group_code']]);

        logAudit($_SESSION['user_id'], $_SESSION['username'], 'update', 'members', $id, null, ['status' => $newStatus], 'Changed member status');
        setFlash('success', 'Member status updated');
        redirect('members.php');
    }

    // Approve member registration
    if (isset($_POST['approve_member']) && hasPermission('approve_members')) {
        require_once __DIR__ . '/includes/member_workflow.php';

        $password = $_POST['password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($password) < 6) {
            setFlash('danger', 'Password must be at least 6 characters.');
            redirect('members.php?action=view&id=' . $id);
        }
        if ($password !== $confirm) {
            setFlash('danger', 'Passwords do not match.');
            redirect('members.php?action=view&id=' . $id);
        }

        $result = approveMember($id, (int)$_SESSION['user_id'], $_SESSION['username']);
        if ($result['success']) {
            $userResult = createUserFromMember($id, $password);
            if ($userResult['success']) {
                $emailResult = sendCredentialsEmail($userResult['first_name'], $userResult['email'], $userResult['username'], $password);
                $msg = $result['message'] . ' User account created (username: ' . $userResult['username'] . ').';
                if (!$emailResult['success']) {
                    $msg .= ' Credentials email could not be sent: ' . ($emailResult['error'] ?? 'unknown error');
                }
                setFlash('success', $msg);
            } else {
                setFlash('warning', $result['message'] . ' But user account creation failed: ' . $userResult['error']);
            }
        } else {
            setFlash('danger', $result['error']);
        }
        redirect('members.php?action=view&id=' . $id);
    }

    // Reject member registration
    if (isset($_POST['reject_member']) && hasPermission('approve_members')) {
        $reason = trim($_POST['rejection_reason'] ?? '');
        if (empty($reason)) {
            setFlash('danger', 'Rejection reason is required.');
            redirect('members.php?action=view&id=' . $id);
        }
        require_once __DIR__ . '/includes/member_workflow.php';
        $result = rejectMember($id, (int)$_SESSION['user_id'], $_SESSION['username'], $reason);
        if ($result['success']) {
            setFlash('success', $result['message']);
        } else {
            setFlash('danger', $result['error']);
        }
        redirect('members.php?action=view&id=' . $id);
    }
}

// Get member data for editing
$member = null;
if ($id && ($action === 'edit' || $action === 'view')) {
    $stmt = $db->prepare("SELECT * FROM members WHERE id = ? AND group_code = ?");
    $stmt->execute([$id, $_SESSION['group_code']]);
    $member = $stmt->fetch();
    if (!$member) {
        setFlash('danger', 'Member not found');
        redirect('members.php');
    }
}

// Load location data for forms
$counties = [];
$subCountiesJson = '{}';
$wardsJson = '{}';
if ($action === 'add' || $action === 'edit') {
    $countiesStmt = $db->query("SELECT id, name FROM counties ORDER BY name");
    $counties = $countiesStmt->fetchAll();
    // Embed all sub-counties and wards as JS objects (bypass AJAX session issues)
    $subs = $db->query("SELECT id, name, county_id FROM sub_counties ORDER BY name")->fetchAll();
    $grouped = [];
    foreach ($subs as $s) { $grouped[$s['county_id']][] = ['id' => (int)$s['id'], 'name' => $s['name']]; }
    $subCountiesJson = json_encode($grouped);
    $wards = $db->query("SELECT id, name, sub_county_id FROM wards ORDER BY name")->fetchAll();
    $groupedW = [];
    foreach ($wards as $w) { $groupedW[$w['sub_county_id']][] = ['id' => (int)$w['id'], 'name' => $w['name']]; }
    $wardsJson = json_encode($groupedW);
}

// Pagination for listing
$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$where = "WHERE group_code = ?";
$params = [$_SESSION['group_code']];

if ($search) {
    $where .= " AND (first_name LIKE ? OR last_name LIKE ? OR member_no LIKE ? OR phone LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}
if ($statusFilter) {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}

$totalMembers = $db->prepare("SELECT COUNT(*) FROM members $where");
$totalMembers->execute($params);
$totalRecords = $totalMembers->fetchColumn();

$pagination = paginate($totalRecords, $page);

$members = $db->prepare("SELECT * FROM members $where ORDER BY created_at DESC LIMIT ? OFFSET ?");
$members->execute(array_merge($params, [$pagination['perPage'], $pagination['offset']]));
$membersList = $members->fetchAll();

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h4>
                <?php if ($action === 'add'): ?>Add Member
                <?php elseif ($action === 'edit'): ?>Edit Member
                <?php elseif ($action === 'view'): ?>Member Profile
                <?php else: ?>Members
                <?php endif; ?>
            </h4>
            <p>
                <?php if ($action === 'list'): ?>Manage all registered members
                <?php else: ?><?= e($member['first_name'] ?? '') ?> <?= e($member['last_name'] ?? '') ?>
                <?php endif; ?>
            </p>
        </div>
        <?php if ($action === 'list'): ?>
        <div class="d-flex gap-2">
            <a href="members.php?action=add" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Add Member
            </a>
        </div>
        <?php elseif ($action === 'view' || $action === 'edit'): ?>
        <div class="d-flex gap-2">
            <a href="members.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back
            </a>
            <?php if ($action === 'view' && hasPermission('edit_members')): ?>
                <a href="members.php?action=edit&id=<?= $id ?>" class="btn btn-primary">
                    <i class="fas fa-edit me-1"></i>Edit
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php displayFlash(); ?>

    <?php if ($action === 'list'): ?>
    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by name, ID or phone..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="pending_verification" <?= $statusFilter === 'pending_verification' ? 'selected' : '' ?>>Pending Verification</option>
                        <option value="email_verified" <?= $statusFilter === 'email_verified' ? 'selected' : '' ?>>Email Verified</option>
                        <option value="pending_approval" <?= $statusFilter === 'pending_approval' ? 'selected' : '' ?>>Pending Approval</option>
                        <option value="suspended" <?= $statusFilter === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        <option value="terminated" <?= $statusFilter === 'terminated' ? 'selected' : '' ?>>Terminated</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="members.php" class="btn btn-outline-secondary w-100"><i class="fas fa-redo me-1"></i>Reset</a>
                </div>
                <div class="col-md-2">
                    <a href="reports/members.php?export=excel" class="btn btn-outline-success w-100"><i class="fas fa-file-excel me-1"></i>Export</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Members Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover datatable mb-0" data-table-name="members">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Member No</th>
                            <th>National ID</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Gender</th>
                            <th>Joined</th>
                            <th>Status</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                            <?php foreach ($membersList as $m): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar user-avatar-sm me-2">
                                                <?php if (!empty($m['photo'])): ?>
                                                    <img src="<?= BASE_URL ?>uploads/members/<?= e($m['photo']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                                <?php else: ?>
                                                    <?= strtoupper(substr($m['first_name'], 0, 1)) . strtoupper(substr($m['last_name'], 0, 1)) ?>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <a href="members.php?action=view&id=<?= $m['id'] ?>" class="text-decoration-none" style="font-weight: 500;">
                                                    <?= e($m['first_name'] . ' ' . $m['last_name']) ?>
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-size: 0.8125rem;"><?= e($m['member_no']) ?></td>
                                    <td style="font-size: 0.8125rem;"><?= e($m['national_id'] ?? '-') ?></td>
                                    <td style="font-size: 0.8125rem;"><?= e($m['phone']) ?></td>
                                    <td style="font-size: 0.8125rem;"><?= e($m['email'] ?? '-') ?></td>
                                    <td><?= e(ucfirst($m['gender'] ?? '-')) ?></td>
                                    <td style="font-size: 0.8125rem;"><?= formatDate($m['date_joined']) ?></td>
                                    <td><?= statusBadge($m['status']) ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="members.php?action=view&id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary btn-icon" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (hasPermission('edit_members')): ?>
                                                <a href="members.php?action=edit&id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-warning btn-icon" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php if (hasPermission('suspend_members') && $m['status'] === 'active'): ?>
                                                <button onclick="toggleStatus(<?= $m['id'] ?>, 'suspended')" class="btn btn-sm btn-outline-danger btn-icon" title="Suspend">
                                                    <i class="fas fa-ban"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (hasPermission('delete_members') && (int)$_SESSION['role_id'] === 1): ?>
                                            <button class="btn btn-sm btn-outline-danger btn-icon delete-member-btn"
                                                data-id="<?= $m['id'] ?>"
                                                data-name="<?= e($m['first_name'] . ' ' . $m['last_name']) ?>"
                                                data-member-no="<?= e($m['member_no']) ?>"
                                                title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($pagination['totalPages'] > 1): ?>
            <div class="card-footer">
                <?= renderPagination($pagination, 'members.php?' . http_build_query(array_filter(['search' => $search, 'status' => $statusFilter])) . '&') ?>
            </div>
        <?php endif; ?>
    </div>

    <?php elseif ($action === 'add' || $action === 'edit'): ?>
    <!-- Add/Edit Form -->
    <div class="card">
        <div class="card-body">
            <form action="members.php<?= $action === 'edit' ? "?action=edit&id=$id" : '?action=add' ?>" method="POST" enctype="multipart/form-data" data-validate
                  <?php if ($action === 'edit'): ?>
                  data-selected-county="<?= (int)($member['county_id'] ?? 0) ?>"
                  data-selected-subcounty="<?= (int)($member['sub_county_id'] ?? 0) ?>"
                  data-selected-ward="<?= (int)($member['ward_id'] ?? 0) ?>"
                  <?php endif; ?>>
                <?= csrfField() ?>

                <div class="row g-3">
                    <!-- Personal Info -->
                    <div class="col-12"><h6 class="border-bottom pb-2">Personal Information</h6></div>

                    <div class="col-md-4">
                        <label class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" class="form-control" value="<?= e($member['first_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" class="form-control" value="<?= e($member['last_name'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Gender</label>
                        <select name="gender" class="form-select">
                            <option value="">Select Gender</option>
                            <option value="male" <?= ($member['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                            <option value="female" <?= ($member['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                            <option value="other" <?= ($member['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">National ID</label>
                        <input type="text" name="national_id" class="form-control" value="<?= e($member['national_id'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Passport No</label>
                        <input type="text" name="passport" class="form-control" value="<?= e($member['passport'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="date_of_birth" class="form-control" value="<?= e($member['date_of_birth'] ?? '') ?>">
                    </div>

                    <!-- Contact Info -->
                    <div class="col-12"><h6 class="border-bottom pb-2">Contact Information</h6></div>

                    <div class="col-md-4">
                        <label class="form-label">Phone <span class="text-danger">*</span></label>
                        <input type="text" name="phone" class="form-control" value="<?= e($member['phone'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($member['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Occupation</label>
                        <input type="text" name="occupation" class="form-control" value="<?= e($member['occupation'] ?? '') ?>" data-suggest="occupation">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Employer</label>
                        <input type="text" name="employer" class="form-control" value="<?= e($member['employer'] ?? '') ?>" data-suggest="employer">
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2"><?= e($member['address'] ?? '') ?></textarea>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">County <span class="text-danger">*</span></label>
                        <select name="county_id" class="form-select searchable-select" data-placeholder="Select County">
                            <option value="">Select County</option>
                            <?php foreach ($counties as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= ($member['county_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="county" value="<?= e($member['county'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Sub County</label>
                        <select name="sub_county_id" class="form-select">
                            <option value="">Select County first</option>
                        </select>
                        <input type="hidden" name="sub_county" value="<?= e($member['sub_county'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Ward</label>
                        <select name="ward_id" class="form-select searchable-select" data-placeholder="Select Ward">
                            <option value="">Select Sub County first</option>
                        </select>
                        <input type="hidden" name="ward" value="<?= e($member['ward'] ?? '') ?>">
                    </div>

                    <!-- Membership Info -->
                    <div class="col-12"><h6 class="border-bottom pb-2">Membership Details</h6></div>

                    <div class="col-md-4">
                        <label class="form-label">Date Joined <span class="text-danger">*</span></label>
                        <input type="date" name="date_joined" class="form-control" value="<?= e($member['date_joined'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= ($member['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="pending" <?= ($member['status'] ?? '') === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="inactive" <?= ($member['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            <option value="suspended" <?= ($member['status'] ?? '') === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Photo</label>
                        <input type="file" name="photo" class="form-control" accept="image/*">
                        <?php if (!empty($member['photo'])): ?>
                            <small class="d-block mt-1">
                                <img src="<?= BASE_URL ?>uploads/members/<?= e($member['photo']) ?>" style="height: 40px; width: 40px; object-fit: cover; border-radius: 50%;">
                            </small>
                        <?php endif; ?>
                    </div>

                    <!-- Emergency Contact -->
                    <div class="col-12"><h6 class="border-bottom pb-2">Emergency Contact</h6></div>

                    <div class="col-md-4">
                        <label class="form-label">Name</label>
                        <input type="text" name="emergency_name" class="form-control" value="<?= e($member['emergency_name'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Phone</label>
                        <input type="text" name="emergency_phone" class="form-control" value="<?= e($member['emergency_phone'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Relationship</label>
                        <input type="text" name="emergency_relation" class="form-control" value="<?= e($member['emergency_relation'] ?? '') ?>" data-suggest="emergency_relation">
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" name="save_member" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i><?= $action === 'edit' ? 'Update' : 'Save' ?> Member
                    </button>
                    <a href="members.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <?php elseif ($action === 'view' && $member): ?>
    <!-- Member Profile View -->
    <?php require_once __DIR__ . '/includes/member_workflow.php'; ?>
    <?php $progress = getRegistrationProgress($member['status']); ?>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card text-center">
                <div class="card-body">
                    <div class="user-avatar user-avatar-lg mx-auto mb-3">
                        <?php if (!empty($member['photo'])): ?>
                            <img src="<?= BASE_URL ?>uploads/members/<?= e($member['photo']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                        <?php else: ?>
                            <?= strtoupper(substr($member['first_name'], 0, 1)) . strtoupper(substr($member['last_name'], 0, 1)) ?>
                        <?php endif; ?>
                    </div>
                    <h5><?= e($member['first_name'] . ' ' . $member['last_name']) ?></h5>
                    <p class="text-muted small"><?= e($member['member_no']) ?></p>
                    <?= statusBadge($member['status']) ?>
                    <hr>
                    <div class="text-start small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Phone:</span>
                            <span><?= e($member['phone']) ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Email:</span>
                            <span><?= e($member['email'] ?? '-') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">National ID:</span>
                            <span><?= e($member['national_id'] ?? '-') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Email Verified:</span>
                            <span><?= $member['email_verified'] ? '<span class="text-success"><i class="fas fa-check-circle"></i> Yes</span>' : '<span class="text-warning"><i class="fas fa-clock"></i> No</span>' ?></span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span class="text-muted">Joined:</span>
                            <span><?= formatDate($member['date_joined']) ?></span>
                        </div>
                    </div>
                    <?php if ($member['email_verified_at']): ?>
                        <div class="mt-2 small text-muted">Verified: <?= formatDate($member['email_verified_at']) ?></div>
                    <?php endif; ?>
                    <?php if ($member['approved_at']): ?>
                        <div class="mt-1 small text-muted">Approved: <?= formatDate($member['approved_at']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Approval Actions -->
            <?php if (hasPermission('approve_members') && in_array($member['status'], ['email_verified', 'pending_approval'])): ?>
            <div class="card mt-3 border-primary">
                <div class="card-header bg-primary text-white"><i class="fas fa-clipboard-check me-1"></i> Approval Required</div>
                <div class="card-body">
                    <p class="small text-muted">This member has verified their email and is awaiting your decision.</p>
                    <form method="POST" class="d-grid gap-2">
                        <?= csrfField() ?>
                        <div class="mb-2">
                            <label class="form-label small">Set Login Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control form-control-sm" required minlength="6" placeholder="Min 6 characters">
                        </div>
                        <div class="mb-2">
                            <label class="form-label small">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control form-control-sm" required minlength="6" placeholder="Re-enter password">
                        </div>
                        <button type="submit" name="approve_member" class="btn btn-success">
                            <i class="fas fa-check-circle me-1"></i> Approve & Create User Account
                        </button>
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#rejectModal">
                            <i class="fas fa-times-circle me-1"></i> Reject Registration
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($member['rejection_reason']): ?>
            <div class="card mt-3 border-danger">
                <div class="card-header bg-danger text-white"><i class="fas fa-exclamation-triangle me-1"></i> Rejection Reason</div>
                <div class="card-body">
                    <p class="mb-0"><?= e($member['rejection_reason']) ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Member Details</span>
                    <?php if ($progress['step'] >= 0): ?>
                        <span class="badge bg-<?= $progress['color'] ?>"><i class="fas <?= $progress['icon'] ?> me-1"></i><?= $progress['label'] ?></span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <small class="text-muted d-block">Occupation</small>
                            <span><?= e($member['occupation'] ?? 'N/A') ?></span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Employer</small>
                            <span><?= e($member['employer'] ?? 'N/A') ?></span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Gender</small>
                            <span><?= e(ucfirst($member['gender'] ?? 'N/A')) ?></span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Date of Birth</small>
                            <span><?= $member['date_of_birth'] ? formatDate($member['date_of_birth']) : 'N/A' ?></span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">County</small>
                            <span><?= e($member['county'] ?? 'N/A') ?></span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Sub County</small>
                            <span><?= e($member['sub_county'] ?? 'N/A') ?></span>
                        </div>
                        <div class="col-md-6">
                            <small class="text-muted d-block">Ward</small>
                            <span><?= e($member['ward'] ?? 'N/A') ?></span>
                        </div>
                        <div class="col-12">
                            <small class="text-muted d-block">Address</small>
                            <span><?= e($member['address'] ?? 'N/A') ?></span>
                        </div>
                        <div class="col-12">
                            <small class="text-muted d-block">Emergency Contact</small>
                            <span><?= e($member['emergency_name'] ?? 'N/A') ?> (<?= e($member['emergency_relation'] ?? '') ?>) - <?= e($member['emergency_phone'] ?? '') ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Documents Card -->
            <?php
            $docs = $db->prepare("SELECT * FROM member_documents WHERE member_id = ? ORDER BY document_type");
            $docs->execute([$id]);
            $memberDocs = $docs->fetchAll();
            ?>
            <?php if ($memberDocs): ?>
            <div class="card mt-3">
                <div class="card-header"><i class="fas fa-file me-1"></i> Uploaded Documents</div>
                <div class="card-body">
                    <div class="row g-2">
                        <?php foreach ($memberDocs as $doc):
                        $docUrl = BASE_URL . 'uploads/members/' . e($doc['file_path']);
                        $isPdf = stripos($doc['mime_type'] ?? $doc['file_path'], 'pdf') !== false;
                        ?>
                        <div class="col-md-4">
                            <div class="border rounded p-3 text-center">
                                <?php if ($isPdf): ?>
                                    <i class="fas fa-file-pdf fa-2x text-danger mb-2"></i>
                                <?php else: ?>
                                    <i class="fas fa-file-image fa-2x text-info mb-2"></i>
                                <?php endif; ?>
                                <div><strong><?= e(ucwords(str_replace('_', ' ', $doc['document_type']))) ?></strong></div>
                                <?php if ($doc['is_verified']): ?>
                                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>Verified</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Pending Verification</span>
                                <?php endif; ?>
                                <div class="mt-2 d-flex justify-content-center gap-1">
                                    <?php if ($isPdf): ?>
                                        <a href="<?= $docUrl ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i> View</a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-sm btn-outline-primary preview-doc" data-src="<?= $docUrl ?>" data-title="<?= e(ucwords(str_replace('_', ' ', $doc['document_type']))) ?>">
                                            <i class="fas fa-eye"></i> Preview
                                        </button>
                                        <a href="<?= $docUrl ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fas fa-external-link-alt"></i></a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Document Preview Modal -->
    <div class="modal fade" id="docPreviewModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="docPreviewTitle"><i class="fas fa-file-image me-1"></i> Document Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-0 bg-dark" style="min-height: 300px;">
                    <img id="docPreviewImg" src="" alt="Document Preview" style="max-width: 100%; max-height: 80vh; object-fit: contain; margin: 0 auto; display: none;">
                    <div id="docPreviewLoading" class="text-white p-5"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading...</p></div>
                </div>
                <div class="modal-footer justify-content-between">
                    <small class="text-muted">Review the document and choose an action below</small>
                    <div>
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <a href="#" id="docPreviewDownload" class="btn btn-outline-primary" target="_blank"><i class="fas fa-external-link-alt me-1"></i> Open in Tab</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div class="modal fade" id="rejectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title"><i class="fas fa-times-circle me-1"></i> Reject Registration</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted">This will reject the registration of <strong><?= e($member['first_name'] . ' ' . $member['last_name']) ?></strong>. The applicant will be notified via email.</p>
                        <div class="mb-3">
                            <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea name="rejection_reason" class="form-control" rows="4" required placeholder="Explain why the registration is being rejected..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="reject_member" class="btn btn-danger"><i class="fas fa-times me-1"></i> Confirm Rejection</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="deleteMemberModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Confirm Delete</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <p>Are you sure you want to permanently delete <strong id="deleteMemberName"></strong> (<span id="deleteMemberNo"></span>)?</p>
                <div class="alert alert-danger mb-0">
                    <i class="fas fa-exclamation-circle me-1"></i>This action cannot be undone. All related records will be permanently removed.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" class="d-inline" id="deleteMemberForm">
                    <?= csrfField() ?>
                    <input type="hidden" name="id" id="deleteMemberId">
                    <button type="submit" name="delete_member" class="btn btn-danger"><i class="fas fa-trash me-1"></i>Delete Permanently</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$extraMeta = '<meta id="csrfMeta" content="' . csrfToken() . '">';
$extraScripts = '<script>var LOCATION_DATA = { subCounties: ' . $subCountiesJson . ', wards: ' . $wardsJson . ' };</script>';
$extraScripts .= <<<'EOT'
<script>
function toggleStatus(id, status) {
    if (confirm('Are you sure you want to change this member\'s status?')) {
        const token = document.getElementById('csrfMeta')?.getAttribute('content');
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = '<input name="csrf_token" value="' + token + '">' +
            '<input name="toggle_status" value="1">' +
            '<input name="new_status" value="' + status + '">' +
            '<input name="id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

$(document).on('click', '.preview-doc', function() {
    var src = $(this).data('src');
    var title = $(this).data('title');
    $('#docPreviewTitle').text(title);
    $('#docPreviewImg').hide();
    $('#docPreviewLoading').show();
    $('#docPreviewDownload').attr('href', src);
    var modal = new bootstrap.Modal(document.getElementById('docPreviewModal'));
    modal.show();
    var img = new Image();
    img.onload = function() {
        $('#docPreviewImg').attr('src', src).show();
        $('#docPreviewLoading').hide();
    };
    img.onerror = function() {
        $('#docPreviewLoading').html('<i class="fas fa-exclamation-triangle fa-2x text-warning"></i><p class="mt-2">Failed to load image. <a href="' + src + '" target="_blank" class="text-info">Open directly</a></p>');
    };
    img.src = src;
});

$(document).on('click', '.delete-member-btn', function() {
    var btn = $(this);
    $('#deleteMemberId').val(btn.data('id'));
    $('#deleteMemberName').text(btn.data('name'));
    $('#deleteMemberNo').text(btn.data('member-no'));
    new bootstrap.Modal(document.getElementById('deleteMemberModal')).show();
});

// Cascading location pre-selection for edit mode — waits for cascade to populate options
$(function() {
    var form = $('form[data-validate]');
    if (!form.length) return;
    var countyId = form.data('selected-county');
    var subCountyId = form.data('selected-subcounty');
    var wardId = form.data('selected-ward');
    if (!countyId) return;
    var $county = form.find('select[name="county_id"]');
    var $sub = form.find('select[name="sub_county_id"]');
    var $ward = form.find('select[name="ward_id"]');
    $county.val(countyId).trigger('change');
    if (subCountyId) {
        var subTries = 0;
        var subInterval = setInterval(function() {
            subTries++;
            if ($sub.find('option[value="' + subCountyId + '"]').length) {
                clearInterval(subInterval);
                $sub.val(subCountyId).trigger('change');
                if (wardId) {
                    var wardTries = 0;
                    var wardInterval = setInterval(function() {
                        wardTries++;
                        if ($ward.find('option[value="' + wardId + '"]').length) {
                            clearInterval(wardInterval);
                            $ward.val(wardId).trigger('change');
                        } else if (wardTries > 100) {
                            clearInterval(wardInterval);
                        }
                    }, 100);
                }
            } else if (subTries > 100) {
                clearInterval(subInterval);
            }
        }, 100);
    }
});
</script>
EOT;

include __DIR__ . '/views/layouts/footer.php';
?>
