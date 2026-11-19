<?php
/**
 * config/mailer.php
 * Sends email through Gmail SMTP using PHPMailer.
 * Credentials come from .env - they are never written in code or shown to visitors.
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/** Build a PHPMailer object that is already connected to Gmail's settings. */
function create_mailer(): PHPMailer
{
    $mail = new PHPMailer(true);          // true = throw exceptions on errors
    $mail->isSMTP();
    $mail->Host       = env('MAIL_HOST');
    $mail->Port       = (int) env('MAIL_PORT', '587');
    $mail->SMTPAuth   = true;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;   // port 587 + STARTTLS
    $mail->Username   = env('MAIL_USERNAME');
    $mail->Password   = env('MAIL_PASSWORD');             // Google App Password
    $mail->Timeout    = 15;
    $mail->CharSet    = 'UTF-8';
    $mail->SMTPDebug  = 0;
    $mail->setFrom(env('MAIL_USERNAME'), env('MAIL_FROM_NAME', "Caroline's Party Helper"));
    return $mail;
}

/**
 * Send one HTML email.
 *
 * $options: 'reply_to' (email), 'reply_to_name', 'booking_id', 'type' (for the email log)
 * Returns ['ok' => true/false, 'error' => message or null].
 * 'ok' is true ONLY when PHPMailer reports the message was accepted by Gmail.
 */
function send_email(string $to, string $toName, string $subject, string $html, array $options = []): array
{
    $bookingId = $options['booking_id'] ?? null;
    $type      = $options['type'] ?? 'general';

    try {
        $mail = create_mailer();
        $mail->addAddress($to, $toName);
        if (!empty($options['reply_to'])) {
            $mail->addReplyTo($options['reply_to'], $options['reply_to_name'] ?? '');
        }
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = html_to_text($html);

        $sent = $mail->send();               // true only if the SMTP server accepted it
        if (!$sent) {
            throw new MailException($mail->ErrorInfo ?: 'Unknown mail error');
        }

        log_email($bookingId, $to, $subject, $type, 'sent', null);
        return ['ok' => true, 'error' => null];
    } catch (Throwable $e) {
        // Keep the technical reason in the private log only.
        error_log('[PartyBooking] Email failed (' . $type . '): ' . $e->getMessage());
        log_email($bookingId, $to, $subject, $type, 'failed', $e->getMessage());
        return ['ok' => false, 'error' => IS_LOCAL ? $e->getMessage() : 'Email could not be sent.'];
    }
}

/** Record an email attempt in the email_logs table (never breaks the page if logging fails). */
function log_email(?int $bookingId, string $to, string $subject, string $type, string $status, ?string $error): void
{
    try {
        $stmt = db()->prepare(
            'INSERT INTO email_logs (booking_id, recipient, subject, type, status, error)
             VALUES (:booking_id, :recipient, :subject, :type, :status, :error)'
        );
        $stmt->execute([
            ':booking_id' => $bookingId,
            ':recipient'  => mb_substr($to, 0, 190),
            ':subject'    => mb_substr($subject, 0, 255),
            ':type'       => mb_substr($type, 0, 30),
            ':status'     => $status,
            ':error'      => $error === null ? null : mb_substr($error, 0, 500),
        ]);
    } catch (Throwable $e) {
        error_log('[PartyBooking] Could not write email log: ' . $e->getMessage());
    }
}

/** Turn an HTML email into a simple plain-text version (for mail apps that don't show HTML). */
function html_to_text(string $html): string
{
    $text = preg_replace('/<(style|head)[^>]*>.*?<\/\1>/is', '', $html);
    $text = preg_replace('/<a\s[^>]*href="([^"]+)"[^>]*>(.*?)<\/a>/is', '$2 ($1)', $text);
    $text = preg_replace('/<\/(p|div|tr|h[1-6]|li)>|<br\s*\/?>/i', "\n", $text);
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES, 'UTF-8');
    $text = preg_replace("/[ \t]+/", ' ', $text);
    return trim(preg_replace("/\n\s*\n+/", "\n\n", $text));
}
