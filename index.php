<?php
$inviteeName = '';
$inviteeId = $_GET['to'] ?? '';
require_once __DIR__ . '/database.php';
$database = appDatabase();

if (is_string($inviteeId) && $inviteeId !== '') {
  $findInvitee = $database->prepare('SELECT name FROM invitees WHERE id = :id');
  $findInvitee->execute([':id' => $inviteeId]);
  $inviteeName = (string) ($findInvitee->fetchColumn() ?: '');
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
    <div class="brand-lockup" aria-label="DWIPANTARA X Kyai Amin">
      <img class="brand-logo" src="assets/image.png" alt="DWIPANTARA X Kyai Amin">
    </div>
    
    <div class="envelope-scene" id="envelopeScene">
      <!-- Ini adalah kertas yang terlihat putih di dalam amplop,
           tetapi isinya adalah halaman invitation. -->
      <div class="paper" id="paper">
        <div class="paper-sheet">
          <div class="invite-art">
            <img src="assets/invitation.png" alt="Halaman invitation DWIPANTARA 2026">
            <div class="invite-overlay">
              <div class="invite-kicker">Bapak/Ibu/Kaka</div>
              <div class="invite-name"><?= $escapedInviteeName !== '' ? $escapedInviteeName : 'Tamu Undangan' ?></div>
            </div>
          </div>
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

      <?php if ($inviteeName !== ''): ?>
       <p class="recipient-label">Kepada Yth.<br><strong><?= $escapedInviteeName ?></strong></p>
      <?php endif; ?>

      <button class="envelope-button" id="envelopeButton" aria-label="Buka undangan"></button>
    </div>

    <p class="tap-text">KLIK AMPLOP UNTUK MEMBUKA</p>
  </div>
</section>

<main class="invitation" id="invitation">
  <!-- Urutan halaman: invitation terlebih dahulu, kemudian info 
  <?php if ($inviteeName !== ''): ?>
    <p class="recipient-banner">Undangan khusus untuk <strong><?= $escapedInviteeName ?></strong></p>
  <?php endif; ?>-->
  <section class="invite-page">
    <div class="invite-art invite-art-full">
      <img src="assets/invitation.png" alt="Invitation DWIPANTARA 2026">
      <div class="invite-overlay">
        <div class="invite-logo">Bapak/Ibu/Kaka</div>
        <div class="invite-name"><?= $escapedInviteeName !== '' ? $escapedInviteeName : 'Tamu Undangan' ?></div>
      </div>
    </div>
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
