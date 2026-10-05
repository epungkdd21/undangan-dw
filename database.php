<?php

function saveAppSetting(PDO $database, string $key, string $value): bool
{
  try {
    $statement = $database->prepare(
      'INSERT INTO app_settings (setting_key, setting_value) VALUES (:key, :value)
       ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value'
    );
    return $statement->execute([':key' => $key, ':value' => $value]);
  } catch (PDOException) {
    return false;
  }
}

function sendAppSecurityHeaders(bool $noStore = false): void
{
  header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: DENY');
  header('Referrer-Policy: no-referrer');
  header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
  if ($noStore) {
    header('Cache-Control: no-store, private');
  }
}

function appDatabase(): PDO
{
  static $database = null;
  if ($database instanceof PDO) {
    return $database;
  }

  $configuredPath = getenv('UNDANGAN_DB_PATH');
  $databasePath = is_string($configuredPath) && $configuredPath !== ''
    ? $configuredPath
    : dirname(__DIR__) . '/undangan-dw.sqlite';
  $databaseDirectory = dirname($databasePath);
  if (!is_dir($databaseDirectory)) {
    mkdir($databaseDirectory, 0700, true);
    chmod($databaseDirectory, 0700);
  }

  $originalUmask = umask(0077);
  try {
    $database = new PDO('sqlite:' . $databasePath);
  } finally {
    umask($originalUmask);
  }

  $database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $database->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  $database->exec('PRAGMA foreign_keys = ON');
  chmod($databasePath, 0600);

  $database->exec(
    'CREATE TABLE IF NOT EXISTS invitees (
      id TEXT PRIMARY KEY,
      name TEXT NOT NULL,
      phone TEXT NOT NULL DEFAULT \'\'
    )'
  );
  $database->exec(
    'CREATE TABLE IF NOT EXISTS app_settings (
      setting_key TEXT PRIMARY KEY,
      setting_value TEXT NOT NULL
    )'
  );
  $database->exec(
    'CREATE TABLE IF NOT EXISTS broadcast_history (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      time TEXT NOT NULL,
      targets INTEGER NOT NULL DEFAULT 0,
      sent INTEGER NOT NULL DEFAULT 0,
      failed INTEGER NOT NULL DEFAULT 0,
      skipped INTEGER NOT NULL DEFAULT 0
    )'
  );
  $database->exec(
    'CREATE TABLE IF NOT EXISTS dashboard_users (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      email TEXT NOT NULL UNIQUE,
      password_hash TEXT NOT NULL,
      created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )'
  );

  $schemaVersion = (int) $database->query('PRAGMA user_version')->fetchColumn();
  if ($schemaVersion < 1) {
    $database->beginTransaction();
    try {
      $legacyInvitees = is_file(__DIR__ . '/invitees-store.php')
        ? require __DIR__ . '/invitees-store.php'
        : [];
      if (is_array($legacyInvitees)) {
        $insertInvitee = $database->prepare(
          'INSERT OR IGNORE INTO invitees (id, name, phone) VALUES (:id, :name, :phone)'
        );
        foreach ($legacyInvitees as $invitee) {
          if (!is_array($invitee) || !isset($invitee['id'], $invitee['name'])) {
            continue;
          }
          $insertInvitee->execute([
            ':id' => (string) $invitee['id'],
            ':name' => (string) $invitee['name'],
            ':phone' => (string) ($invitee['phone'] ?? ''),
          ]);
        }
      }

      $legacySettings = is_file(__DIR__ . '/fonnte-settings.php')
        ? require __DIR__ . '/fonnte-settings.php'
        : [];
      if (is_array($legacySettings)) {
        $insertSetting = $database->prepare(
          'INSERT OR IGNORE INTO app_settings (setting_key, setting_value) VALUES (:key, :value)'
        );
        foreach ($legacySettings as $key => $value) {
          if (is_scalar($value)) {
            $insertSetting->execute([':key' => (string) $key, ':value' => (string) $value]);
          }
        }
      }

      $legacyHistory = is_file(__DIR__ . '/broadcast-history.php')
        ? require __DIR__ . '/broadcast-history.php'
        : [];
      if (is_array($legacyHistory)) {
        $insertHistory = $database->prepare(
          'INSERT INTO broadcast_history (time, targets, sent, failed, skipped)
           VALUES (:time, :targets, :sent, :failed, :skipped)'
        );
        foreach ($legacyHistory as $entry) {
          if (!is_array($entry)) {
            continue;
          }
          $insertHistory->execute([
            ':time' => (string) ($entry['time'] ?? ''),
            ':targets' => (int) ($entry['targets'] ?? 0),
            ':sent' => (int) ($entry['sent'] ?? 0),
            ':failed' => (int) ($entry['failed'] ?? 0),
            ':skipped' => (int) ($entry['skipped'] ?? 0),
          ]);
        }
      }

      $database->exec('PRAGMA user_version = 1');
      $database->commit();
    } catch (Throwable $error) {
      $database->rollBack();
      throw $error;
    }
  }

  $userCount = (int) $database->query('SELECT COUNT(*) FROM dashboard_users')->fetchColumn();
  $bootstrapEmail = getenv('UNDANGAN_DASHBOARD_EMAIL');
  $bootstrapPassword = getenv('UNDANGAN_DASHBOARD_PASSWORD');
  if (
    $userCount === 0
    && is_string($bootstrapEmail) && $bootstrapEmail !== ''
    && is_string($bootstrapPassword) && $bootstrapPassword !== ''
  ) {
    $createAdmin = $database->prepare(
      'INSERT INTO dashboard_users (email, password_hash) VALUES (:email, :password_hash)'
    );
    $createAdmin->execute([
      ':email' => $bootstrapEmail,
      ':password_hash' => password_hash($bootstrapPassword, PASSWORD_DEFAULT),
    ]);
  }

  return $database;
}