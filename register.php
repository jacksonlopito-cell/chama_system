<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/member_workflow.php';

$pageTitle = 'Member Registration';
$regSettings = getRegistrationSettings();

// Redirect if registration is disabled
if (($regSettings['allow_public_registration'] ?? '1') !== '1') {
    setFlash('warning', 'Public registration is currently disabled.');
    redirect('login.php');
}

$errors = [];
$formData = [];
$db = getConnection();

// Load lookup data
$counties = $db->query("SELECT id, name FROM counties ORDER BY name")->fetchAll();
$subCounties = [];
$wards = [];

// If a county is selected (from validation re-display), load cascading
if (!empty($_POST['county_id'])) {
    $s = $db->prepare("SELECT id, name FROM sub_counties WHERE county_id = ? ORDER BY name");
    $s->execute([(int)$_POST['county_id']]);
    $subCounties = $s->fetchAll();
}
if (!empty($_POST['sub_county_id'])) {
    $w = $db->prepare("SELECT id, name FROM wards WHERE sub_county_id = ? ORDER BY name");
    $w->execute([(int)$_POST['sub_county_id']]);
    $wards = $w->fetchAll();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = $_POST;

    // Validate
    $errors = validateRegistrationData($_POST, $_FILES);

    if (empty($errors)) {
        // Upload documents
        $uploadedFiles = [];
        $docFields = [
            'national_id_doc' => 'national_id',
            'passport_photo' => 'passport_photo',
            'signature' => 'signature',
        ];
        $uploadOk = true;

        foreach ($docFields as $field => $docType) {
            if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
                $allowedExt = $docType === 'signature' ? ['jpg','jpeg','png'] : ['jpg','jpeg','png','pdf'];
                $result = uploadFile($_FILES[$field], __DIR__ . '/uploads/members', $allowedExt);
                if ($result) {
                    $uploadedFiles[$docType] = $result;
                } else {
                    $errors[$field] = 'Failed to upload file. Allowed: ' . implode(', ', $allowedExt) . ' (max 5MB)';
                    $uploadOk = false;
                }
            }
        }

        if ($uploadOk) {
            try {
                $db->beginTransaction();
                $memberId = createMemberFromRegistration($formData, $uploadedFiles);
                $db->commit();

                // Send verification email
                $emailResult = sendVerificationEmail($memberId, strtolower(trim($formData['email'])), $formData['first_name']);

                if ($emailResult['success']) {
                    setFlash('success', 'Registration successful! Please check your email to verify your account.');
                    redirect('register.php?step=done&email=' . urlencode($formData['email']));
                } else {
                    // Email failed but registration saved
                    setFlash('warning', 'Registration saved but verification email could not be sent. Contact administrator to verify your email. Error: ' . $emailResult['error']);
                    redirect('register.php?step=done&email=' . urlencode($formData['email']) . '&email_failed=1');
                }
            } catch (Exception $e) {
                $db->rollBack();
                $errors['general'] = 'An error occurred while processing your registration. Please try again.';
                error_log("Registration error: " . $e->getMessage());
            }
        }
    }
}

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>

<style>
.reg-step { display: none; }
.reg-step.active { display: block; }
.reg-progress { display: flex; justify-content: center; gap: 0; margin-bottom: 2rem; padding: 0; list-style: none; }
.reg-progress li { flex: 1; text-align: center; padding: 10px; background: #f8f9fa; border: 1px solid #dee2e6; font-size: 0.85rem; color: #6c757d; position: relative; }
.reg-progress li:not(:last-child)::after { content: '\f054'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: -8px; top: 50%; transform: translateY(-50%); color: #dee2e6; z-index: 1; }
.reg-progress li.active { background: #0d6efd; color: white; border-color: #0d6efd; }
.reg-progress li.active::after { color: #0d6efd; }
.reg-progress li.completed { background: #198754; color: white; border-color: #198754; }
.reg-progress li.completed::after { color: #198754; }
.reg-progress li .step-icon { display: block; font-size: 1.2rem; margin-bottom: 4px; }
.reg-progress li .step-label { font-size: 0.75rem; }
@media (max-width: 768px) { .reg-progress li .step-label { display: none; } }
</style>

<div class="main-content">
    <?php if (isset($_GET['step']) && $_GET['step'] === 'done'): ?>
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <div class="card text-center p-5">
                    <div style="font-size: 4rem; color: <?= empty($_GET['email_failed']) ? '#198754' : '#ffc107' ?>; margin-bottom: 1rem;">
                        <i class="fas <?= empty($_GET['email_failed']) ? 'fa-check-circle' : 'fa-exclamation-triangle' ?>"></i>
                    </div>
                    <h3><?= empty($_GET['email_failed']) ? 'Registration Successful!' : 'Registration Saved' ?></h3>
                    <p class="text-muted">
                        <?php if (empty($_GET['email_failed'])): ?>
                            We've sent a verification email to <strong><?= e($_GET['email'] ?? '') ?></strong>.
                            Please check your inbox and click the verification link to activate your account.
                        <?php else: ?>
                            Your registration has been saved but the verification email could not be delivered.
                            Please contact the administrator to verify your email address manually.
                        <?php endif; ?>
                    </p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Didn't receive the email? Check your spam folder or
                        <a href="#" id="resendLink" data-email="<?= e($_GET['email'] ?? '') ?>">click here to resend</a>.
                    </div>
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                </div>
            </div>
        </div>
    <?php else: ?>
    <div class="page-header">
        <h4>Member Registration</h4>
        <p>Complete all steps to register as a member</p>
    </div>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="regForm" data-validate>
        <?= csrfField() ?>

        <!-- Progress Bar -->
        <ul class="reg-progress" id="regProgress">
            <li class="active" data-step="1"><span class="step-icon"><i class="fas fa-user"></i></span><span class="step-label">Personal</span></li>
            <li data-step="2"><span class="step-icon"><i class="fas fa-address-card"></i></span><span class="step-label">Contact & Location</span></li>
            <li data-step="3"><span class="step-icon"><i class="fas fa-briefcase"></i></span><span class="step-label">Professional</span></li>
            <li data-step="4"><span class="step-icon"><i class="fas fa-phone-alt"></i></span><span class="step-label">Emergency</span></li>
            <li data-step="5"><span class="step-icon"><i class="fas fa-file-upload"></i></span><span class="step-label">Documents</span></li>
            <li data-step="6"><span class="step-icon"><i class="fas fa-check"></i></span><span class="step-label">Review & Submit</span></li>
        </ul>

        <!-- Step 1: Personal Info -->
        <div class="reg-step active" data-step="1">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-user me-2"></i>Personal Information</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>" value="<?= e($formData['first_name'] ?? '') ?>" required maxlength="100">
                            <?php if (isset($errors['first_name'])): ?><div class="invalid-feedback"><?= e($errors['first_name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>" value="<?= e($formData['last_name'] ?? '') ?>" required maxlength="100">
                            <?php if (isset($errors['last_name'])): ?><div class="invalid-feedback"><?= e($errors['last_name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gender <span class="text-danger">*</span></label>
                            <select name="gender" class="form-select <?= isset($errors['gender']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Select Gender</option>
                                <option value="male" <?= ($formData['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= ($formData['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                                <option value="other" <?= ($formData['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                            </select>
                            <?php if (isset($errors['gender'])): ?><div class="invalid-feedback"><?= e($errors['gender']) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                            <input type="date" name="date_of_birth" class="form-control <?= isset($errors['date_of_birth']) ? 'is-invalid' : '' ?>" value="<?= e($formData['date_of_birth'] ?? '') ?>" required>
                            <?php if (isset($errors['date_of_birth'])): ?><div class="invalid-feedback"><?= e($errors['date_of_birth']) ?></div><?php endif; ?>
                            <small class="text-muted">You must be at least <?= (int)($regSettings['min_registration_age'] ?? 18) ?> years old.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">National ID Number <?= $regSettings['require_national_id'] === '1' ? '<span class="text-danger">*</span>' : '' ?></label>
                            <input type="text" name="national_id" class="form-control <?= isset($errors['national_id']) ? 'is-invalid' : '' ?>" value="<?= e($formData['national_id'] ?? '') ?>" <?= $regSettings['require_national_id'] === '1' ? 'required' : '' ?> maxlength="20" placeholder="e.g. 12345678">
                            <?php if (isset($errors['national_id'])): ?><div class="invalid-feedback"><?= e($errors['national_id']) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Passport Number</label>
                            <input type="text" name="passport" class="form-control <?= isset($errors['passport']) ? 'is-invalid' : '' ?>" value="<?= e($formData['passport'] ?? '') ?>" maxlength="50">
                            <?php if (isset($errors['passport'])): ?><div class="invalid-feedback"><?= e($errors['passport']) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-end">
                    <button type="button" class="btn btn-primary next-step">Continue <i class="fas fa-arrow-right ms-1"></i></button>
                </div>
            </div>
        </div>

        <!-- Step 2: Contact & Location -->
        <div class="reg-step" data-step="2">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-address-card me-2"></i>Contact & Location</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" value="<?= e($formData['email'] ?? '') ?>" required maxlength="255">
                            <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" value="<?= e($formData['phone'] ?? '') ?>" required placeholder="e.g. 0712 345 678" maxlength="20">
                            <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= e($errors['phone']) ?></div><?php endif; ?>
                            <small class="text-muted">Kenyan format: 0712 345 678 or +254 712 345 678</small>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">County <span class="text-danger">*</span></label>
                            <select name="county_id" id="county_id" class="form-select searchable-select <?= isset($errors['county_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Select County</option>
                                <?php foreach ($counties as $c): ?>
                                    <option value="<?= $c['id'] ?>" <?= ($formData['county_id'] ?? '') == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['county_id'])): ?><div class="invalid-feedback"><?= e($errors['county_id']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Sub County <span class="text-danger">*</span></label>
                            <select name="sub_county_id" id="sub_county_id" class="form-select searchable-select <?= isset($errors['sub_county_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Select Sub County</option>
                                <?php foreach ($subCounties as $sc): ?>
                                    <option value="<?= $sc['id'] ?>" <?= ($formData['sub_county_id'] ?? '') == $sc['id'] ? 'selected' : '' ?>><?= e($sc['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['sub_county_id'])): ?><div class="invalid-feedback"><?= e($errors['sub_county_id']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Ward <span class="text-danger">*</span></label>
                            <select name="ward_id" id="ward_id" class="form-select searchable-select <?= isset($errors['ward_id']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Select Ward</option>
                                <?php foreach ($wards as $w): ?>
                                    <option value="<?= $w['id'] ?>" <?= ($formData['ward_id'] ?? '') == $w['id'] ? 'selected' : '' ?>><?= e($w['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['ward_id'])): ?><div class="invalid-feedback"><?= e($errors['ward_id']) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Residential Address</label>
                        <textarea name="address" class="form-control" rows="2" maxlength="500"><?= e($formData['address'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary prev-step"><i class="fas fa-arrow-left me-1"></i> Back</button>
                    <button type="button" class="btn btn-primary next-step">Continue <i class="fas fa-arrow-right ms-1"></i></button>
                </div>
            </div>
        </div>

        <!-- Step 3: Professional -->
        <div class="reg-step" data-step="3">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-briefcase me-2"></i>Professional Information</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Occupation <span class="text-danger">*</span></label>
                            <input type="text" name="occupation" class="form-control <?= isset($errors['occupation']) ? 'is-invalid' : '' ?>" value="<?= e($formData['occupation'] ?? '') ?>" required maxlength="100" list="suggest-occupation" data-suggest="occupation">
                            <datalist id="suggest-occupation"></datalist>
                            <?php if (isset($errors['occupation'])): ?><div class="invalid-feedback"><?= e($errors['occupation']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Employer</label>
                            <input type="text" name="employer" class="form-control" value="<?= e($formData['employer'] ?? '') ?>" maxlength="100" list="suggest-employer" data-suggest="employer">
                            <datalist id="suggest-employer"></datalist>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary prev-step"><i class="fas fa-arrow-left me-1"></i> Back</button>
                    <button type="button" class="btn btn-primary next-step">Continue <i class="fas fa-arrow-right ms-1"></i></button>
                </div>
            </div>
        </div>

        <!-- Step 4: Emergency Contact -->
        <div class="reg-step" data-step="4">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-phone-alt me-2"></i>Emergency Contact</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Full Name <?= $regSettings['require_emergency_contact'] === '1' ? '<span class="text-danger">*</span>' : '' ?></label>
                            <input type="text" name="emergency_name" class="form-control <?= isset($errors['emergency_name']) ? 'is-invalid' : '' ?>" value="<?= e($formData['emergency_name'] ?? '') ?>" <?= $regSettings['require_emergency_contact'] === '1' ? 'required' : '' ?> maxlength="100">
                            <?php if (isset($errors['emergency_name'])): ?><div class="invalid-feedback"><?= e($errors['emergency_name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Phone Number <?= $regSettings['require_emergency_contact'] === '1' ? '<span class="text-danger">*</span>' : '' ?></label>
                            <input type="tel" name="emergency_phone" class="form-control <?= isset($errors['emergency_phone']) ? 'is-invalid' : '' ?>" value="<?= e($formData['emergency_phone'] ?? '') ?>" <?= $regSettings['require_emergency_contact'] === '1' ? 'required' : '' ?> placeholder="e.g. 0712 345 678" maxlength="20">
                            <?php if (isset($errors['emergency_phone'])): ?><div class="invalid-feedback"><?= e($errors['emergency_phone']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Relationship <?= $regSettings['require_emergency_contact'] === '1' ? '<span class="text-danger">*</span>' : '' ?></label>
                            <input type="text" name="emergency_relation" class="form-control <?= isset($errors['emergency_relation']) ? 'is-invalid' : '' ?>" value="<?= e($formData['emergency_relation'] ?? '') ?>" <?= $regSettings['require_emergency_contact'] === '1' ? 'required' : '' ?> maxlength="50" list="suggest-emergency_relation" data-suggest="emergency_relation">
                            <datalist id="suggest-emergency_relation"></datalist>
                            <?php if (isset($errors['emergency_relation'])): ?><div class="invalid-feedback"><?= e($errors['emergency_relation']) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary prev-step"><i class="fas fa-arrow-left me-1"></i> Back</button>
                    <button type="button" class="btn btn-primary next-step">Continue <i class="fas fa-arrow-right ms-1"></i></button>
                </div>
            </div>
        </div>

        <!-- Step 5: Document Uploads -->
        <div class="reg-step" data-step="5">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-file-upload me-2"></i>Document Uploads</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">National ID / Passport <span class="text-danger">*</span></label>
                            <input type="file" name="national_id_doc" class="form-control <?= isset($errors['national_id_doc']) ? 'is-invalid' : '' ?>" accept=".jpg,.jpeg,.png,.pdf" required>
                            <?php if (isset($errors['national_id_doc'])): ?><div class="invalid-feedback"><?= e($errors['national_id_doc']) ?></div><?php endif; ?>
                            <small class="text-muted">JPG, PNG, or PDF. Max 5MB.</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Passport Photo <span class="text-danger">*</span></label>
                            <input type="file" name="passport_photo" class="form-control <?= isset($errors['passport_photo']) ? 'is-invalid' : '' ?>" accept=".jpg,.jpeg,.png" required>
                            <?php if (isset($errors['passport_photo'])): ?><div class="invalid-feedback"><?= e($errors['passport_photo']) ?></div><?php endif; ?>
                            <small class="text-muted">JPG or PNG. Recent passport-size photo.</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Signature <span class="text-danger">*</span></label>
                            <input type="file" name="signature" class="form-control <?= isset($errors['signature']) ? 'is-invalid' : '' ?>" accept=".jpg,.jpeg,.png" required>
                            <?php if (isset($errors['signature'])): ?><div class="invalid-feedback"><?= e($errors['signature']) ?></div><?php endif; ?>
                            <small class="text-muted">JPG or PNG. Clear image of your signature.</small>
                        </div>
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary prev-step"><i class="fas fa-arrow-left me-1"></i> Back</button>
                    <button type="button" class="btn btn-primary next-step">Continue <i class="fas fa-arrow-right ms-1"></i></button>
                </div>
            </div>
        </div>

        <!-- Step 6: Review & Submit -->
        <div class="reg-step" data-step="6">
            <div class="card mb-4">
                <div class="card-header"><h5 class="mb-0"><i class="fas fa-check me-2"></i>Review Your Information</h5></div>
                <div class="card-body">
                    <p class="text-muted">Please review all information before submitting. You will not be able to edit after submission.</p>
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2">Personal Information</h6>
                            <table class="table table-sm table-borderless">
                                <tr><td class="text-muted">Name:</td><td class="review-name"></td></tr>
                                <tr><td class="text-muted">Gender:</td><td class="review-gender"></td></tr>
                                <tr><td class="text-muted">Date of Birth:</td><td class="review-dob"></td></tr>
                                <tr><td class="text-muted">National ID:</td><td class="review-national-id"></td></tr>
                                <tr><td class="text-muted">Passport:</td><td class="review-passport"></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2">Contact & Location</h6>
                            <table class="table table-sm table-borderless">
                                <tr><td class="text-muted">Email:</td><td class="review-email"></td></tr>
                                <tr><td class="text-muted">Phone:</td><td class="review-phone"></td></tr>
                                <tr><td class="text-muted">County:</td><td class="review-county"></td></tr>
                                <tr><td class="text-muted">Sub County:</td><td class="review-sub-county"></td></tr>
                                <tr><td class="text-muted">Ward:</td><td class="review-ward"></td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2">Professional</h6>
                            <table class="table table-sm table-borderless">
                                <tr><td class="text-muted">Occupation:</td><td class="review-occupation"></td></tr>
                                <tr><td class="text-muted">Employer:</td><td class="review-employer"></td></tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2">Emergency Contact</h6>
                            <table class="table table-sm table-borderless">
                                <tr><td class="text-muted">Name:</td><td class="review-emergency-name"></td></tr>
                                <tr><td class="text-muted">Phone:</td><td class="review-emergency-phone"></td></tr>
                                <tr><td class="text-muted">Relation:</td><td class="review-emergency-relation"></td></tr>
                            </table>
                        </div>
                    </div>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        By submitting this form, you confirm that all information provided is accurate and complete.
                        Your membership application will be reviewed and you will receive a verification email.
                    </div>
                </div>
                <div class="card-footer d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-secondary prev-step"><i class="fas fa-arrow-left me-1"></i> Back</button>
                    <button type="submit" class="btn btn-success" id="submitReg">
                        <i class="fas fa-paper-plane me-1"></i> Submit Registration
                    </button>
                </div>
            </div>
        </div>
    </form>
    <?php endif; ?>
</div>

<script>
$(document).ready(function() {
    var currentStep = 1;
    var totalSteps = 6;

    function showStep(step) {
        $('.reg-step').removeClass('active');
        $('.reg-step[data-step="' + step + '"]').addClass('active');
        $('#regProgress li').removeClass('active');
        $('#regProgress li').each(function() {
            var s = parseInt($(this).data('step'));
            if (s === step) $(this).addClass('active');
            else if (s < step) $(this).addClass('completed').removeClass('active');
            else $(this).removeClass('completed active');
        });
        currentStep = step;
        $(window).scrollTop(0);
    }

    function validateStep(step) {
        var $stepEl = $('.reg-step[data-step="' + step + '"]');
        var valid = true;
        $stepEl.find('[required]').each(function() {
            var $el = $(this);
            var val = $el.val() ? $el.val().trim() : '';
            if (!val) {
                $el.addClass('is-invalid');
                if (!$el.next('.invalid-feedback').length && !$el.parent().find('.invalid-feedback').length) {
                    var label = $el.closest('.mb-3').find('.form-label').text().replace('*', '').trim();
                    $el.after('<div class="invalid-feedback">' + label + ' is required</div>');
                }
                valid = false;
            } else {
                $el.removeClass('is-invalid');
            }
        });
        // File validation
        $stepEl.find('input[type="file"][required]').each(function() {
            var files = this.files;
            if (!files || files.length === 0) {
                $(this).addClass('is-invalid');
                valid = false;
            } else {
                $(this).removeClass('is-invalid');
            }
        });
        return valid;
    }

    $('.next-step').on('click', function() {
        if (validateStep(currentStep)) {
            // Load cascading data for step 2
            if (currentStep === 1) {
                populateReviewFields();
            }
            if (currentStep === 2) {
                // County-dependent sub-county load handled by change event
            }
            showStep(currentStep + 1);
        }
    });

    $('.prev-step').on('click', function() {
        showStep(currentStep - 1);
    });

    // Cascading location dropdowns
    $('#county_id').on('change', function() {
        var countyId = $(this).val();
        var $sub = $('#sub_county_id');
        var $ward = $('#ward_id');
        $sub.empty().append('<option value="">Loading...</option>').prop('disabled', true);
        $ward.empty().append('<option value="">Select Ward</option>').prop('disabled', true);
        if (countyId) {
            $.get('ajax/get-sub-counties.php', { county_id: countyId }, function(data) {
                $sub.empty().append('<option value="">Select Sub County</option>');
                $.each(data, function(i, sc) {
                    $sub.append('<option value="' + sc.id + '">' + sc.name + '</option>');
                });
                $sub.prop('disabled', false);
                $sub.trigger('change');
            });
        } else {
            $sub.empty().append('<option value="">Select Sub County</option>').prop('disabled', false);
        }
    });

    $('#sub_county_id').on('change', function() {
        var subCountyId = $(this).val();
        var $ward = $('#ward_id');
        $ward.empty().append('<option value="">Loading...</option>').prop('disabled', true);
        if (subCountyId) {
            $.get('ajax/get-wards.php', { sub_county_id: subCountyId }, function(data) {
                $ward.empty().append('<option value="">Select Ward</option>');
                $.each(data, function(i, w) {
                    $ward.append('<option value="' + w.id + '">' + w.name + '</option>');
                });
                $ward.prop('disabled', false);
            });
        } else {
            $ward.empty().append('<option value="">Select Ward</option>').prop('disabled', false);
        }
    });

    // Auto-suggest via data-suggest attribute (reuse existing initAutoSuggest logic)
    $('input[data-suggest]').each(function() {
        var $input = $(this);
        var field = $input.data('suggest');
        var $datalist = $('#' + $input.attr('list'));
        var timer;
        $input.on('input', function() {
            clearTimeout(timer);
            var q = $(this).val();
            if (q.length < 1) return;
            timer = setTimeout(function() {
                $.get('ajax/suggest-field.php', { field: field, q: q }, function(data) {
                    $datalist.empty();
                    $.each(data, function(i, v) {
                        $datalist.append('<option value="' + v + '">');
                    });
                });
            }, 300);
        });
    });

    // Populate review fields when entering step 6
    function populateReviewFields() {
        // Step 2 is where county/sub-county/ward are - these need text, not IDs
        var countyText = $('#county_id option:selected').text();
        var subCountyText = $('#sub_county_id option:selected').text();
        var wardText = $('#ward_id option:selected').text();

        $('.review-name').text(
            $('input[name="first_name"]').val() + ' ' + $('input[name="last_name"]').val()
        );
        $('.review-gender').text($('select[name="gender"] option:selected').text());
        $('.review-dob').text($('input[name="date_of_birth"]').val());
        $('.review-national-id').text($('input[name="national_id"]').val() || '-');
        $('.review-passport').text($('input[name="passport"]').val() || '-');
        $('.review-email').text($('input[name="email"]').val());
        $('.review-phone').text($('input[name="phone"]').val());
        $('.review-county').text(countyText);
        $('.review-sub-county').text(subCountyText);
        $('.review-ward').text(wardText);
        $('.review-occupation').text($('input[name="occupation"]').val());
        $('.review-employer').text($('input[name="employer"]').val() || '-');
        $('.review-emergency-name').text($('input[name="emergency_name"]').val());
        $('.review-emergency-phone').text($('input[name="emergency_phone"]').val());
        $('.review-emergency-relation').text($('input[name="emergency_relation"]').val());
    }

    // Form submission loading state
    $('#regForm').on('submit', function() {
        $('#submitReg').prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Submitting...');
    });

    // Resend verification email
    $('#resendLink').on('click', function(e) {
        e.preventDefault();
        var email = $(this).data('email');
        var $link = $(this);
        $link.text('Sending...').css('pointer-events', 'none');
        $.post('ajax/resend-verification.php', {
            email: email,
            csrf_token: $('input[name="csrf_token"]').val()
        }, function(resp) {
            if (resp.success) {
                showToast('success', resp.message || 'Verification email resent. Please check your inbox.');
            } else {
                showToast('error', resp.message || 'Failed to resend. Try again later.');
            }
            $link.text('click here to resend').css('pointer-events', '');
        }).fail(function() {
            showToast('error', 'Network error. Please try again.');
            $link.text('click here to resend').css('pointer-events', '');
        });
    });
});
</script>

<?php include __DIR__ . '/views/layouts/footer.php'; ?>
