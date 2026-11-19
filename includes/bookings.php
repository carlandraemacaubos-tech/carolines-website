<?php
/**
 * includes/bookings.php
 * All database work for bookings: create, verify, approve, reject, search, history.
 * Every query uses PDO prepared statements.
 */

// ---------------------------------------------------------------------------
// Create / find
// ---------------------------------------------------------------------------

/** Make a random token for the email link. Only its SHA-256 hash is stored in MySQL. */
function generate_verification_token(): array
{
    $token = bin2hex(random_bytes(32));                 // 64 hex characters, cryptographically secure
    return ['token' => $token, 'hash' => hash('sha256', $token)];
}

/** Friendly reference such as CPH-2026-K7M4QX (no confusing 0/O/1/I characters). */
function generate_booking_reference(): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    $code = '';
    for ($i = 0; $i < 6; $i++) {
        $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return 'CPH-' . date('Y') . '-' . $code;
}

/**
 * Save a new booking (status: pending_email) and return the booking row plus the raw token.
 * $clean comes from validate_booking_input().
 */
function booking_create(array $clean): array
{
    $pdo = db();
    $tokenData = generate_verification_token();
    $expires   = (new DateTimeImmutable('now'))->modify('+' . VERIFICATION_MINUTES . ' minutes')->format('Y-m-d H:i:s');

    $sql = 'INSERT INTO bookings
              (booking_reference, customer_name, email, phone, event_type, event_location, guests, help_needed,
               start_datetime, end_datetime, message, verification_token_hash, verification_expires_at,
               email_verified, booking_status)
            VALUES
              (:ref, :name, :email, :phone, :type, :location, :guests, :help,
               :start, :end, :message, :hash, :expires, 0, \'pending_email\')';
    $stmt = $pdo->prepare($sql);

    // The reference is random; in the (very unlikely) case of a clash, try again.
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $reference = generate_booking_reference();
        try {
            $stmt->execute([
                ':ref'      => $reference,
                ':name'     => $clean['name'],
                ':email'    => $clean['email'],
                ':phone'    => $clean['phone'],
                ':type'     => $clean['event_type'],
                ':location' => $clean['event_location'],
                ':guests'   => $clean['guests'],
                ':help'     => $clean['help'] ? implode(', ', $clean['help']) : null,
                ':start'    => $clean['start_datetime'],
                ':end'      => $clean['end_datetime'],
                ':message'  => $clean['message'] !== '' ? $clean['message'] : null,
                ':hash'     => $tokenData['hash'],
                ':expires'  => $expires,
            ]);
            $id = (int) $pdo->lastInsertId();
            booking_log($id, null, 'submitted', 'Booking request submitted by customer.');
            return ['booking' => booking_find($id), 'token' => $tokenData['token']];
        } catch (PDOException $e) {
            if ($e->getCode() !== '23000' || $attempt === 4) {   // 23000 = duplicate key
                throw $e;
            }
            $tokenData = generate_verification_token();           // new token too, just in case
            $stmt->bindValue(':hash', $tokenData['hash']);
        }
    }
    throw new RuntimeException('Could not create a booking reference.');
}

function booking_find(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM bookings WHERE id = :id');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

function booking_find_by_reference(string $reference): ?array
{
    $stmt = db()->prepare('SELECT * FROM bookings WHERE booking_reference = :ref');
    $stmt->execute([':ref' => $reference]);
    return $stmt->fetch() ?: null;
}

function booking_find_by_token(string $rawToken): ?array
{
    $stmt = db()->prepare('SELECT * FROM bookings WHERE verification_token_hash = :hash');
    $stmt->execute([':hash' => hash('sha256', $rawToken)]);
    return $stmt->fetch() ?: null;
}

/** Same person + same schedule already requested (and not rejected)? Blocks duplicate submissions. */
function booking_duplicate_exists(string $email, string $start, string $end): ?array
{
    $stmt = db()->prepare(
        "SELECT * FROM bookings
         WHERE email = :email AND start_datetime = :start AND end_datetime = :end
           AND booking_status <> 'rejected'
         LIMIT 1"
    );
    $stmt->execute([':email' => $email, ':start' => $start, ':end' => $end]);
    return $stmt->fetch() ?: null;
}

/** Create a fresh token (the old one stops working). Returns the raw token for the email link. */
function booking_issue_new_token(int $id): string
{
    $tokenData = generate_verification_token();
    $expires   = (new DateTimeImmutable('now'))->modify('+' . VERIFICATION_MINUTES . ' minutes')->format('Y-m-d H:i:s');
    db()->prepare(
        'UPDATE bookings SET verification_token_hash = :hash, verification_expires_at = :expires
         WHERE id = :id AND email_verified = 0'
    )->execute([':hash' => $tokenData['hash'], ':expires' => $expires, ':id' => $id]);
    return $tokenData['token'];
}

// ---------------------------------------------------------------------------
// Email verification
// ---------------------------------------------------------------------------

/**
 * Mark the email as verified and move the booking to pending_review.
 * The WHERE clause makes this safe if the link is clicked twice at the same time.
 * Returns true only for the request that actually changed the row.
 */
function booking_mark_verified(array $booking): bool
{
    $stmt = db()->prepare(
        "UPDATE bookings
            SET email_verified = 1, verified_at = :now, booking_status = 'pending_review',
                verification_token_hash = NULL, verification_expires_at = NULL
          WHERE id = :id AND email_verified = 0 AND booking_status = 'pending_email'
            AND verification_token_hash = :hash"
    );
    $stmt->execute([':now' => date('Y-m-d H:i:s'), ':id' => $booking['id'], ':hash' => $booking['verification_token_hash']]);
    if ($stmt->rowCount() !== 1) {
        return false;
    }
    booking_log((int) $booking['id'], null, 'verified', 'Customer verified their email address.');
    return true;
}

// ---------------------------------------------------------------------------
// Admin decisions
// ---------------------------------------------------------------------------

/** Approved bookings whose time range overlaps [$start, $end). */
function booking_conflicts(string $start, string $end, int $excludeId = 0): array
{
    $stmt = db()->prepare(
        "SELECT id, booking_reference, customer_name, start_datetime, end_datetime
           FROM bookings
          WHERE booking_status = 'approved' AND id <> :id
            AND start_datetime < :end AND end_datetime > :start
          ORDER BY start_datetime"
    );
    $stmt->execute([':id' => $excludeId, ':start' => $start, ':end' => $end]);
    return $stmt->fetchAll();
}

/**
 * Approve a booking. Checks: exists, email verified, still pending review, no overlapping approved booking.
 * A MySQL named lock makes sure two approvals can never run at the same moment (no double-booking).
 * Returns ['ok' => bool, 'message' => string, 'booking' => row|null].
 */
function booking_approve(int $id, int $adminId): array
{
    $pdo = db();
    $gotLock = (int) $pdo->query("SELECT GET_LOCK('party_booking_approval', 10)")->fetchColumn();
    if ($gotLock !== 1) {
        return ['ok' => false, 'message' => 'The system is busy. Please try again in a moment.', 'booking' => null];
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $booking = $stmt->fetch();

        $fail = function (string $message) use ($pdo): array {
            $pdo->rollBack();
            return ['ok' => false, 'message' => $message, 'booking' => null];
        };

        if (!$booking) {
            return $fail('Booking not found.');
        }
        if ((int) $booking['email_verified'] !== 1) {
            return $fail('This booking cannot be approved because the customer has not verified their email yet.');
        }
        if ($booking['booking_status'] !== 'pending_review') {
            return $fail('This booking is no longer pending review (current status: '
                . (BOOKING_STATUS_LABELS[$booking['booking_status']] ?? $booking['booking_status']) . ').');
        }

        $conflicts = booking_conflicts($booking['start_datetime'], $booking['end_datetime'], $id);
        if ($conflicts) {
            $c = $conflicts[0];
            return $fail('Schedule conflict: it overlaps with approved booking ' . $c['booking_reference']
                . ' (' . format_short($c['start_datetime']) . ' to ' . format_short($c['end_datetime']) . ').');
        }

        // The schedule must still be open (the administrator may have blocked the date or changed hours since the request).
        $ctx = avail_load(substr($booking['start_datetime'], 0, 10), substr($booking['end_datetime'], 0, 10));
        $problem = avail_check_schedule($ctx, $booking['start_datetime'], $booking['end_datetime'], date('Y-m-d H:i:s'),
            ['skip_past' => true, 'skip_grid' => true]);
        if ($problem) {
            return $fail('Cannot approve: this schedule is no longer open. ' . $problem['message']
                . ' Change the Availability calendar first if you still want to accept it.');
        }

        $upd = $pdo->prepare(
            "UPDATE bookings
                SET booking_status = 'approved', reviewed_by_admin_id = :admin, reviewed_at = :now
              WHERE id = :id AND booking_status = 'pending_review' AND email_verified = 1"
        );
        $upd->execute([':admin' => $adminId, ':now' => date('Y-m-d H:i:s'), ':id' => $id]);
        if ($upd->rowCount() !== 1) {
            return $fail('The booking could not be approved. Please refresh and try again.');
        }

        booking_log($id, $adminId, 'approved', 'Administrator approved the booking.');
        $pdo->commit();
        return ['ok' => true, 'message' => 'Booking approved.', 'booking' => booking_find($id)];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[PartyBooking] Approve failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Something went wrong while approving. Nothing was changed.', 'booking' => null];
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('party_booking_approval')");
    }
}

/** Reject a booking that is still pending review. $reason is optional and IS shown to the customer. */
function booking_reject(int $id, int $adminId, string $reason): array
{
    $pdo = db();
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('SELECT * FROM bookings WHERE id = :id FOR UPDATE');
        $stmt->execute([':id' => $id]);
        $booking = $stmt->fetch();

        if (!$booking) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Booking not found.', 'booking' => null];
        }
        if ($booking['booking_status'] !== 'pending_review') {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Only bookings that are pending review can be rejected (current status: '
                . (BOOKING_STATUS_LABELS[$booking['booking_status']] ?? $booking['booking_status']) . ').', 'booking' => null];
        }

        $upd = $pdo->prepare(
            "UPDATE bookings
                SET booking_status = 'rejected', rejection_reason = :reason,
                    reviewed_by_admin_id = :admin, reviewed_at = :now
              WHERE id = :id AND booking_status = 'pending_review'"
        );
        $upd->execute([
            ':reason' => $reason !== '' ? $reason : null,
            ':admin'  => $adminId,
            ':now'    => date('Y-m-d H:i:s'),
            ':id'     => $id,
        ]);
        if ($upd->rowCount() !== 1) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'The booking could not be rejected. Please refresh and try again.', 'booking' => null];
        }

        booking_log($id, $adminId, 'rejected', 'Administrator rejected the booking.');
        $pdo->commit();
        return ['ok' => true, 'message' => 'Booking rejected.', 'booking' => booking_find($id)];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('[PartyBooking] Reject failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Something went wrong while rejecting. Nothing was changed.', 'booking' => null];
    }
}

/** Save the private admin notes (customers never see these). */
function booking_save_notes(int $id, int $adminId, string $notes): bool
{
    $stmt = db()->prepare('UPDATE bookings SET admin_notes = :notes WHERE id = :id');
    $stmt->execute([':notes' => $notes !== '' ? $notes : null, ':id' => $id]);
    booking_log($id, $adminId, 'note_updated', 'Administrator notes were updated.');
    return true;
}

// ---------------------------------------------------------------------------
// History and dashboard queries
// ---------------------------------------------------------------------------

/** Record an action in booking_logs. */
function booking_log(int $bookingId, ?int $adminId, string $action, ?string $details = null): void
{
    db()->prepare('INSERT INTO booking_logs (booking_id, admin_id, action, details) VALUES (:b, :a, :action, :details)')
        ->execute([':b' => $bookingId, ':a' => $adminId, ':action' => $action, ':details' => $details]);
}

/** Counts for the dashboard cards. */
function booking_stats(): array
{
    $row = db()->query(
        "SELECT COUNT(*) AS total,
                SUM(booking_status = 'pending_email')  AS pending_email,
                SUM(booking_status = 'pending_review') AS pending_review,
                SUM(booking_status = 'approved')       AS approved,
                SUM(booking_status = 'rejected')       AS rejected,
                SUM(email_verified = 1)                AS verified
           FROM bookings"
    )->fetch();
    return array_map(fn($v) => (int) $v, $row);
}

/**
 * Search + filter + paginate.
 * $filters: q (text), status (all|pending_email|...), email (all|verified|unverified), date (YYYY-MM-DD)
 * Returns ['rows' => [...], 'total' => int].
 */
function booking_search(array $filters, int $page, int $perPage): array
{
    $where  = [];
    $params = [];

    $q = trim((string) ($filters['q'] ?? ''));
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $where[] = '(booking_reference LIKE :q1 OR customer_name LIKE :q2 OR email LIKE :q3 OR phone LIKE :q4 OR event_location LIKE :q5)';
        foreach (['q1', 'q2', 'q3', 'q4', 'q5'] as $key) {
            $params[':' . $key] = $like;
        }
    }

    $status = (string) ($filters['status'] ?? 'all');
    if (isset(BOOKING_STATUS_LABELS[$status])) {
        $where[] = 'booking_status = :status';
        $params[':status'] = $status;
    }

    $emailFilter = (string) ($filters['email'] ?? 'all');
    if ($emailFilter === 'verified') {
        $where[] = 'email_verified = 1';
    } elseif ($emailFilter === 'unverified') {
        $where[] = 'email_verified = 0';
    }

    // Bookings whose schedule touches the chosen date.
    $date = trim((string) ($filters['date'] ?? ''));
    if ($date !== '' && parse_date_strict($date)) {
        $where[] = '(start_datetime < :day_end AND end_datetime > :day_start)';
        $params[':day_start'] = $date . ' 00:00:00';
        $params[':day_end']   = date_add_days($date, 1) . ' 00:00:00';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $pdo = db();

    $count = $pdo->prepare("SELECT COUNT(*) FROM bookings $whereSql");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    // Bookings that need a decision come first.
    $sql = "SELECT * FROM bookings $whereSql
            ORDER BY (booking_status = 'pending_review') DESC, created_at DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, ($page - 1) * $perPage), PDO::PARAM_INT);
    $stmt->execute();

    return ['rows' => $stmt->fetchAll(), 'total' => $total];
}

/** History of one booking: actions + emails, newest first. */
function booking_history(int $bookingId): array
{
    $pdo = db();

    $logs = $pdo->prepare(
        'SELECT l.created_at, l.action, l.details, a.username
           FROM booking_logs l LEFT JOIN administrators a ON a.id = l.admin_id
          WHERE l.booking_id = :id'
    );
    $logs->execute([':id' => $bookingId]);

    $emails = $pdo->prepare('SELECT created_at, type, status, recipient, subject, error FROM email_logs WHERE booking_id = :id');
    $emails->execute([':id' => $bookingId]);

    $items = [];
    foreach ($logs->fetchAll() as $row) {
        $items[] = [
            'at'    => $row['created_at'],
            'kind'  => 'action',
            'title' => ucfirst(str_replace('_', ' ', $row['action'])),
            'text'  => trim(($row['details'] ?? '') . ($row['username'] ? ' (by ' . $row['username'] . ')' : '')),
            'ok'    => true,
        ];
    }
    foreach ($emails->fetchAll() as $row) {
        $ok = $row['status'] === 'sent';
        $items[] = [
            'at'    => $row['created_at'],
            'kind'  => 'email',
            'title' => 'Email ' . ($ok ? 'sent' : 'FAILED') . ' - ' . str_replace('_', ' ', $row['type']),
            'text'  => 'To ' . $row['recipient'] . ': ' . $row['subject'] . ($ok ? '' : ' (' . ($row['error'] ?? 'unknown error') . ')'),
            'ok'    => $ok,
        ];
    }
    usort($items, fn($a, $b) => strcmp($b['at'], $a['at']));
    return $items;
}

/** Latest actions across all bookings (dashboard "Recent activity"). */
function booking_recent_activity(int $limit = 8): array
{
    $stmt = db()->prepare(
        'SELECT l.created_at, l.action, l.details, b.id AS booking_id, b.booking_reference, b.customer_name
           FROM booking_logs l JOIN bookings b ON b.id = l.booking_id
          ORDER BY l.created_at DESC, l.id DESC
          LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

// ---------------------------------------------------------------------------
// Calendar, upcoming bookings, customers and global history (admin pages)
// ---------------------------------------------------------------------------

/** Bookings with one of $statuses whose schedule touches [$from, $to) (datetime strings). */
function booking_list_overlapping(string $from, string $to, array $statuses): array
{
    $statuses = array_values(array_intersect($statuses, array_keys(BOOKING_STATUS_LABELS)));
    if (!$statuses) {
        return [];
    }
    $marks  = implode(',', array_fill(0, count($statuses), '?'));
    $stmt   = db()->prepare(
        "SELECT * FROM bookings WHERE booking_status IN ($marks) AND start_datetime < ? AND end_datetime > ?
          ORDER BY start_datetime"
    );
    $stmt->execute([...$statuses, $to, $from]);
    return $stmt->fetchAll();
}

/** Approved bookings that have not finished yet, soonest first. */
function booking_upcoming_approved(int $limit = 5): array
{
    $stmt = db()->prepare(
        "SELECT * FROM bookings WHERE booking_status = 'approved' AND end_datetime >= :now
          ORDER BY start_datetime LIMIT :limit"
    );
    $stmt->bindValue(':now', date('Y-m-d H:i:s'));
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Verified requests that wait for a decision, oldest first. */
function booking_pending_review_list(int $limit = 5): array
{
    $stmt = db()->prepare(
        "SELECT * FROM bookings WHERE booking_status = 'pending_review' ORDER BY created_at LIMIT :limit"
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Customers grouped by email address. Returns ['rows' => [...], 'total' => int]. */
function customer_list(string $q, int $page, int $perPage): array
{
    $params = [];
    $having = '';
    $q = trim($q);
    if ($q !== '') {
        $like = '%' . addcslashes($q, '%_\\') . '%';
        $having = 'HAVING (name LIKE :q1 OR email LIKE :q2 OR phone LIKE :q3)';
        $params = [':q1' => $like, ':q2' => $like, ':q3' => $like];
    }
    $base = "SELECT email, SUBSTRING_INDEX(GROUP_CONCAT(customer_name ORDER BY created_at DESC SEPARATOR '\n'), '\n', 1) AS name,
                    SUBSTRING_INDEX(GROUP_CONCAT(phone ORDER BY created_at DESC SEPARATOR '\n'), '\n', 1) AS phone,
                    COUNT(*) AS requests, SUM(booking_status = 'approved') AS approved,
                    SUM(booking_status = 'rejected') AS rejected, MAX(email_verified) AS verified, MAX(created_at) AS last_request
               FROM bookings GROUP BY email $having";
    $pdo   = db();
    $count = $pdo->prepare("SELECT COUNT(*) FROM ($base) AS c");
    $count->execute($params);
    $total = (int) $count->fetchColumn();

    $stmt = $pdo->prepare("$base ORDER BY last_request DESC LIMIT :limit OFFSET :offset");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, ($page - 1) * $perPage), PDO::PARAM_INT);
    $stmt->execute();
    return ['rows' => $stmt->fetchAll(), 'total' => $total];
}

/** Every logged action and email across all bookings, newest first (history page). */
function booking_global_history(int $page, int $perPage): array
{
    $pdo   = db();
    $total = (int) $pdo->query('SELECT (SELECT COUNT(*) FROM booking_logs) + (SELECT COUNT(*) FROM email_logs)')->fetchColumn();
    $stmt  = $pdo->prepare(
        "SELECT * FROM (
            SELECT l.created_at AS happened_at, 'action' AS kind, l.action AS title, l.details AS text,
                   b.id AS booking_id, b.booking_reference AS ref, b.customer_name AS customer, a.username AS admin, 1 AS ok
              FROM booking_logs l JOIN bookings b ON b.id = l.booking_id LEFT JOIN administrators a ON a.id = l.admin_id
            UNION ALL
            SELECT e.created_at, 'email', e.type, CONCAT(e.recipient, ': ', e.subject),
                   e.booking_id, b.booking_reference, b.customer_name, NULL, (e.status = 'sent')
              FROM email_logs e LEFT JOIN bookings b ON b.id = e.booking_id
         ) AS history ORDER BY happened_at DESC LIMIT :limit OFFSET :offset"
    );
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', max(0, ($page - 1) * $perPage), PDO::PARAM_INT);
    $stmt->execute();
    return ['rows' => $stmt->fetchAll(), 'total' => $total];
}
