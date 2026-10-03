<?php
session_start();

require_once __DIR__ . '/database.php';
$database = appDatabase();
$invitees = $database->query('SELECT id, name, phone FROM invitees ORDER BY rowid')->fetchAll();
$settings = $database->query('SELECT setting_key, setting_value FROM app_settings')->fetchAll(PDO::FETCH_KEY_PAIR);
$history = $database->query('SELECT time, targets, sent, failed, skipped FROM broadcast_history ORDER BY id')->fetchAll();
$dashboardUserCount = (int) $database->query('SELECT COUNT(*) FROM dashboard_users')->fetchColumn();
$messageTemplate = $settings['message'] ?? 'Assalamu\'alaikum. Yth. {nama}, kami mengundang Anda pada acara DWIPANTARA 2026. Silakan buka undangan: {link}';
$savedFonnteToken = $settings['token'] ?? '';
$environmentFonnteToken = getenv('FONNTE_TOKEN');
$fonnteToken = is_string($environmentFonnteToken) && $environmentFonnteToken !== ''
  ? $environmentFonnteToken
  : $savedFonnteToken;
$error = '';
$statusMessages = [
  'saved' => 'Perubahan berhasil disimpan.',
  'settings-saved' => 'Pengaturan pesan berhasil disimpan.',
  'deleted' => 'Nama tamu berhasil dihapus.',
  'invalid' => 'Nama wajib diisi dan maksimal 120 karakter.',
  'no-selection' => 'Pilih minimal satu tamu untuk broadcast.',
];
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$notice = $statusMessages[$status] ?? '';
if ($status === 'broadcasted') {
  $sent = (int) ($_GET['sent'] ?? 0);
  $failed = (int) ($_GET['failed'] ?? 0);
  $skipped = (int) ($_GET['skipped'] ?? 0);
  $notice = "Broadcast selesai: $sent terkirim, $failed gagal, $skipped dilewati.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrfToken = $_POST['csrf'] ?? '';
  if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf'] ?? '', $csrfToken)) {
    $error = 'Sesi formulir tidak valid. Muat ulang halaman dan coba kembali.';
  } elseif (($_POST['action'] ?? '') === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $findUser = $database->prepare('SELECT id, password_hash FROM dashboard_users WHERE email = :email');
    $findUser->execute([':email' => is_string($email) ? $email : '']);
    $dashboardUser = $findUser->fetch();
    if (is_string($email) && is_string($password) && $dashboardUser && password_verify($password, $dashboardUser['password_hash'])) {
      session_regenerate_id(true);
      $_SESSION['dashboard_authenticated'] = true;
      $_SESSION['dashboard_user_id'] = (int) $dashboardUser['id'];
      if (password_needs_rehash($dashboardUser['password_hash'], PASSWORD_DEFAULT)) {
        $updateHash = $database->prepare('UPDATE dashboard_users SET password_hash = :password_hash WHERE id = :id');
        $updateHash->execute([
          ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
          ':id' => $dashboardUser['id'],
        ]);
      }
      header('Location: dashboard.php');
      exit;
    }
    $error = 'Email atau kata sandi tidak sesuai.';
  } elseif (!empty($_SESSION['dashboard_authenticated'])) {
    $action = $_POST['action'] ?? '';
    $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');

    if (in_array($action, ['add', 'update'], true) && ($name === '' || strlen($name) > 120)) {
      header('Location: dashboard.php?status=invalid');
      exit;
    }

    if ($action === 'add') {
      $phone = trim(is_string($_POST['phone'] ?? null) ? $_POST['phone'] : '');
      $insertInvitee = $database->prepare('INSERT INTO invitees (id, name, phone) VALUES (:id, :name, :phone)');
      $insertInvitee->execute([':id' => bin2hex(random_bytes(8)), ':name' => $name, ':phone' => $phone]);
      header('Location: dashboard.php?status=saved#kontak');
      exit;
    } elseif ($action === 'update' || $action === 'delete') {
      $id = $_POST['id'] ?? '';
      if (is_string($id)) {
        if ($action === 'update') {
          $updateInvitee = $database->prepare('UPDATE invitees SET name = :name, phone = :phone WHERE id = :id');
          $updateInvitee->execute([
            ':name' => $name,
            ':phone' => trim(is_string($_POST['phone'] ?? null) ? $_POST['phone'] : ''),
            ':id' => $id,
          ]);
        } else {
          $deleteInvitee = $database->prepare('DELETE FROM invitees WHERE id = :id');
          $deleteInvitee->execute([':id' => $id]);
        }
        header('Location: dashboard.php?status=' . ($action === 'delete' ? 'deleted' : 'saved') . '#kontak');
        exit;
      }
    } elseif ($action === 'logout') {
      $_SESSION = [];
      session_destroy();
      header('Location: dashboard.php');
      exit;
    } elseif ($action === 'save-settings' || $action === 'broadcast') {
      $messageTemplate = trim(is_string($_POST['message'] ?? null) ? $_POST['message'] : '');
      if ($messageTemplate === '' || strlen($messageTemplate) > 8000) {
        $error = 'Pesan wajib diisi dan maksimal 8.000 karakter.';
      } else {
        $settings['message'] = $messageTemplate;
        $submittedToken = trim(is_string($_POST['fonnte_token'] ?? null) ? $_POST['fonnte_token'] : '');
        $settingsSaved = saveAppSetting($database, 'message', $messageTemplate);
        if (isset($_POST['clear_fonnte_token'])) {
          $settings['token'] = '';
          $settingsSaved = saveAppSetting($database, 'token', '') && $settingsSaved;
        } elseif ($submittedToken !== '') {
          $settings['token'] = $submittedToken;
          $settingsSaved = saveAppSetting($database, 'token', $submittedToken) && $settingsSaved;
        }
        $savedFonnteToken = $settings['token'] ?? '';
        $environmentFonnteToken = getenv('FONNTE_TOKEN');
        $fonnteToken = is_string($environmentFonnteToken) && $environmentFonnteToken !== ''
          ? $environmentFonnteToken
          : $savedFonnteToken;

        if (!$settingsSaved) {
          $error = 'Pengaturan tidak dapat disimpan. Periksa izin tulis database SQLite.';
        } elseif ($action === 'save-settings') {
          header('Location: dashboard.php?status=settings-saved#settings');
          exit;
        } elseif ($fonnteToken === '') {
          $error = 'Masukkan token Fonnte terlebih dahulu.';
        } else {
          if ($fonnteToken !== '') {
            $selectedIds = $_POST['selected'] ?? [];
            $selectedIds = is_array($selectedIds) ? array_filter($selectedIds, 'is_string') : [];
            if ($selectedIds === []) {
              header('Location: dashboard.php?status=no-selection#broadcast');
              exit;
            }
            $sentCount = 0;
            $failedCount = 0;
            $skippedCount = 0;
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $basePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
            $baseUrl = rtrim($scheme . '://' . $host . $basePath, '/');

            foreach ($invitees as $invitee) {
              $id = (string) ($invitee['id'] ?? '');
              if (!in_array($id, $selectedIds, true)) {
                continue;
              }

              $phone = preg_replace('/\D+/', '', (string) ($invitee['phone'] ?? ''));
              if (str_starts_with($phone, '0')) {
                $phone = '62' . substr($phone, 1);
              } elseif (str_starts_with($phone, '8')) {
                $phone = '62' . $phone;
              }
              if (!preg_match('/^[0-9]{8,15}$/', $phone)) {
                $skippedCount++;
                continue;
              }

              $link = $baseUrl . '/index.php?to=' . rawurlencode($id);
              $message = str_replace(['{nama}', '{link}'], [(string) ($invitee['name'] ?? ''), $link], $messageTemplate);
              $request = curl_init('https://api.fonnte.com/send');
              curl_setopt_array($request, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => ['target' => $phone, 'message' => $message],
                CURLOPT_HTTPHEADER => ['Authorization: ' . $fonnteToken],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => 25,
              ]);
              $response = curl_exec($request);
              $httpCode = (int) curl_getinfo($request, CURLINFO_HTTP_CODE);
              $responseData = is_string($response) ? json_decode($response, true) : null;
              if ($response !== false && $httpCode >= 200 && $httpCode < 300 && is_array($responseData) && !empty($responseData['status'])) {
                $sentCount++;
              } else {
                $failedCount++;
              }
              curl_close($request);
            }

            $insertHistory = $database->prepare(
              'INSERT INTO broadcast_history (time, targets, sent, failed, skipped)
               VALUES (:time, :targets, :sent, :failed, :skipped)'
            );
            $insertHistory->execute([
              ':time' => date('Y-m-d H:i:s'),
              ':targets' => count($selectedIds),
              ':sent' => $sentCount,
              ':failed' => $failedCount,
              ':skipped' => $skippedCount,
            ]);

            header('Location: dashboard.php?status=broadcasted&sent=' . $sentCount . '&failed=' . $failedCount . '&skipped=' . $skippedCount . '#histori');
            exit;
          }
        }
      }
    }
  }
}

$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$csrf = htmlspecialchars($_SESSION['csrf'], ENT_QUOTES, 'UTF-8');
$authenticated = !empty($_SESSION['dashboard_authenticated']);
$escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#3e4938">
  <title>Kelola Undangan | DWIPANTARA 2026</title>
  <link rel="stylesheet" href="style.css">
</head>
<body class="dashboard-body">
  <header class="dashboard-header">
    <a class="dashboard-brand" href="index.php" aria-label="DWIPANTARA 2026">
      <img src="assets/image.png" alt="DWIPANTARA X Kyai Amin">
    </a>
    <?php if ($authenticated): ?>
      <form method="post" class="logout-form">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <button type="submit" name="action" value="logout">Keluar</button>
      </form>
    <?php endif; ?>
  </header>

  <main class="dashboard-main">
    <?php if ($dashboardUserCount === 0): ?>
      <section class="dashboard-message">
        <p class="dashboard-kicker">PENGATURAN DIPERLUKAN</p>
        <h1>Dashboard belum diaktifkan</h1>
        <p>Atur environment variable <code>UNDANGAN_DASHBOARD_EMAIL</code> dan <code>UNDANGAN_DASHBOARD_PASSWORD</code> untuk membuat akun admin pertama, lalu muat ulang halaman ini.</p>
      </section>
    <?php elseif (!$authenticated): ?>
      <section class="dashboard-message login-panel">
        <p class="dashboard-kicker">AREA PENGELOLA</p>
        <h1>Kelola daftar undangan</h1>
        <p>Masukkan email dan kata sandi dashboard untuk melanjutkan.</p>
        <?php if ($error !== ''): ?><p class="form-error"><?= $escape($error) ?></p><?php endif; ?>
        <form method="post" class="login-form">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="action" value="login">
          <label for="email">Email</label>
          <input id="email" name="email" type="email" autocomplete="username" required>
          <label for="password">Kata sandi</label>
          <input id="password" name="password" type="password" autocomplete="current-password" required>
          <button type="submit">Masuk</button>
        </form>
      </section>
    <?php else: ?>
      <section class="dashboard-title-row">
        <div>
          <p class="dashboard-kicker">MANAJEMEN TAMU</p>
          <h1>Daftar undangan</h1>
        </div>
        <p class="guest-count"><strong><?= count($invitees) ?></strong> nama terdaftar</p>
      </section>

      <?php if ($notice !== ''): ?><p class="dashboard-notice"><?= $escape($notice) ?></p><?php endif; ?>
      <?php if ($error !== ''): ?><p class="form-error"><?= $escape($error) ?></p><?php endif; ?>

      <section class="dashboard-overview" aria-label="Ringkasan dashboard">
        <article class="stat-card">
          <span class="stat-label">Kontak</span>
          <strong><?= count($invitees) ?></strong>
          <small>Total tamu</small>
        </article>
        <article class="stat-card">
          <span class="stat-label">Broadcast</span>
          <strong><?= array_sum(array_map(fn($entry) => (int) ($entry['sent'] ?? 0), $history)) ?></strong>
          <small>Pesan terkirim</small>
        </article>
        <article class="stat-card">
          <span class="stat-label">Histori</span>
          <strong><?= count($history) ?></strong>
          <small>Riwayat pengiriman</small>
        </article>
        <article class="stat-card">
          <span class="stat-label">Status</span>
          <strong><?= $fonnteToken !== '' ? 'Aktif' : 'Kosong' ?></strong>
          <small>API Fonnte</small>
        </article>
      </section>

      <nav class="section-menu" aria-label="Menu dashboard">
        <a href="#kontak" class="active">Kontak</a>
        <a href="#broadcast">Broadcast</a>
        <a href="#histori">Histori</a>
        <a href="#settings">Settingan</a>
      </nav>

      <div class="dashboard-shell">
        <div class="dashboard-content">
          <section id="kontak" class="panel-section">
            <div class="section-header">
              <div>
                <p class="dashboard-kicker">DATA KONTAK</p>
                <h2>Kelola daftar tamu</h2>
              </div>
            </div>

            <form method="post" class="add-guest-form">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="add">
              <label for="new-name">Tambah penerima undangan</label>
              <div class="add-guest-controls">
                <input id="new-name" name="name" type="text" maxlength="120" placeholder="Contoh: Bapak Ahmad dan keluarga" required>
                <input id="new-phone" name="phone" type="tel" placeholder="WhatsApp, contoh: 62812...">
                <button type="submit">Tambah tamu</button>
              </div>
            </form>

            <section class="guest-list" aria-label="Daftar tamu undangan">
              <?php if ($invitees === []): ?>
                <p class="empty-guests">Belum ada nama tamu. Tambahkan nama pertama di atas.</p>
              <?php else: ?>
                <div class="table-toolbar">
                  <label class="select-all"><input type="checkbox" id="select-all"> Pilih semua tamu</label>
                </div>
                <?php foreach ($invitees as $invitee):
                  $id = (string) ($invitee['id'] ?? '');
                  $sharePath = 'index.php?to=' . rawurlencode($id);
                ?>
                  <article class="guest-row">
                    <label class="guest-select">
                      <input type="checkbox" name="selected[]" value="<?= $escape($id) ?>" form="broadcast-form">
                      <span class="visually-hidden">Pilih <?= $escape($invitee['name'] ?? 'tamu') ?></span>
                    </label>
                    <div class="guest-main">
                      <form method="post" class="edit-guest-form">
                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $escape($id) ?>">
                        <label class="visually-hidden" for="guest-<?= $escape($id) ?>">Nama tamu</label>
                        <input id="guest-<?= $escape($id) ?>" name="name" type="text" maxlength="120" value="<?= $escape($invitee['name'] ?? '') ?>" required>
                        <label class="visually-hidden" for="phone-<?= $escape($id) ?>">Nomor WhatsApp</label>
                        <input id="phone-<?= $escape($id) ?>" name="phone" type="tel" value="<?= $escape($invitee['phone'] ?? '') ?>" placeholder="62812...">
                        <button type="submit" class="save-guest">Simpan</button>
                      </form>
                    </div>
                    <a class="guest-link" href="<?= $escape($sharePath) ?>" target="_blank" rel="noopener"><?= $escape($sharePath) ?></a>
                    <form method="post" class="delete-guest-form" onsubmit="return confirm('Hapus nama tamu ini?')">
                      <input type="hidden" name="csrf" value="<?= $csrf ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="id" value="<?= $escape($id) ?>">
                      <button type="submit" aria-label="Hapus <?= $escape($invitee['name'] ?? 'tamu') ?>">Hapus</button>
                    </form>
                  </article>
                <?php endforeach; ?>
              <?php endif; ?>
            </section>
          </section>

          <section id="broadcast" class="panel-section">
            <div class="section-header">
              <div>
                <p class="dashboard-kicker">BROADCAST</p>
                <h2>Kirim undangan ke WhatsApp</h2>
              </div>
              <span class="fonnte-status <?= $fonnteToken !== '' ? 'is-configured' : '' ?>">
                <?= $fonnteToken !== '' ? 'Fonnte terhubung' : 'Token belum diatur' ?>
              </span>
            </div>

            <form method="post" id="broadcast-form" class="broadcast-form">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="broadcast">
              <label for="message-template">Isi pesan</label>
              <textarea id="message-template" name="message" rows="4" maxlength="8000" required><?= $escape($messageTemplate) ?></textarea>
              <p class="field-hint">Gunakan <code>{nama}</code> untuk nama penerima dan <code>{link}</code> untuk tautan personal undangan.</p>
              <div class="broadcast-actions">
                <button type="submit" class="primary-button" <?= $fonnteToken === '' ? 'disabled' : '' ?>>Kirim ke tamu terpilih</button>
              </div>
            </form>
          </section>

          <section id="histori" class="panel-section">
            <div class="section-header">
              <div>
                <p class="dashboard-kicker">HISTORI</p>
                <h2>Riwayat broadcast</h2>
              </div>
            </div>

            <div class="history-list">
              <?php if ($history === []): ?>
                <p class="empty-guests">Belum ada histori broadcast.</p>
              <?php else: ?>
                <?php foreach (array_reverse($history) as $entry): ?>
                  <article class="history-item">
                    <div class="history-meta">
                      <strong><?= htmlspecialchars((string) ($entry['time'] ?? 'Belum ada waktu'), ENT_QUOTES, 'UTF-8') ?></strong>
                      <span><?= (int) ($entry['sent'] ?? 0) ?> terkirim</span>
                    </div>
                    <div class="history-stats">
                      <span><?= (int) ($entry['targets'] ?? 0) ?> target</span>
                      <span><?= (int) ($entry['failed'] ?? 0) ?> gagal</span>
                      <span><?= (int) ($entry['skipped'] ?? 0) ?> dilewati</span>
                    </div>
                  </article>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </section>

          <section id="settings" class="panel-section">
            <div class="section-header">
              <div>
                <p class="dashboard-kicker">SETTINGAN</p>
                <h2>Konfigurasi Fonnte</h2>
              </div>
            </div>

            <form method="post" class="settings-form">
              <input type="hidden" name="csrf" value="<?= $csrf ?>">
              <input type="hidden" name="action" value="save-settings">
              <label for="fonnte-token">Token API Fonnte</label>
              <input id="fonnte-token" name="fonnte_token" type="password" autocomplete="new-password" placeholder="<?= $fonnteToken !== '' ? 'Token tersimpan; isi hanya untuk mengganti' : 'Tempel token API Fonnte' ?>">
              <?php if ($savedFonnteToken !== ''): ?>
                <label class="clear-token"><input type="checkbox" name="clear_fonnte_token" value="1"> Hapus token tersimpan</label>
              <?php endif; ?>
              <label for="settings-message">Template pesan</label>
              <textarea id="settings-message" name="message" rows="4" maxlength="8000" required><?= $escape($messageTemplate) ?></textarea>
              <div class="timeline-actions">
                <button type="submit" class="secondary-button">Simpan pengaturan</button>
              </div>
            </form>
          </section>
        </div>
      </div>

      <script>
        const sectionMenu = document.querySelector('.section-menu');
        const menuLinks = [...sectionMenu.querySelectorAll('a')];
        const panels = [...document.querySelectorAll('.dashboard-content > .panel-section')];

        const showPanel = (hash) => {
          const activePanel = panels.find((panel) => `#${panel.id}` === hash) ?? panels[0];

          panels.forEach((panel) => {
            panel.hidden = panel !== activePanel;
          });

          menuLinks.forEach((link) => {
            const isActive = link.hash === `#${activePanel.id}`;
            link.classList.toggle('active', isActive);
            if (isActive) {
              link.setAttribute('aria-current', 'page');
            } else {
              link.removeAttribute('aria-current');
            }
          });
        };

        menuLinks.forEach((link) => {
          link.addEventListener('click', (event) => {
            event.preventDefault();
            history.pushState(null, '', link.hash);
            showPanel(link.hash);
          });
        });

        window.addEventListener('popstate', () => showPanel(window.location.hash));
        showPanel(window.location.hash);

        const setupPagination = (list, itemSelector, label) => {
          if (!list) return;

          const items = [...list.querySelectorAll(itemSelector)];
          const pageSize = 10;
          const pageCount = Math.ceil(items.length / pageSize);
          if (pageCount <= 1) return;

          let currentPage = 1;
          const pagination = document.createElement('nav');
          pagination.className = 'list-pagination';
          pagination.setAttribute('aria-label', label);

          const previousButton = document.createElement('button');
          previousButton.type = 'button';
          previousButton.textContent = 'Sebelumnya';

          const pageStatus = document.createElement('span');
          pageStatus.setAttribute('aria-live', 'polite');

          const nextButton = document.createElement('button');
          nextButton.type = 'button';
          nextButton.textContent = 'Selanjutnya';

          pagination.append(previousButton, pageStatus, nextButton);

          const updatePage = () => {
            const firstItem = (currentPage - 1) * pageSize;
            items.forEach((item, index) => {
              item.hidden = index < firstItem || index >= firstItem + pageSize;
            });
            previousButton.disabled = currentPage === 1;
            nextButton.disabled = currentPage === pageCount;
            pageStatus.textContent = `Halaman ${currentPage} dari ${pageCount}`;
          };

          previousButton.addEventListener('click', () => {
            currentPage--;
            updatePage();
          });
          nextButton.addEventListener('click', () => {
            currentPage++;
            updatePage();
          });

          list.after(pagination);
          updatePage();
        };

        setupPagination(document.querySelector('.guest-list'), '.guest-row', 'Halaman daftar kontak');
        setupPagination(document.querySelector('.history-list'), '.history-item', 'Halaman histori broadcast');

        document.getElementById('select-all')?.addEventListener('change', (event) => {
          document.querySelectorAll('.guest-select input').forEach((checkbox) => {
            checkbox.checked = event.target.checked;
          });
        });
      </script>
    <?php endif; ?>
  </main>
</body>
</html>