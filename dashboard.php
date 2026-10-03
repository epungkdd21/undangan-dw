<?php
session_start();

$dashboardEmail = getenv('UNDANGAN_DASHBOARD_EMAIL');
$dashboardPassword = getenv('UNDANGAN_DASHBOARD_PASSWORD');
$dataPath = __DIR__ . '/invitees-store.php';
$settingsPath = __DIR__ . '/fonnte-settings.php';
$invitees = require $dataPath;
$invitees = is_array($invitees) ? $invitees : [];
$settings = is_file($settingsPath) ? require $settingsPath : [];
$settings = is_array($settings) ? $settings : [];
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

function saveFonnteSettings(string $path, array $settings): bool
{
  return file_put_contents($path, "<?php\nreturn " . var_export($settings, true) . ";\n", LOCK_EX) !== false;
}

if (is_string($dashboardEmail) && $dashboardEmail !== '' && is_string($dashboardPassword) && $dashboardPassword !== '' && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $csrfToken = $_POST['csrf'] ?? '';
  if (!is_string($csrfToken) || !hash_equals($_SESSION['csrf'] ?? '', $csrfToken)) {
    $error = 'Sesi formulir tidak valid. Muat ulang halaman dan coba kembali.';
  } elseif (($_POST['action'] ?? '') === 'login') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    if (is_string($email) && is_string($password) && hash_equals($dashboardEmail, $email) && hash_equals($dashboardPassword, $password)) {
      session_regenerate_id(true);
      $_SESSION['dashboard_authenticated'] = true;
      header('Location: dashboard.php');
      exit;
    }
    $error = 'Email atau kata sandi tidak sesuai.';
  } elseif (!empty($_SESSION['dashboard_authenticated'])) {
    $action = $_POST['action'] ?? '';
    $name = trim(is_string($_POST['name'] ?? null) ? $_POST['name'] : '');

    if (in_array($action, ['add', 'update'], true) && ($name === '' || strlen($name) > 240)) {
      header('Location: dashboard.php?status=invalid');
      exit;
    }

    if ($action === 'add') {
      $phone = trim(is_string($_POST['phone'] ?? null) ? $_POST['phone'] : '');
      $invitees[] = ['id' => bin2hex(random_bytes(8)), 'name' => $name, 'phone' => $phone];
    } elseif ($action === 'update' || $action === 'delete') {
      $id = $_POST['id'] ?? '';
      if (is_string($id)) {
        foreach ($invitees as $index => $invitee) {
          if (isset($invitee['id']) && hash_equals($invitee['id'], $id)) {
            if ($action === 'update') {
              $invitees[$index]['name'] = $name;
              $invitees[$index]['phone'] = trim(is_string($_POST['phone'] ?? null) ? $_POST['phone'] : '');
            } else {
              array_splice($invitees, $index, 1);
            }
            break;
          }
        }
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
        if (isset($_POST['clear_fonnte_token'])) {
          $settings['token'] = '';
        } elseif ($submittedToken !== '') {
          $settings['token'] = $submittedToken;
        }

        if (!saveFonnteSettings($settingsPath, $settings)) {
          $error = 'Pengaturan tidak dapat disimpan. Periksa izin tulis folder aplikasi.';
        } elseif ($action === 'save-settings') {
          header('Location: dashboard.php?status=settings-saved');
          exit;
        } elseif ($fonnteToken === '' && $submittedToken === '') {
          $error = 'Masukkan token Fonnte terlebih dahulu.';
        } else {
          if ($submittedToken !== '' && !isset($_POST['clear_fonnte_token'])) {
            $fonnteToken = $submittedToken;
          } elseif (isset($_POST['clear_fonnte_token'])) {
            $fonnteToken = '';
          }

          if ($fonnteToken === '') {
            $error = 'Token Fonnte belum dikonfigurasi.';
          } else {
            $selectedIds = $_POST['selected'] ?? [];
            $selectedIds = is_array($selectedIds) ? array_filter($selectedIds, 'is_string') : [];
            if ($selectedIds === []) {
              header('Location: dashboard.php?status=no-selection');
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

            header('Location: dashboard.php?status=broadcasted&sent=' . $sentCount . '&failed=' . $failedCount . '&skipped=' . $skippedCount);
            exit;
          }
        }
      }
    }

    if (in_array($action, ['add', 'update', 'delete'], true)) {
      $stored = file_put_contents(
        $dataPath,
        "<?php\nreturn " . var_export(array_values($invitees), true) . ";\n",
        LOCK_EX
      );
      if ($stored === false) {
        $error = 'Data tidak dapat disimpan. Periksa izin tulis folder aplikasi.';
      } else {
        header('Location: dashboard.php?status=' . ($action === 'delete' ? 'deleted' : 'saved'));
        exit;
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
    <a class="dashboard-brand" href="index.php">DWIPANTARA <span>2026</span></a>
    <?php if ($authenticated): ?>
      <form method="post" class="logout-form">
        <input type="hidden" name="csrf" value="<?= $csrf ?>">
        <button type="submit" name="action" value="logout">Keluar</button>
      </form>
    <?php endif; ?>
  </header>

  <main class="dashboard-main">
    <?php if (!is_string($dashboardEmail) || $dashboardEmail === '' || !is_string($dashboardPassword) || $dashboardPassword === ''): ?>
      <section class="dashboard-message">
        <p class="dashboard-kicker">PENGATURAN DIPERLUKAN</p>
        <h1>Dashboard belum diaktifkan</h1>
        <p>Atur environment variable <code>UNDANGAN_DASHBOARD_EMAIL</code> dan <code>UNDANGAN_DASHBOARD_PASSWORD</code> di hosting, lalu muat ulang halaman ini.</p>
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

      <section class="broadcast-panel">
        <div class="broadcast-heading">
          <div>
            <p class="dashboard-kicker">WHATSAPP</p>
            <h2>Broadcast undangan</h2>
          </div>
          <span class="fonnte-status <?= $fonnteToken !== '' ? 'is-configured' : '' ?>">
            <?= $fonnteToken !== '' ? 'Fonnte terhubung' : 'Token belum diatur' ?>
          </span>
        </div>
        <form method="post" id="broadcast-form" class="broadcast-form">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <label for="message-template">Isi pesan</label>
          <textarea id="message-template" name="message" rows="4" maxlength="8000" required><?= $escape($messageTemplate) ?></textarea>
          <p class="field-hint">Gunakan <code>{nama}</code> untuk nama penerima dan <code>{link}</code> untuk tautan personal undangan.</p>
          <label for="fonnte-token">Token API Fonnte</label>
          <input id="fonnte-token" name="fonnte_token" type="password" autocomplete="new-password" placeholder="<?= $fonnteToken !== '' ? 'Token tersimpan; isi hanya untuk mengganti' : 'Tempel token API Fonnte' ?>">
          <?php if ($savedFonnteToken !== ''): ?>
            <label class="clear-token"><input type="checkbox" name="clear_fonnte_token" value="1"> Hapus token tersimpan</label>
          <?php endif; ?>
          <div class="broadcast-actions">
            <button type="submit" name="action" value="save-settings" class="secondary-button">Simpan pengaturan</button>
            <button type="submit" name="action" value="broadcast" class="primary-button" <?= $fonnteToken === '' ? 'disabled' : '' ?>>Kirim ke tamu terpilih</button>
          </div>
        </form>
      </section>

      <section class="guest-list" aria-label="Daftar tamu undangan">
        <?php if ($invitees === []): ?>
          <p class="empty-guests">Belum ada nama tamu. Tambahkan nama pertama di atas.</p>
        <?php else: ?>
          <label class="select-all"><input type="checkbox" id="select-all"> Pilih semua tamu</label>
          <?php foreach ($invitees as $invitee):
            $id = (string) ($invitee['id'] ?? '');
            $sharePath = 'index.php?to=' . rawurlencode($id);
          ?>
            <article class="guest-row">
              <label class="guest-select">
                <input type="checkbox" name="selected[]" value="<?= $escape($id) ?>" form="broadcast-form">
                <span class="visually-hidden">Pilih <?= $escape($invitee['name'] ?? 'tamu') ?></span>
              </label>
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
      <script>
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