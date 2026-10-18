<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/includes/app.php';

function prompt(string $label, bool $hidden = false): string
{
    fwrite(STDOUT, $label);
    if ($hidden && stripos(PHP_OS, 'WIN') !== 0) {
        system('stty -echo');
    }
    $value = trim((string) fgets(STDIN));
    if ($hidden && stripos(PHP_OS, 'WIN') !== 0) {
        system('stty echo');
        fwrite(STDOUT, PHP_EOL);
    }
    return $value;
}

$name = prompt('Administrator full name: ');
$phone = normalize_phone(prompt('Administrator phone: '));
$password = prompt('Temporary password (12+ characters): ', true);

if ($name === '' || strlen($phone) < 10 || strlen($password) < 12) {
    fwrite(STDERR, "A name, valid phone, and password of at least 12 characters are required.\n");
    exit(1);
}

$pdo->beginTransaction();
try {
    $exists = $pdo->prepare('SELECT id FROM users WHERE phone = ?');
    $exists->execute([$phone]);
    if ($exists->fetchColumn()) {
        throw new RuntimeException('An account already uses that phone number.');
    }

    $user = $pdo->prepare(
        "INSERT INTO users (phone, password_hash, role, account_status, must_change_password)
         VALUES (?, ?, 'admin', 'active', 1)"
    );
    $user->execute([$phone, password_hash($password, PASSWORD_DEFAULT)]);
    $userId = (int) $pdo->lastInsertId();

    $prefix = $pdo->query("SELECT setting_value FROM app_settings WHERE setting_key = 'membership_number_prefix'")->fetchColumn() ?: 'KSSHG';
    $next = (int) $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM members FOR UPDATE")->fetchColumn();
    $number = $prefix . '-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    $joined = date('Y-m-d');
    $firstMonth = date('Y-m-01', strtotime('first day of next month'));

    $member = $pdo->prepare(
        "INSERT INTO members
         (user_id, membership_number, full_name, date_joined, first_contribution_month,
          membership_status, approved_by, approved_at)
         VALUES (?, ?, ?, ?, ?, 'Active', ?, NOW())"
    );
    $member->execute([$userId, $number, $name, $joined, $firstMonth, $userId]);
    $pdo->commit();
    echo "Created administrator {$number}. A password change is required at first login.\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
