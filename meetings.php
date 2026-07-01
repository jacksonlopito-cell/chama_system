<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();
requirePermission('view_meetings');

$db = getConnection();
$currentUser = getCurrentUser();
$pageTitle = 'Meetings';
$action = $_GET['action'] ?? 'list';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['save_meeting'])) {
        $isEdit = $id > 0;
        requirePermission($isEdit ? 'edit_meetings' : 'create_meetings');
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $meetingDate = $_POST['meeting_date'] ?? '';
        $startTime = $_POST['start_time'] ?? null;
        $endTime = $_POST['end_time'] ?? null;
        $venue = trim($_POST['venue'] ?? '');
        $type = $_POST['type'] ?? 'regular';
        $status = $_POST['status'] ?? 'scheduled';

        $errors = [];
        if (empty($title)) $errors[] = 'Title is required';
        if (empty($meetingDate)) $errors[] = 'Meeting date is required';

        if (empty($errors)) {
            try {
                $db->beginTransaction();

                if ($id) {
                    $stmt = $db->prepare("UPDATE meetings SET title=?, description=?, meeting_date=?, start_time=?, end_time=?, venue=?, type=?, status=? WHERE id=? AND group_code=?");
                    $stmt->execute([$title, $description, $meetingDate, $startTime, $endTime, $venue, $type, $status, $id, $_SESSION['group_code']]);

                    $db->prepare("DELETE FROM meeting_agenda WHERE meeting_id = ?")->execute([$id]);

                    logAudit($_SESSION['user_id'], $_SESSION['username'], 'update', 'meetings', $id, null, ['title' => $title], 'Updated meeting');
                } else {
                    $stmt = $db->prepare("INSERT INTO meetings (title, description, meeting_date, start_time, end_time, venue, type, status, group_code, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$title, $description, $meetingDate, $startTime, $endTime, $venue, $type, $status, $_SESSION['group_code'], $_SESSION['user_id']]);
                    $id = $db->lastInsertId();

                    logAudit($_SESSION['user_id'], $_SESSION['username'], 'create', 'meetings', $id, null, ['title' => $title], 'Created new meeting');
                }

                $titles = $_POST['agenda_title'] ?? [];
                $descriptions = $_POST['agenda_description'] ?? [];
                $orders = $_POST['agenda_order'] ?? [];

                if (!empty($titles)) {
                    $stmt = $db->prepare("INSERT INTO meeting_agenda (meeting_id, title, description, order_no) VALUES (?, ?, ?, ?)");
                    foreach ($titles as $i => $agendaTitle) {
                        $agendaTitle = trim($agendaTitle);
                        if (!empty($agendaTitle)) {
                            $stmt->execute([$id, $agendaTitle, trim($descriptions[$i] ?? ''), (int)($orders[$i] ?? $i + 1)]);
                        }
                    }
                }

                $db->commit();
                setFlash('success', 'Meeting saved successfully');
                redirect('meetings.php?action=view&id=' . $id);
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }

        if (!empty($errors)) {
            setFlash('danger', implode('<br>', $errors));
        }
    }

    if (isset($_POST['save_attendance'])) {
        $memberIds = $_POST['member_id'] ?? [];
        $statuses = $_POST['attendance_status'] ?? [];

        try {
            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO attendance (meeting_id, member_id, status, check_in_time, notes) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status=VALUES(status), check_in_time=VALUES(check_in_time), notes=VALUES(notes)");

            foreach ($memberIds as $index => $memberId) {
                $memberId = (int)$memberId;
                $attStatus = $statuses[$index] ?? 'present';
                $checkIn = ($attStatus === 'present' || $attStatus === 'late') ? date('H:i:s') : null;
                $stmt->execute([$id, $memberId, $attStatus, $checkIn, null]);

                logActivity($_SESSION['user_id'], 'attendance', 'Recorded attendance for meeting #' . $id . ' - member #' . $memberId);
            }

            $db->commit();
            setFlash('success', 'Attendance saved successfully');
            redirect('meetings.php?action=view&id=' . $id);
        } catch (Exception $e) {
            $db->rollBack();
            setFlash('danger', 'Error saving attendance: ' . $e->getMessage());
        }
    }

    if (isset($_POST['save_minutes'])) {
        $minutesContent = trim($_POST['minutes_content'] ?? '');
        $minutesDecision = trim($_POST['minutes_decision'] ?? '');
        $minutesActions = trim($_POST['minutes_actions'] ?? '');
        $agendaId = !empty($_POST['agenda_id']) ? (int)$_POST['agenda_id'] : null;

        if (empty($minutesContent)) {
            setFlash('danger', 'Minutes content is required');
        } else {
            try {
                $stmt = $db->prepare("INSERT INTO meeting_minutes (meeting_id, agenda_id, content, decision, action_items, recorded_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$id, $agendaId, $minutesContent, $minutesDecision, $minutesActions, $_SESSION['user_id']]);

                $db->prepare("UPDATE meetings SET status = 'completed' WHERE id = ? AND status = 'ongoing' AND group_code = ?")->execute([$id, $_SESSION['group_code']]);

                logAudit($_SESSION['user_id'], $_SESSION['username'], 'create', 'meeting_minutes', $id, null, ['meeting_id' => $id], 'Recorded meeting minutes');
                setFlash('success', 'Minutes saved successfully');
                redirect('meetings.php?action=view&id=' . $id);
            } catch (Exception $e) {
                setFlash('danger', 'Error saving minutes: ' . $e->getMessage());
            }
        }
    }

    if (isset($_POST['update_status'])) {
        $newStatus = $_POST['new_status'] ?? '';
        $allowed = ['scheduled', 'ongoing', 'completed', 'cancelled'];
        if (in_array($newStatus, $allowed)) {
            $db->prepare("UPDATE meetings SET status = ? WHERE id = ? AND group_code = ?")->execute([$newStatus, $id, $_SESSION['group_code']]);
            logAudit($_SESSION['user_id'], $_SESSION['username'], 'update', 'meetings', $id, null, ['status' => $newStatus], 'Changed meeting status');
            setFlash('success', 'Meeting status updated to ' . ucfirst($newStatus));
            redirect('meetings.php?action=view&id=' . $id);
        }
    }
}

$meeting = null;
$agendaItems = [];
$attendanceRecords = [];
$minutesRecords = [];
$activeMembers = [];

if ($id && ($action === 'view' || $action === 'edit')) {
    $stmt = $db->prepare("SELECT * FROM meetings WHERE id = ? AND group_code = ?");
    $stmt->execute([$id, $_SESSION['group_code']]);
    $meeting = $stmt->fetch();

    if (!$meeting) {
        setFlash('danger', 'Meeting not found');
        redirect('meetings.php');
    }

    $stmt = $db->prepare("SELECT * FROM meeting_agenda WHERE meeting_id = ? ORDER BY order_no ASC");
    $stmt->execute([$id]);
    $agendaItems = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT a.*, CONCAT(m.first_name, ' ', m.last_name) as member_name, m.member_no, m.photo
        FROM attendance a
        JOIN members m ON a.member_id = m.id
        WHERE a.meeting_id = ?
        ORDER BY m.first_name ASC
    ");
    $stmt->execute([$id]);
    $attendanceRecords = $stmt->fetchAll();

    $stmt = $db->prepare("
        SELECT mm.*, ma.title as agenda_title, u.username as recorder
        FROM meeting_minutes mm
        LEFT JOIN meeting_agenda ma ON mm.agenda_id = ma.id
        LEFT JOIN users u ON mm.recorded_by = u.id
        WHERE mm.meeting_id = ?
        ORDER BY mm.created_at DESC
    ");
    $stmt->execute([$id]);
    $minutesRecords = $stmt->fetchAll();

    $stmt = $db->prepare("SELECT id, first_name, last_name, member_no, photo FROM members WHERE status = 'active' AND group_code = ? ORDER BY first_name ASC");
    $stmt->execute([$_SESSION['group_code']]);
    $activeMembers = $stmt->fetchAll();
}

$page = max(1, (int)($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';

$where = "WHERE group_code = ?";
$params = [$_SESSION['group_code']];

if ($search) {
    $where .= " AND (title LIKE ? OR venue LIKE ?)";
    $searchTerm = "%$search%";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
}
if ($statusFilter) {
    $where .= " AND status = ?";
    $params[] = $statusFilter;
}

$totalMeetings = $db->prepare("SELECT COUNT(*) FROM meetings $where");
$totalMeetings->execute($params);
$totalRecords = $totalMeetings->fetchColumn();

$pagination = paginate($totalRecords, $page);

$meetings = $db->prepare("SELECT * FROM meetings $where ORDER BY meeting_date DESC LIMIT ? OFFSET ?");
$meetings->execute(array_merge($params, [$pagination['perPage'], $pagination['offset']]));
$meetingsList = $meetings->fetchAll();

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>

<div class="main-content">
    <div class="page-header">
        <div>
            <h4>
                <?php if ($action === 'create' || $action === 'edit'): ?>
                    <?= $action === 'edit' ? 'Edit Meeting' : 'Schedule Meeting' ?>
                <?php elseif ($action === 'view'): ?>Meeting Details
                <?php else: ?>Meetings
                <?php endif; ?>
            </h4>
            <p>
                <?php if ($action === 'list'): ?>Manage all meetings and agendas
                <?php elseif ($action === 'view' && $meeting): ?><?= e($meeting['title']) ?>
                <?php else: ?>Schedule a new meeting
                <?php endif; ?>
            </p>
        </div>
        <?php if ($action === 'list'): ?>
        <div class="d-flex gap-2">
            <?php if (hasPermission('create_meetings')): ?>
            <a href="meetings.php?action=create" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Schedule Meeting
            </a>
            <?php endif; ?>
        </div>
        <?php elseif ($action === 'view' && $meeting): ?>
        <div class="d-flex gap-2">
            <a href="meetings.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-1"></i>Back
            </a>
            <?php if (hasPermission('edit_meetings')): ?>
                <a href="meetings.php?action=edit&id=<?= $id ?>" class="btn btn-outline-primary">
                    <i class="fas fa-edit me-1"></i>Edit
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php displayFlash(); ?>

    <?php if ($action === 'list'): ?>
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by title or venue..." value="<?= e($search) ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="scheduled" <?= $statusFilter === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                        <option value="ongoing" <?= $statusFilter === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-1"></i>Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="meetings.php" class="btn btn-outline-secondary w-100"><i class="fas fa-redo me-1"></i>Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover datatable mb-0" data-table-name="meetings">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Venue</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th class="no-sort">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                            <?php foreach ($meetingsList as $m): ?>
                                <tr>
                                    <td>
                                        <a href="meetings.php?action=view&id=<?= $m['id'] ?>" class="text-decoration-none" style="font-weight: 500;">
                                            <?= e($m['title']) ?>
                                        </a>
                                    </td>
                                    <td style="font-size: 0.8125rem;"><?= formatDate($m['meeting_date']) ?></td>
                                    <td style="font-size: 0.8125rem;">
                                        <?php if ($m['start_time']): ?>
                                            <?= date('H:i', strtotime($m['start_time'])) ?>
                                            <?php if ($m['end_time']): ?> - <?= date('H:i', strtotime($m['end_time'])) ?><?php endif; ?>
                                        <?php else: ?>-<?php endif; ?>
                                    </td>
                                    <td style="font-size: 0.8125rem;"><?= e($m['venue'] ?? '-') ?></td>
                                    <td><span class="badge bg-<?= $m['type'] === 'emergency' ? 'danger' : ($m['type'] === 'annual' ? 'warning text-dark' : ($m['type'] === 'special' ? 'info text-dark' : 'secondary')) ?>"><?= e(ucfirst($m['type'])) ?></span></td>
                                    <td><?= statusBadge($m['status']) ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="meetings.php?action=view&id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary btn-icon" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (hasPermission('edit_meetings')): ?>
                                                <a href="meetings.php?action=edit&id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-warning btn-icon" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
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
                <?= renderPagination($pagination, 'meetings.php?' . http_build_query(array_filter(['search' => $search, 'status' => $statusFilter])) . '&') ?>
            </div>
        <?php endif; ?>
    </div>

    <?php elseif ($action === 'create' || $action === 'edit'): ?>
    <div class="card">
        <div class="card-body">
            <form action="meetings.php<?= $action === 'edit' ? "?action=edit&id=$id" : '?action=create' ?>" method="POST" data-validate>
                <?= csrfField() ?>

                <div class="row g-3">
                    <div class="col-12"><h6 class="border-bottom pb-2">Meeting Information</h6></div>

                    <div class="col-md-8">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control" value="<?= e($meeting['title'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Type <span class="text-danger">*</span></label>
                        <select name="type" class="form-select">
                            <option value="regular" <?= ($meeting['type'] ?? '') === 'regular' ? 'selected' : '' ?>>Regular</option>
                            <option value="special" <?= ($meeting['type'] ?? '') === 'special' ? 'selected' : '' ?>>Special</option>
                            <option value="annual" <?= ($meeting['type'] ?? '') === 'annual' ? 'selected' : '' ?>>Annual</option>
                            <option value="emergency" <?= ($meeting['type'] ?? '') === 'emergency' ? 'selected' : '' ?>>Emergency</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Meeting Date <span class="text-danger">*</span></label>
                        <input type="date" name="meeting_date" class="form-control" value="<?= e($meeting['meeting_date'] ?? date('Y-m-d')) ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Start Time</label>
                        <input type="time" name="start_time" class="form-control" value="<?= e($meeting['start_time'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">End Time</label>
                        <input type="time" name="end_time" class="form-control" value="<?= e($meeting['end_time'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Venue</label>
                        <input type="text" name="venue" class="form-control" value="<?= e($meeting['venue'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="scheduled" <?= ($meeting['status'] ?? '') === 'scheduled' ? 'selected' : '' ?>>Scheduled</option>
                            <option value="ongoing" <?= ($meeting['status'] ?? '') === 'ongoing' ? 'selected' : '' ?>>Ongoing</option>
                            <option value="completed" <?= ($meeting['status'] ?? '') === 'completed' ? 'selected' : '' ?>>Completed</option>
                            <option value="cancelled" <?= ($meeting['status'] ?? '') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= e($meeting['description'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12"><h6 class="border-bottom pb-2">Agenda Items</h6></div>

                    <div class="col-12">
                        <div id="agendaContainer">
                            <?php if (!empty($agendaItems)): ?>
                                <?php foreach ($agendaItems as $i => $item): ?>
                                <div class="agenda-row row g-2 mb-2 align-items-end">
                                    <div class="col-md-1">
                                        <label class="form-label">Order</label>
                                        <input type="number" name="agenda_order[]" class="form-control" value="<?= $item['order_no'] ?>" min="1">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Title</label>
                                        <input type="text" name="agenda_title[]" class="form-control" value="<?= e($item['title']) ?>">
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">Description</label>
                                        <input type="text" name="agenda_description[]" class="form-control" value="<?= e($item['description'] ?? '') ?>">
                                    </div>
                                    <div class="col-md-2">
                                        <button type="button" class="btn btn-outline-danger btn-sm remove-agenda"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <div class="agenda-row row g-2 mb-2 align-items-end">
                                <div class="col-md-1">
                                    <label class="form-label">Order</label>
                                    <input type="number" name="agenda_order[]" class="form-control" value="1" min="1">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Title</label>
                                    <input type="text" name="agenda_title[]" class="form-control" placeholder="Agenda title">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Description</label>
                                    <input type="text" name="agenda_description[]" class="form-control" placeholder="Brief description">
                                </div>
                                <div class="col-md-2">
                                    <button type="button" class="btn btn-outline-danger btn-sm remove-agenda"><i class="fas fa-times"></i></button>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        <button type="button" id="addAgendaRow" class="btn btn-outline-primary btn-sm mt-1">
                            <i class="fas fa-plus me-1"></i>Add Agenda Item
                        </button>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" name="save_meeting" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i><?= $action === 'edit' ? 'Update' : 'Save' ?> Meeting
                    </button>
                    <a href="meetings.php" class="btn btn-outline-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>

    <?php elseif ($action === 'view' && $meeting): ?>
    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class="fas fa-calendar-check" style="font-size: 3rem; color: var(--secondary);"></i>
                    </div>
                    <h5><?= e($meeting['title']) ?></h5>
                    <?= statusBadge($meeting['status']) ?>
                    <hr>
                    <div class="text-start small">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Date:</span>
                            <span><?= formatDate($meeting['meeting_date']) ?></span>
                        </div>
                        <?php if ($meeting['start_time']): ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Time:</span>
                            <span><?= date('H:i', strtotime($meeting['start_time'])) ?><?php if ($meeting['end_time']): ?> - <?= date('H:i', strtotime($meeting['end_time'])) ?><?php endif; ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Venue:</span>
                            <span><?= e($meeting['venue'] ?? '-') ?></span>
                        </div>
                        <div class="d-flex justify-content-between mb-1">
                            <span class="text-muted">Type:</span>
                            <span><?= e(ucfirst($meeting['type'])) ?></span>
                        </div>
                    </div>

                    <?php if (hasPermission('edit_meetings')): ?>
                    <hr>
                    <form method="POST" class="d-flex gap-2 justify-content-center">
                        <?= csrfField() ?>
                        <?php if ($meeting['status'] === 'scheduled'): ?>
                            <button type="submit" name="update_status" value="1" class="btn btn-sm btn-success" onclick="return confirm('Start this meeting?')">
                                <i class="fas fa-play me-1"></i>Start Meeting
                            </button>
                        <?php endif; ?>
                        <?php if ($meeting['status'] === 'ongoing'): ?>
                            <button type="submit" name="update_status" value="1" class="btn btn-sm btn-primary" onclick="return confirm('Mark meeting as completed?')">
                                <i class="fas fa-check me-1"></i>Mark Completed
                            </button>
                        <?php endif; ?>
                        <?php if ($meeting['status'] !== 'cancelled' && $meeting['status'] !== 'completed'): ?>
                            <button type="submit" name="update_status" value="1" class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancel this meeting?')">
                                <i class="fas fa-ban me-1"></i>Cancel
                            </button>
                        <?php endif; ?>
                        <input type="hidden" name="new_status" value="<?= $meeting['status'] === 'scheduled' ? 'ongoing' : ($meeting['status'] === 'ongoing' ? 'completed' : 'cancelled') ?>">
                    </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($meeting['description']): ?>
            <div class="card mt-3">
                <div class="card-header">Description</div>
                <div class="card-body">
                    <p class="mb-0 small"><?= e($meeting['description']) ?></p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-list me-2" style="color: var(--secondary);"></i>Agenda</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($agendaItems)): ?>
                        <div class="text-center py-4" style="color: var(--gray-500);">
                            <i class="fas fa-clipboard-list mb-2" style="font-size: 2rem;"></i>
                            <p class="small mb-0">No agenda items</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($agendaItems as $item): ?>
                                <div class="list-group-item px-3 py-3">
                                    <div class="d-flex">
                                        <div class="me-3">
                                            <span class="badge bg-primary rounded-pill"><?= (int)$item['order_no'] ?></span>
                                        </div>
                                        <div>
                                            <div style="font-weight: 500;"><?= e($item['title']) ?></div>
                                            <?php if ($item['description']): ?>
                                                <small style="color: var(--gray-500);"><?= e($item['description']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-user-check me-2" style="color: var(--secondary);"></i>Attendance</span>
                    <?php if ($meeting['status'] !== 'cancelled'): ?>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#attendanceModal">
                        <i class="fas fa-check-double me-1"></i>Record Attendance
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($attendanceRecords)): ?>
                        <div class="text-center py-4" style="color: var(--gray-500);">
                            <i class="fas fa-users-slash mb-2" style="font-size: 2rem;"></i>
                            <p class="small mb-0">No attendance recorded yet</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Status</th>
                                        <th>Check In</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendanceRecords as $att): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar user-avatar-sm me-2">
                                                        <?php if (!empty($att['photo'])): ?>
                                                            <img src="<?= BASE_URL ?>uploads/members/<?= e($att['photo']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                                        <?php else: ?>
                                                            <?= strtoupper(substr($att['member_name'], 0, 1)) ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <div style="font-size: 0.8125rem;"><?= e($att['member_name']) ?></div>
                                                        <small style="color: var(--gray-500);"><?= e($att['member_no']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= statusBadge($att['status']) ?></td>
                                            <td style="font-size: 0.8125rem;"><?= $att['check_in_time'] ? date('H:i', strtotime($att['check_in_time'])) : '-' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-file-alt me-2" style="color: var(--secondary);"></i>Minutes</span>
                    <?php if ($meeting['status'] !== 'cancelled'): ?>
                    <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#minutesModal">
                        <i class="fas fa-plus me-1"></i>Add Minutes
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($minutesRecords)): ?>
                        <div class="text-center py-4" style="color: var(--gray-500);">
                            <i class="fas fa-file-alt mb-2" style="font-size: 2rem;"></i>
                            <p class="small mb-0">No minutes recorded yet</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($minutesRecords as $min): ?>
                                <div class="list-group-item px-3 py-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <strong style="font-size: 0.875rem;">
                                            <?= $min['agenda_title'] ? e($min['agenda_title']) : 'General Minutes' ?>
                                        </strong>
                                        <small style="color: var(--gray-500);">by <?= e($min['recorder'] ?? 'Unknown') ?> - <?= formatDateTime($min['created_at']) ?></small>
                                    </div>
                                    <p class="mb-1 small"><?= nl2br(e($min['content'])) ?></p>
                                    <?php if ($min['decision']): ?>
                                        <div class="mt-2 p-2 bg-light rounded">
                                            <strong class="small">Decision:</strong>
                                            <span class="small"><?= nl2br(e($min['decision'])) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($min['action_items']): ?>
                                        <div class="mt-1">
                                            <strong class="small">Action Items:</strong>
                                            <span class="small"><?= nl2br(e($min['action_items'])) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Modal -->
    <div class="modal fade" id="attendanceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="modal-header">
                        <h5 class="modal-title">Record Attendance</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($activeMembers)): ?>
                                        <tr><td colspan="2" class="text-center py-3" style="color: var(--gray-500);">No active members</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($activeMembers as $member): ?>
                                            <?php
                                            $att = array_filter($attendanceRecords, fn($a) => (int)$a['member_id'] === (int)$member['id']);
                                            $att = $att ? reset($att) : null;
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="user-avatar user-avatar-sm me-2">
                                                            <?php if (!empty($member['photo'])): ?>
                                                                <img src="<?= BASE_URL ?>uploads/members/<?= e($member['photo']) ?>" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                                            <?php else: ?>
                                                                <?= strtoupper(substr($member['first_name'], 0, 1)) . strtoupper(substr($member['last_name'], 0, 1)) ?>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div>
                                                            <div style="font-size: 0.8125rem;"><?= e($member['first_name'] . ' ' . $member['last_name']) ?></div>
                                                            <small style="color: var(--gray-500);"><?= e($member['member_no']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="hidden" name="member_id[]" value="<?= $member['id'] ?>">
                                                    <select name="attendance_status[]" class="form-select form-select-sm">
                                                        <option value="present" <?= $att && $att['status'] === 'present' ? 'selected' : '' ?>>Present</option>
                                                        <option value="absent" <?= $att && $att['status'] === 'absent' ? 'selected' : '' ?>>Absent</option>
                                                        <option value="excused" <?= $att && $att['status'] === 'excused' ? 'selected' : '' ?>>Excused</option>
                                                        <option value="late" <?= $att && $att['status'] === 'late' ? 'selected' : '' ?>>Late</option>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="save_attendance" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save Attendance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Minutes Modal -->
    <div class="modal fade" id="minutesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <?= csrfField() ?>
                    <div class="modal-header">
                        <h5 class="modal-title">Record Minutes</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Related Agenda Item</label>
                            <select name="agenda_id" class="form-select">
                                <option value="">General Minutes</option>
                                <?php foreach ($agendaItems as $item): ?>
                                    <option value="<?= $item['id'] ?>"><?= e($item['order_no']) ?>. <?= e($item['title']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Minutes Content <span class="text-danger">*</span></label>
                            <textarea name="minutes_content" class="form-control" rows="6" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Decision / Resolution</label>
                            <textarea name="minutes_decision" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Action Items</label>
                            <textarea name="minutes_actions" class="form-control" rows="3" placeholder="What needs to be done and by whom?"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="save_minutes" class="btn btn-primary">
                            <i class="fas fa-save me-1"></i>Save Minutes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
$extraScripts = <<<EOT
<script>
$(document).ready(function() {
    $('#addAgendaRow').on('click', function() {
        const container = $('#agendaContainer');
        const count = container.children('.agenda-row').length + 1;
        const row = `
            <div class="agenda-row row g-2 mb-2 align-items-end">
                <div class="col-md-1">
                    <label class="form-label">Order</label>
                    <input type="number" name="agenda_order[]" class="form-control" value="\${count}" min="1">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Title</label>
                    <input type="text" name="agenda_title[]" class="form-control" placeholder="Agenda title">
                </div>
                <div class="col-md-5">
                    <label class="form-label">Description</label>
                    <input type="text" name="agenda_description[]" class="form-control" placeholder="Brief description">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-outline-danger btn-sm remove-agenda"><i class="fas fa-times"></i></button>
                </div>
            </div>`;
        container.append(row);
    });

    $(document).on('click', '.remove-agenda', function() {
        if ($('.agenda-row').length > 1) {
            $(this).closest('.agenda-row').remove();
        }
    });
});
</script>
EOT;

include __DIR__ . '/views/layouts/footer.php';
?>
