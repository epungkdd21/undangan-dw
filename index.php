<?php
$inviteeName = '';
$inviteeId = $_GET['to'] ?? '';
$invitees = require __DIR__ . '/invitees-store.php';

if (is_string($inviteeId)) {
  foreach ($invitees as $invitee) {
    if (isset($invitee['id']) && hash_equals($invitee['id'], $inviteeId)) {
      $inviteeName = $invitee['name'];
      break;
    }
  }
}

$escapedInviteeName = htmlspecialchars($inviteeName, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#e9e5d8">
  <title>Undangan DWIPANTARA 2026</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>

<section class="opening" id="opening">
  <div class="opening-content">
    <p class="eyebrow">UNDANGAN DIGITAL</p>
    <h1>DWIPANTARA 2026</h1>
    <p class="opening-subtitle">50TH MILAD KYAI AMIN</p>
    <?php if ($inviteeName !== ''): ?>
      <p class="recipient-label">Kepada Yth.<br><strong><?= $escapedInviteeName ?></strong></p>
    <?php endif; ?>

    <div class="envelope-scene" id="envelopeScene">
      <!-- Ini adalah kertas yang terlihat putih di dalam amplop,
           tetapi isinya adalah halaman invitation. -->
      <div class="paper" id="paper">
        <div class="paper-sheet">
          <img src="assets/invitation.jpeg" alt="Halaman invitation DWIPANTARA 2026">
        </div>
      </div>

      <!-- Amplop tertutup -->
      <img class="envelope envelope-closed"
           src="assets/envelope-closed.png"
           alt="Amplop tertutup">

      <!-- Amplop terbuka tanpa background foto -->
      <img class="envelope envelope-open-paper"
           src="assets/envelope-open-paper.png"
           alt="Amplop terbuka dengan kertas">

      <button class="envelope-button" id="envelopeButton" aria-label="Buka undangan"></button>
    </div>

    <p class="tap-text">KLIK AMPLOP UNTUK MEMBUKA</p>
  </div>
</section>

<main class="invitation" id="invitation">
  <!-- Urutan halaman: invitation terlebih dahulu, kemudian info -->
  <?php if ($inviteeName !== ''): ?>
    <p class="recipient-banner">Undangan khusus untuk <strong><?= $escapedInviteeName ?></strong></p>
  <?php endif; ?>
  <section class="invite-page">
    <img src="assets/invitation.jpeg" alt="Invitation DWIPANTARA 2026">
  </section>

  <section class="invite-page">
    <img src="assets/info.jpeg" alt="Informasi penting DWIPANTARA 2026">
  </section>

  <footer class="invite-footer">
    <button id="backToTop">Kembali ke Atas ↑</button>
  </footer>
</main>

<script src="script.js"></script>
</body>
</html>
