<?php
/**
 * Homepage CMS — Visual Page Builder
 * Normalized DB: homepage_sections, homepage_fields, homepage_items, homepage_item_fields
 * No JSON exposed to the user anywhere.
 */

// ================================================================
// READ OPERATIONS
// ================================================================

function hp_get_sections(): array {
    try {
        $db = getConnection();
        $rows = $db->query("SELECT * FROM homepage_sections ORDER BY sort_order ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
        $result = [];
        foreach ($rows as $row) {
            $row['fields'] = [];
            $row['items'] = [];
            $result[$row['section_key']] = $row;
        }
        $keys = array_keys($result);
        if (empty($keys)) return $result;

        // Load all fields at once
        $placeholders = implode(',', array_fill(0, count($keys), '?'));
        $stmt = $db->prepare("SELECT hf.*, hs.section_key FROM homepage_fields hf JOIN homepage_sections hs ON hs.id = hf.section_id WHERE hs.section_key IN ($placeholders)");
        $stmt->execute($keys);
        while ($f = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$f['section_key']]['fields'][$f['field_key']] = $f['field_value'];
        }

        // Load all items with their fields
        $stmt = $db->prepare("SELECT hi.*, hs.section_key FROM homepage_items hi JOIN homepage_sections hs ON hs.id = hi.section_id WHERE hs.section_key IN ($placeholders) ORDER BY hi.sort_order ASC, hi.id ASC");
        $stmt->execute($keys);
        $itemsBySection = [];
        while ($item = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $item['fields'] = [];
            $itemsBySection[$item['section_key']][] = $item;
        }
        if (!empty($itemsBySection)) {
            // Load item fields
            $allItemIds = [];
            foreach ($itemsBySection as $secKey => $items) {
                foreach ($items as $it) {
                    $allItemIds[] = $it['id'];
                }
            }
            if (!empty($allItemIds)) {
                $idPlaceholders = implode(',', array_fill(0, count($allItemIds), '?'));
                $stmtFields = $db->prepare("SELECT * FROM homepage_item_fields WHERE item_id IN ($idPlaceholders)");
                $stmtFields->execute($allItemIds);
                $itemFieldMap = [];
                while ($if = $stmtFields->fetch(PDO::FETCH_ASSOC)) {
                    $itemFieldMap[$if['item_id']][$if['field_key']] = $if['field_value'];
                }
                foreach ($itemsBySection as $secKey => &$items) {
                    foreach ($items as &$it) {
                        $it['fields'] = $itemFieldMap[$it['id']] ?? [];
                    }
                    unset($it);
                    $result[$secKey]['items'] = $items;
                }
                unset($items);
            }
        }
        return $result;
    } catch (Exception $e) {
        error_log("hp_get_sections: " . $e->getMessage());
        return [];
    }
}

function hp_get_section(string $key): ?array {
    $sections = hp_get_sections();
    return $sections[$key] ?? null;
}

function hp_get_section_by_id(int $id): ?array {
    try {
        $db = getConnection();
        $stmt = $db->prepare("SELECT * FROM homepage_sections WHERE id = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;
        $row['fields'] = [];
        $row['items'] = [];
        $fStmt = $db->prepare("SELECT field_key, field_value FROM homepage_fields WHERE section_id = ?");
        $fStmt->execute([$id]);
        while ($f = $fStmt->fetch(PDO::FETCH_ASSOC)) {
            $row['fields'][$f['field_key']] = $f['field_value'];
        }
        $iStmt = $db->prepare("SELECT * FROM homepage_items WHERE section_id = ? ORDER BY sort_order ASC");
        $iStmt->execute([$id]);
        while ($item = $iStmt->fetch(PDO::FETCH_ASSOC)) {
            $item['fields'] = [];
            $ifStmt = $db->prepare("SELECT field_key, field_value FROM homepage_item_fields WHERE item_id = ?");
            $ifStmt->execute([$item['id']]);
            while ($if = $ifStmt->fetch(PDO::FETCH_ASSOC)) {
                $item['fields'][$if['field_key']] = $if['field_value'];
            }
            $row['items'][] = $item;
        }
        return $row;
    } catch (Exception $e) {
        error_log("hp_get_section_by_id: " . $e->getMessage());
        return null;
    }
}

// ================================================================
// WRITE OPERATIONS
// ================================================================

function hp_save_field(int $section_id, string $field_key, $field_value): bool {
    try {
        $db = getConnection();
        $stmt = $db->prepare("INSERT INTO homepage_fields (section_id, field_key, field_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)");
        $stmt->execute([$section_id, $field_key, $field_value ?? '']);
        return true;
    } catch (Exception $e) {
        error_log("hp_save_field: " . $e->getMessage());
        return false;
    }
}

function hp_save_fields(int $section_id, array $fields): bool {
    foreach ($fields as $key => $value) {
        hp_save_field($section_id, $key, $value);
    }
    return true;
}

function hp_save_section_fields(string $section_key, array $fields): bool {
    $sec = hp_get_section($section_key);
    if (!$sec) return false;
    return hp_save_fields((int)$sec['id'], $fields);
}

function hp_add_item(int $section_id, array $data, int $sort_order = 0): ?int {
    try {
        $db = getConnection();
        $stmt = $db->prepare("INSERT INTO homepage_items (section_id, sort_order) VALUES (?, ?)");
        $stmt->execute([$section_id, $sort_order]);
        $itemId = (int)$db->lastInsertId();
        $ifStmt = $db->prepare("INSERT INTO homepage_item_fields (item_id, field_key, field_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)");
        foreach ($data as $key => $value) {
            $ifStmt->execute([$itemId, $key, $value ?? '']);
        }
        return $itemId;
    } catch (Exception $e) {
        error_log("hp_add_item: " . $e->getMessage());
        return null;
    }
}

function hp_update_item(int $item_id, array $data): bool {
    try {
        $db = getConnection();
        $stmt = $db->prepare("INSERT INTO homepage_item_fields (item_id, field_key, field_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)");
        foreach ($data as $key => $value) {
            $stmt->execute([$item_id, $key, $value ?? '']);
        }
        return true;
    } catch (Exception $e) {
        error_log("hp_update_item: " . $e->getMessage());
        return false;
    }
}

function hp_delete_item(int $item_id): bool {
    try {
        $db = getConnection();
        $db->prepare("DELETE FROM homepage_items WHERE id = ?")->execute([$item_id]);
        return true;
    } catch (Exception $e) {
        error_log("hp_delete_item: " . $e->getMessage());
        return false;
    }
}

function hp_reorder_items(int $section_id, array $order): bool {
    try {
        $db = getConnection();
        $stmt = $db->prepare("UPDATE homepage_items SET sort_order = ? WHERE id = ? AND section_id = ?");
        foreach ($order as $itemId => $sortOrder) {
            $stmt->execute([(int)$sortOrder, (int)$itemId, $section_id]);
        }
        return true;
    } catch (Exception $e) {
        error_log("hp_reorder_items: " . $e->getMessage());
        return false;
    }
}

function hp_toggle(string $section_key, bool $visible): bool {
    try {
        $db = getConnection();
        $stmt = $db->prepare("UPDATE homepage_sections SET is_visible = ? WHERE section_key = ?");
        $stmt->execute([$visible ? 1 : 0, $section_key]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        error_log("hp_toggle: " . $e->getMessage());
        return false;
    }
}

function hp_reorder_sections(array $order): bool {
    try {
        $db = getConnection();
        $stmt = $db->prepare("UPDATE homepage_sections SET sort_order = ? WHERE section_key = ?");
        foreach ($order as $item) {
            $stmt->execute([(int)$item[1], $item[0]]);
        }
        return true;
    } catch (Exception $e) {
        error_log("hp_reorder_sections: " . $e->getMessage());
        return false;
    }
}

// ================================================================
// REVISION / UNDO
// ================================================================

function hp_take_snapshot(): string {
    $sections = hp_get_sections();
    $data = [];
    foreach ($sections as $sk => $sec) {
        $s = [
            'key' => $sk,
            'title' => $sec['title'],
            'visible' => $sec['is_visible'],
            'order' => $sec['sort_order'],
            'fields' => $sec['fields'],
            'items' => [],
        ];
        foreach ($sec['items'] as $item) {
            $s['items'][] = $item['fields'];
        }
        $data[$sk] = $s;
    }
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function hp_save_revision(string $action): ?int {
    try {
        $db = getConnection();
        $snapshot = hp_take_snapshot();
        $userId = $_SESSION['user_id'] ?? null;
        $stmt = $db->prepare("INSERT INTO homepage_revisions (action, snapshot, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$action, $snapshot, $userId]);
        return (int)$db->lastInsertId();
    } catch (Exception $e) {
        error_log("hp_save_revision: " . $e->getMessage());
        return null;
    }
}

function hp_get_revisions(int $limit = 20): array {
    try {
        $db = getConnection();
        return $db->query("SELECT hr.*, u.username FROM homepage_revisions hr LEFT JOIN users u ON u.id = hr.created_by ORDER BY hr.id DESC LIMIT $limit")->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function hp_restore_revision(int $revision_id): bool {
    try {
        $db = getConnection();
        $stmt = $db->prepare("SELECT * FROM homepage_revisions WHERE id = ?");
        $stmt->execute([$revision_id]);
        $rev = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$rev) return false;

        $data = json_decode($rev['snapshot'], true);
        if (!$data) return false;

        // Clear all existing data
        $db->exec("DELETE FROM homepage_item_fields");
        $db->exec("DELETE FROM homepage_items");
        $db->exec("DELETE FROM homepage_fields");
        $db->exec("DELETE FROM homepage_sections");
        $db->exec("ALTER TABLE homepage_sections AUTO_INCREMENT = 1");
        $db->exec("ALTER TABLE homepage_fields AUTO_INCREMENT = 1");
        $db->exec("ALTER TABLE homepage_items AUTO_INCREMENT = 1");
        $db->exec("ALTER TABLE homepage_item_fields AUTO_INCREMENT = 1");

        $insertSec = $db->prepare("INSERT INTO homepage_sections (section_key, title, is_visible, sort_order) VALUES (?, ?, ?, ?)");
        $insertField = $db->prepare("INSERT INTO homepage_fields (section_id, field_key, field_value) VALUES (?, ?, ?)");
        $insertItem = $db->prepare("INSERT INTO homepage_items (section_id, sort_order) VALUES (?, ?)");
        $insertIF = $db->prepare("INSERT INTO homepage_item_fields (item_id, field_key, field_value) VALUES (?, ?, ?)");

        foreach ($data as $sk => $s) {
            $insertSec->execute([$s['key'], $s['title'] ?? '', $s['visible'] ?? 1, $s['order'] ?? 0]);
            $secId = (int)$db->lastInsertId();
            foreach ($s['fields'] as $fk => $fv) {
                $insertField->execute([$secId, $fk, $fv]);
            }
            foreach ($s['items'] as $order => $itemFields) {
                $insertItem->execute([$secId, $order]);
                $itemId = (int)$db->lastInsertId();
                foreach ($itemFields as $fk => $fv) {
                    $insertIF->execute([$itemId, $fk, $fv ?? '']);
                }
            }
        }

        hp_save_revision('undo_restore');
        return true;
    } catch (Exception $e) {
        error_log("hp_restore_revision: " . $e->getMessage());
        return false;
    }
}

// ================================================================
// RENDER ENGINE
// ================================================================

function hp_render_all(): void {
    $sections = hp_get_sections();
    foreach ($sections as $key => $section) {
        if (empty($section['is_visible'])) continue;
        $renderFn = 'hp_render_' . str_replace('-', '_', $key);
        if (function_exists($renderFn)) {
            $renderFn($section);
        }
    }
}

function hp_render_preloader(array $sec): void {
    ?><div class="preloader"><div class="spinner"></div></div><?php
}

function hp_render_navbar(array $sec): void {
    $f = $sec['fields'];
    $brand = $f['brand_text'] ?? 'Chama System';
    $signInText = $f['sign_in_text'] ?? 'Sign In';
    $signInLink = $f['sign_in_link'] ?? 'login.php';
    ?>
    <nav class="landing-navbar navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php"><i class="fas fa-hand-holding-usd me-2"></i><?= e($brand) ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-lg-center">
                    <?php foreach ($sec['items'] as $item): $fi = $item['fields']; ?>
                    <li class="nav-item"><a class="nav-link" href="<?= e($fi['href'] ?? '#') ?>"><?= e($fi['label'] ?? '') ?></a></li>
                    <?php endforeach; ?>
                    <li class="nav-item ms-lg-3"><a href="<?= e($signInLink) ?>" class="btn btn-secondary btn-sm rounded-pill px-4"><i class="fas fa-sign-in-alt me-1"></i><?= e($signInText) ?></a></li>
                </ul>
            </div>
        </div>
    </nav>
    <?php
}

function hp_render_hero(array $sec): void {
    $f = $sec['fields'];
    $bg = e($f['bg_color'] ?? '#0f0e3a');
    $text = e($f['text_color'] ?? '#ffffff');
    ?>
    <section id="home" class="hero-section" style="background: <?= $bg ?>; color: <?= $text ?>;">
        <div class="hero-shape hero-shape-1"></div>
        <div class="hero-shape hero-shape-2"></div>
        <div class="hero-shape hero-shape-3"></div>
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 hero-content animate-on-scroll">
                    <h1 style="color: <?= $text ?>;"><?= e($f['headline'] ?? '') ?></h1>
                    <p style="color: <?= $text ?>;"><?= e($f['subheadline'] ?? '') ?></p>
                    <div class="d-flex flex-wrap gap-3">
                        <?php if (!empty($f['primary_btn_text'])): ?>
                        <a href="<?= e($f['primary_btn_link'] ?? '#') ?>" class="btn btn-secondary btn-lg rounded-pill px-4"><i class="fas fa-rocket me-2"></i><?= e($f['primary_btn_text']) ?></a>
                        <?php endif; ?>
                        <?php if (!empty($f['secondary_btn_text'])): ?>
                        <a href="<?= e($f['secondary_btn_link'] ?? '#') ?>" class="btn btn-outline-light btn-lg rounded-pill px-4"><i class="fas fa-play-circle me-2"></i><?= e($f['secondary_btn_text']) ?></a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6 hero-image text-center animate-on-scroll">
                    <?php if (!empty($f['hero_image'])): ?>
                    <img src="<?= e($f['hero_image']) ?>" alt="Hero" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div class="d-none d-lg-block" style="padding: 3rem;"><i class="fas fa-chart-line" style="font-size: 12rem; opacity: 0.15; color: <?= $text ?>;"></i></div>
                </div>
            </div>
        </div>
    </section>
    <?php
}

function hp_render_about(array $sec): void {
    $f = $sec['fields'];
    ?>
    <section id="about" class="section-padding" style="background: <?= e($f['bg_color'] ?? '#ffffff') ?>;">
        <div class="container">
            <div class="section-title animate-on-scroll">
                <h2><?= e($f['headline'] ?? '') ?></h2>
                <p><?= e($f['subheadline'] ?? '') ?></p>
            </div>
            <div class="row g-4 align-items-center">
                <div class="col-lg-6 animate-on-scroll">
                    <?php if (!empty($f['about_image'])): ?>
                    <img src="<?= e($f['about_image']) ?>" alt="About" class="img-fluid rounded-3 shadow-lg" onerror="this.style.display='none'">
                    <?php endif; ?>
                    <div class="bg-soft-primary rounded-3 p-5 text-center" style="min-height: 300px; display: flex; align-items: center; justify-content: center;">
                        <div>
                            <i class="fas <?= e($f['badge_icon'] ?? 'fa-users') ?>" style="font-size: 4rem; color: var(--secondary);"></i>
                            <h4 class="mt-3"><?= e($f['badge_text'] ?? '') ?></h4>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 animate-on-scroll">
                    <div class="ps-lg-4">
                        <h3 class="mb-3" style="color: var(--primary);">Why Choose Our Platform?</h3>
                        <p class="text-muted mb-4"><?= e($f['subheadline'] ?? '') ?></p>
                        <?php foreach ($sec['items'] as $item): $fi = $item['fields']; ?>
                        <div class="d-flex mb-3">
                            <div class="flex-shrink-0">
                                <div class="bg-soft-<?= e($fi['color'] ?? 'primary') ?> rounded-2 p-2" style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas <?= e($fi['icon'] ?? 'fa-check') ?>" style="color: var(--<?= e($fi['color'] ?? 'secondary') ?>);"></i>
                                </div>
                            </div>
                            <div class="ms-3">
                                <h6><?= e($fi['title'] ?? '') ?></h6>
                                <p class="text-muted small mb-0"><?= e($fi['description'] ?? '') ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php
}

function hp_render_services(array $sec): void {
    $f = $sec['fields'];
    ?>
    <section id="services" class="section-padding" style="background: <?= e($f['bg_color'] ?? 'var(--gray-50)') ?>;">
        <div class="container">
            <div class="section-title animate-on-scroll">
                <h2><?= e($f['headline'] ?? '') ?></h2>
                <p><?= e($f['subheadline'] ?? '') ?></p>
            </div>
            <div class="row g-4">
                <?php foreach ($sec['items'] as $item): $fi = $item['fields']; ?>
                <div class="col-md-6 col-lg-4 animate-on-scroll">
                    <div class="service-card">
                        <div class="icon bg-soft-<?= e($fi['color'] ?? 'primary') ?>">
                            <i class="fas <?= e($fi['icon'] ?? 'fa-cog') ?>" style="color: var(--<?= e($fi['color'] ?? 'secondary') ?>);"></i>
                        </div>
                        <h5><?= e($fi['title'] ?? '') ?></h5>
                        <p><?= e($fi['description'] ?? '') ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}

function hp_render_stats(array $sec): void {
    $f = $sec['fields'];
    ?>
    <section id="benefits" class="stats-section" style="background: <?= e($f['bg_color'] ?? '#0f0e3a') ?>;">
        <div class="container">
            <div class="section-title animate-on-scroll">
                <h2 style="color: <?= e($f['text_color'] ?? '#ffffff') ?>;"><?= e($f['headline'] ?? '') ?></h2>
                <p style="color: <?= e($f['text_color'] ?? 'rgba(255,255,255,0.7)') ?>;"><?= e($f['subheadline'] ?? '') ?></p>
            </div>
            <div class="row g-4">
                <?php foreach ($sec['items'] as $item): $fi = $item['fields']; ?>
                <div class="col-6 col-lg-3 animate-on-scroll">
                    <div class="stat-item">
                        <div class="number"><?= e($fi['prefix'] ?? '') ?><span class="counter-number" data-target="<?= e($fi['number'] ?? 0) ?>">0</span><?= e($fi['suffix'] ?? '') ?></div>
                        <div class="label"><?= e($fi['label'] ?? '') ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}

function hp_render_testimonials(array $sec): void {
    $f = $sec['fields'];
    ?>
    <section id="testimonials" class="section-padding">
        <div class="container">
            <div class="section-title animate-on-scroll">
                <h2><?= e($f['headline'] ?? '') ?></h2>
                <p><?= e($f['subheadline'] ?? '') ?></p>
            </div>
            <div class="row g-4">
                <?php foreach ($sec['items'] as $item): $fi = $item['fields']; $stars = (float)($fi['stars'] ?? 5); ?>
                <div class="col-md-6 col-lg-4 animate-on-scroll">
                    <div class="testimonial-card">
                        <div class="stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star<?= $i > $stars ? ($i - 0.5 <= $stars ? '-half-alt' : '') : '' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <p>"<?= e($fi['text'] ?? '') ?>"</p>
                        <div class="author">
                            <div class="user-avatar" style="background: var(--secondary);"><?= e($fi['initials'] ?? '') ?></div>
                            <div><h6><?= e($fi['name'] ?? '') ?></h6><span><?= e($fi['role'] ?? '') ?></span></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}

function hp_render_gallery(array $sec): void {
    $f = $sec['fields'];
    $gradients = ['primary' => 'linear-gradient(135deg, var(--primary), var(--primary-light))', 'success' => 'linear-gradient(135deg, var(--success), #039855)', 'warning' => 'linear-gradient(135deg, var(--warning), #dc6803)', 'info' => 'linear-gradient(135deg, var(--info), #0086c9)', 'danger' => 'linear-gradient(135deg, var(--danger), #b42318)'];
    ?>
    <section id="gallery" class="section-padding" style="background: var(--gray-50);">
        <div class="container">
            <div class="section-title animate-on-scroll">
                <h2><?= e($f['headline'] ?? '') ?></h2>
                <p><?= e($f['subheadline'] ?? '') ?></p>
            </div>
            <div class="row g-3">
                <?php foreach ($sec['items'] as $item): $fi = $item['fields']; $g = $gradients[$fi['color'] ?? 'primary'] ?? $gradients['primary']; ?>
                <div class="col-6 col-md-4 col-lg-3 animate-on-scroll">
                    <div class="gallery-item">
                        <div style="background: <?= $g ?>; height: 200px; display: flex; align-items: center; justify-content: center; border-radius: 8px; flex-direction: column;">
                            <i class="fas <?= e($fi['icon'] ?? 'fa-image') ?>" style="font-size: 3rem; color: rgba(255,255,255,0.3);"></i>
                            <?php if (!empty($fi['label'])): ?><span class="text-white mt-2 small"><?= e($fi['label']) ?></span><?php endif; ?>
                        </div>
                        <div class="overlay"><i class="fas fa-search-plus"></i></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}

function hp_render_partners(array $sec): void {
    $f = $sec['fields'];
    ?>
    <section id="partners" class="section-padding">
        <div class="container">
            <div class="section-title animate-on-scroll">
                <h2><?= e($f['headline'] ?? '') ?></h2>
                <p><?= e($f['subheadline'] ?? '') ?></p>
            </div>
            <div class="row justify-content-center align-items-center g-4">
                <?php foreach ($sec['items'] as $item): $fi = $item['fields']; ?>
                <div class="col-4 col-md-2">
                    <div class="partner-logo"><div style="background: var(--gray-200); height: 60px; border-radius: 8px; display: flex; align-items: center; justify-content: center; padding: 0 1rem;"><span class="fw-bold text-muted"><?= e($fi['name'] ?? '') ?></span></div></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
    <?php
}

function hp_render_faqs(array $sec): void {
    $f = $sec['fields'];
    ?>
    <section id="faqs" class="section-padding" style="background: var(--gray-50);">
        <div class="container">
            <div class="section-title animate-on-scroll">
                <h2><?= e($f['headline'] ?? '') ?></h2>
                <p><?= e($f['subheadline'] ?? '') ?></p>
            </div>
            <div class="row justify-content-center">
                <div class="col-lg-8 animate-on-scroll">
                    <div class="accordion" id="faqAccordion">
                        <?php foreach ($sec['items'] as $i => $item): $fi = $item['fields']; ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button <?= $i > 0 ? 'collapsed' : '' ?>" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>"><?= e($fi['question'] ?? '') ?></button>
                            </h2>
                            <div id="faq<?= $i ?>" class="accordion-collapse collapse <?= $i === 0 ? 'show' : '' ?>" data-bs-parent="#faqAccordion">
                                <div class="accordion-body"><?= e($fi['answer'] ?? '') ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php
}

function hp_render_contact(array $sec): void {
    $f = $sec['fields'];
    ?>
    <section id="contact" class="section-padding">
        <div class="container">
            <div class="section-title animate-on-scroll">
                <h2><?= e($f['headline'] ?? '') ?></h2>
                <p><?= e($f['subheadline'] ?? '') ?></p>
            </div>
            <div class="row g-4 justify-content-center">
                <div class="col-lg-5 animate-on-scroll">
                    <?php foreach ($sec['items'] as $item): $fi = $item['fields']; $c = $fi['color'] ?? 'primary'; ?>
                    <div class="d-flex mb-4">
                        <div class="flex-shrink-0">
                            <div class="bg-soft-<?= $c ?> rounded-2 p-3" style="width: 48px; height: 48px; display: flex; align-items: center; justify-content: center;">
                                <i class="fas <?= e($fi['icon'] ?? 'fa-info') ?>" style="color: var(--<?= $c ?>);"></i>
                            </div>
                        </div>
                        <div class="ms-3"><h6><?= e($fi['title'] ?? '') ?></h6><p class="text-muted small mb-0"><?= e($fi['value'] ?? '') ?></p></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="col-lg-5 animate-on-scroll">
                    <div class="card shadow-sm"><div class="card-body p-4">
                        <form action="<?= e($f['form_action'] ?? 'ajax/contact.php') ?>" method="POST" data-ajax>
                            <?= csrfField() ?>
                            <div class="mb-3"><label class="form-label">Your Name <span class="text-danger">*</span></label><input type="text" name="name" class="form-control" required placeholder="John Doe"></div>
                            <div class="mb-3"><label class="form-label">Email <span class="text-danger">*</span></label><input type="email" name="email" class="form-control" required placeholder="john@example.com"></div>
                            <div class="mb-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" placeholder="+254 712 345 678"></div>
                            <div class="mb-3"><label class="form-label">Message <span class="text-danger">*</span></label><textarea name="message" rows="4" class="form-control" required placeholder="Your message..."></textarea></div>
                            <button type="submit" class="btn btn-primary w-100"><i class="fas fa-paper-plane me-2"></i>Send Message</button>
                        </form>
                    </div></div>
                </div>
            </div>
        </div>
    </section>
    <?php
}

function hp_render_footer(array $sec): void {
    $f = $sec['fields'];
    $socials = []; $quickLinks = []; $serviceLinks = [];
    foreach ($sec['items'] as $item) {
        $fi = $item['fields'];
        $type = $fi['type'] ?? '';
        if ($type === 'social') $socials[] = $fi;
        elseif ($type === 'quick_link') $quickLinks[] = $fi;
        elseif ($type === 'service_link') $serviceLinks[] = $fi;
    }
    ?>
    <footer class="footer">
        <div class="container">
            <div class="row g-4">
                <div class="col-lg-4">
                    <h5><i class="fas fa-hand-holding-usd me-2"></i><?= e($f['brand_text'] ?? 'Chama System') ?></h5>
                    <p><?= e($f['description'] ?? '') ?></p>
                    <div class="social-links">
                        <?php foreach ($socials as $s): ?><a href="<?= e($s['url'] ?? '#') ?>"><i class="fab <?= e($s['icon'] ?? '') ?>"></i></a><?php endforeach; ?>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <h5>Quick Links</h5>
                    <?php foreach ($quickLinks as $l): ?><a href="<?= e($l['href'] ?? '#') ?>"><?= e($l['label'] ?? '') ?></a><?php endforeach; ?>
                </div>
                <div class="col-6 col-lg-2">
                    <h5>Services</h5>
                    <?php foreach ($serviceLinks as $l): ?><a href="<?= e($l['href'] ?? '#') ?>"><?= e($l['label'] ?? '') ?></a><?php endforeach; ?>
                </div>
                <div class="col-lg-4">
                    <h5><?= e($f['newsletter_title'] ?? 'Newsletter') ?></h5>
                    <p><?= e($f['newsletter_text'] ?? '') ?></p>
                    <div class="input-group">
                        <input type="email" class="form-control" placeholder="Your email" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); color: #fff;">
                        <button class="btn btn-secondary" type="button"><i class="fas fa-arrow-right"></i></button>
                    </div>
                </div>
            </div>
            <div class="footer-bottom"><p class="mb-0">&copy; <?= date('Y') ?> Chama System. All rights reserved.</p></div>
        </div>
    </footer>
    <?php
}

function hp_render_copyright(array $sec): void {
    $f = $sec['fields']; ?>
    <div style="background: #1a1a2e; color: rgba(255,255,255,0.6); text-align: center; padding: 1rem 0; font-size: 0.875rem;">
        <div class="container"><p class="mb-0"><?= e($f['copyright_text'] ?? '&copy; ' . date('Y') . ' Chama System. All rights reserved.') ?></p></div>
    </div>
    <?php
}

function hp_render_dark_mode_toggle(array $sec): void {
    ?><button id="darkModeToggle" class="dark-mode-toggle" title="Toggle Dark Mode"><i class="fas fa-moon"></i></button><?php
}

function hp_render_scroll_top(array $sec): void {
    ?><button id="scrollTop" class="scroll-top" title="Scroll to Top"><i class="fas fa-arrow-up"></i></button><?php
}
