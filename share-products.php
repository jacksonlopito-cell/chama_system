<?php
require_once __DIR__ . '/includes/config.php';
requireAuth();
requirePermission('view_shares');

$db = getConnection();
$currentUser = getCurrentUser();
$pageTitle = 'Share Products';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    if (isset($_POST['save_product'])) {
        requirePermission('edit_shares');
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price_per_share'] ?? 0);
        $minShares = (int)($_POST['min_shares'] ?? 1);
        $maxShares = !empty($_POST['max_shares']) ? (int)$_POST['max_shares'] : null;
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || $price <= 0) {
            setFlash('danger', 'Product name and price are required.');
            redirect('share-products.php' . ($id ? "?edit=$id" : ''));
        }

        if ($id) {
            $stmt = $db->prepare("UPDATE share_products SET name=?, price_per_share=?, min_shares=?, max_shares=?, description=?, status=? WHERE id=? AND group_code=?");
            $stmt->execute([$name, $price, $minShares, $maxShares, $description, $status, $id, $_SESSION['group_code']]);
            logAudit($_SESSION['user_id'], $_SESSION['username'], 'edit', 'share_products', $id, null, ['name' => $name], 'Share product updated');
            setFlash('success', 'Product updated successfully.');
        } else {
            $stmt = $db->prepare("INSERT INTO share_products (name, price_per_share, min_shares, max_shares, description, status, group_code) VALUES (?,?,?,?,?,?,?)");
            $stmt->execute([$name, $price, $minShares, $maxShares, $description, $status, $_SESSION['group_code']]);
            logAudit($_SESSION['user_id'], $_SESSION['username'], 'create', 'share_products', $db->lastInsertId(), null, ['name' => $name], 'Share product created');
            setFlash('success', 'Product created successfully.');
        }
        redirect('share-products.php');
    }

    if (isset($_POST['toggle_product'])) {
        requirePermission('edit_shares');
        $id = (int)$_POST['id'];
        $stmt = $db->prepare("SELECT status FROM share_products WHERE id=? AND group_code=?");
        $stmt->execute([$id, $_SESSION['group_code']]);
        $product = $stmt->fetch();
        if ($product) {
            $newStatus = $product['status'] === 'active' ? 'inactive' : 'active';
            $db->prepare("UPDATE share_products SET status=? WHERE id=?")->execute([$newStatus, $id]);
            setFlash('success', 'Product status changed.');
        }
        redirect('share-products.php');
    }

    if (isset($_POST['delete_product'])) {
        requirePermission('delete_shares');
        $id = (int)$_POST['id'];
        // Check if any purchases reference this product
        $check = $db->prepare("SELECT COUNT(*) FROM share_purchases WHERE product_id=?");
        $check->execute([$id]);
        if ((int)$check->fetchColumn() > 0) {
            setFlash('danger', 'Cannot delete: this product has existing share purchases. Deactivate it instead.');
        } else {
            $db->prepare("DELETE FROM share_products WHERE id=? AND group_code=?")->execute([$id, $_SESSION['group_code']]);
            setFlash('success', 'Product deleted.');
        }
        redirect('share-products.php');
    }
}

// Get edit data
$editProduct = null;
if (isset($_GET['edit']) && hasPermission('edit_shares')) {
    $stmt = $db->prepare("SELECT * FROM share_products WHERE id=? AND group_code=?");
    $stmt->execute([(int)$_GET['edit'], $_SESSION['group_code']]);
    $editProduct = $stmt->fetch();
}

$products = $db->prepare("SELECT * FROM share_products WHERE group_code=? ORDER BY status DESC, name");
$products->execute([$_SESSION['group_code']]);
$products = $products->fetchAll();

include __DIR__ . '/views/layouts/header.php';
include __DIR__ . '/views/layouts/sidebar.php';
include __DIR__ . '/views/layouts/navbar.php';
?>
<div class="main-content">
    <div class="page-header">
        <div><h4>Share Products</h4><p>Manage share product types</p></div>
        <?php if (hasPermission('edit_shares')): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#productModal"><i class="fas fa-plus me-1"></i>Add Product</button>
        <?php endif; ?>
    </div>
    <?php displayFlash(); ?>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="productsTable">
                    <thead><tr><th>Name</th><th>Price per Share</th><th>Min</th><th>Max</th><th>Status</th><th class="text-end no-sort">Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($products)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No share products defined. Click "Add Product" to create one.</td></tr>
                        <?php else: foreach ($products as $p): ?>
                        <tr>
                            <td class="fw-medium"><?= e($p['name']) ?></td>
                            <td><?= formatCurrency($p['price_per_share']) ?></td>
                            <td><?= (int)$p['min_shares'] ?></td>
                            <td><?= $p['max_shares'] ? (int)$p['max_shares'] : '<span class="text-muted">Unlimited</span>' ?></td>
                            <td><?= $p['status'] === 'active' ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                            <td class="text-end">
                                <?php if (hasPermission('edit_shares')): ?>
                                <a href="?edit=<?= $p['id'] ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="fas fa-edit"></i></a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Toggle status?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" name="toggle_product" class="btn btn-sm btn-outline-<?= $p['status'] === 'active' ? 'warning' : 'success' ?>" title="<?= $p['status'] === 'active' ? 'Deactivate' : 'Activate' ?>">
                                        <i class="fas fa-<?= $p['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if (hasPermission('delete_shares')): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this product permanently?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                    <button type="submit" name="delete_product" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Product Modal (Add/Edit) -->
<div class="modal fade" id="productModal" tabindex="-1" <?= $editProduct ? 'data-show="true"' : '' ?>>
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <?php if ($editProduct): ?>
                <input type="hidden" name="id" value="<?= $editProduct['id'] ?>">
                <?php endif; ?>
                <div class="modal-header">
                    <h5><i class="fas fa-<?= $editProduct ? 'edit' : 'plus' ?> me-1"></i><?= $editProduct ? 'Edit Product' : 'Add Product' ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Product Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" value="<?= e($editProduct['name'] ?? '') ?>" required maxlength="200">
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Price per Share (KES) <span class="text-danger">*</span></label>
                            <input type="number" name="price_per_share" class="form-control" value="<?= $editProduct ? (float)$editProduct['price_per_share'] : '' ?>" required min="1" step="0.01">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Min Shares</label>
                            <input type="number" name="min_shares" class="form-control" value="<?= (int)($editProduct['min_shares'] ?? 1) ?>" min="1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Max Shares</label>
                            <input type="number" name="max_shares" class="form-control" value="<?= $editProduct ? (int)$editProduct['max_shares'] : '' ?>" min="1" placeholder="Unlimited">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3"><?= e($editProduct['description'] ?? '') ?></textarea>
                    </div>
                    <?php if ($editProduct): ?>
                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="active" <?= $editProduct['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $editProduct['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_product" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraScripts = <<<'JS'
<script>
$(document).ready(function() {
    if ($('#productModal').data('show')) {
        new bootstrap.Modal(document.getElementById('productModal')).show();
    }
    dtHelper.init('#productsTable');
});
</script>
JS;
include __DIR__ . '/views/layouts/footer.php';
