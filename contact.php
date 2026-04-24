<?php
/**
 * send_mail.php — Kavi Home Care & Nursing Contact Form Handler
 */

// MUST be at the very top — before anything else
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/Exception.php';
require __DIR__ . '/PHPMailer/PHPMailer.php';
require __DIR__ . '/PHPMailer/SMTP.php';

// --- CONFIG ------------------------------------------------------------------
define('RECIPIENT_EMAIL', 'arun@vishakarex.in');
define('RECIPIENT_NAME',  'Kavi Home Care & Nursing');
define('FROM_EMAIL',      'info@wimbgo.com');
define('FROM_NAME',       'Kavi Contact Form');
define('SUBJECT_PREFIX',  '[New Enquiry] ');
define('REDIRECT_PAGE',   'contact.html');
// -----------------------------------------------------------------------------


// -- 1. Accept POST only -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . REDIRECT_PAGE);
    exit;
}


// -- 2. Honeypot spam check ----------------------------------------------------
if (!empty($_POST['website'])) {
    header('Location: ' . REDIRECT_PAGE . '?status=success');
    exit;
}


// -- 3. Rate limiting (max 3 submissions per hour per visitor) -----------------
session_start();
$now = time();
$_SESSION['kavi_contact_times'] = array_filter(
    $_SESSION['kavi_contact_times'] ?? [],
    fn($t) => ($now - $t) < 3600
);
if (count($_SESSION['kavi_contact_times']) >= 3) {
    redirect('error', 'Too many submissions. Please try again later.');
}


// -- 4. Collect & sanitize all form fields ------------------------------------
$name             = sanitize($_POST['name']              ?? '');
$phone            = sanitize($_POST['phone']             ?? '');
$email            = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$address          = sanitize($_POST['address']           ?? '');
$caseHistory      = sanitize($_POST['case_history']      ?? '');
$diagnosisDetails = sanitize($_POST['diagnosis_details'] ?? '');
$staffRequired    = sanitize($_POST['staff_required']    ?? '');
$patientCondition = sanitize($_POST['patient_condition'] ?? '');
$serviceDuration  = sanitize($_POST['service_duration']  ?? '');
$terms            = !empty($_POST['terms']);


// -- 5. Validate required fields -----------------------------------------------
$errors = [];

if (empty($name) || strlen($name) > 100) {
    $errors[] = 'Full name is required (max 100 characters).';
}
if (empty($phone) || !preg_match('/^[+\d\s\-(). ]{7,20}$/', $phone)) {
    $errors[] = 'A valid contact number is required.';
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}
if (empty($address) || strlen($address) > 300) {
    $errors[] = 'Address is required (max 300 characters).';
}

if (!empty($errors)) {
    redirect('error', implode(' ', $errors));
}


// -- 6. Build the plain-text email body ---------------------------------------
$body  = "New patient enquiry received via the Kavi Home Care contact form.\n";
$body .= str_repeat('=', 55) . "\n\n";
$body .= "PATIENT / CONTACT DETAILS\n";
$body .= str_repeat('-', 55) . "\n";
$body .= "Name             : {$name}\n";
$body .= "Contact Number   : {$phone}\n";
$body .= "Email ID         : {$email}\n";
$body .= "Address          : {$address}\n\n";
$body .= "MEDICAL INFORMATION\n";
$body .= str_repeat('-', 55) . "\n";
$body .= "Case History     :\n{$caseHistory}\n\n";
$body .= "Diagnosis Details:\n" . ($diagnosisDetails ?: 'Not provided') . "\n\n";
$body .= "SERVICE REQUIREMENTS\n";
$body .= str_repeat('-', 55) . "\n";
$body .= "Type of Staff    : " . ($staffRequired    ?: 'Not specified') . "\n";
$body .= "Patient Condition: " . ($patientCondition ?: 'Not specified') . "\n";
$body .= "Service Duration : " . ($serviceDuration  ?: 'Not specified') . "\n\n";
$body .= str_repeat('=', 55) . "\n";
$body .= "Submitted: " . date('Y-m-d H:i:s T') . "\n";


// -- 7. Build the HTML email body ----------------------------------------------
$htmlBody = '<!DOCTYPE html>
<html>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:30px 0;">
  <tr><td>
    <table width="600" cellpadding="0" cellspacing="0" align="center"
           style="background:#ffffff;border-radius:8px;overflow:hidden;
                  box-shadow:0 2px 8px rgba(0,0,0,0.1);">

      <!-- Header -->
      <tr>
        <td style="background:#008080;padding:24px 32px;">
          <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:700;">
            &#128203; New Patient Enquiry
          </h1>
          <p style="margin:6px 0 0;color:#b2dfdb;font-size:13px;">
            Kavi Home Care &amp; Nursing &mdash; Contact Form Submission
          </p>
        </td>
      </tr>

      <!-- Body -->
      <tr>
        <td style="padding:28px 32px;">

          <!-- Section: Contact Details -->
          <h2 style="margin:0 0 12px;font-size:14px;text-transform:uppercase;
                     letter-spacing:1px;color:#008080;border-bottom:2px solid #e0f2f1;
                     padding-bottom:6px;">Contact Details</h2>
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
            ' . infoRow('Name',           htmlspecialchars($name))
              . infoRow('Contact Number', htmlspecialchars($phone))
              . infoRow('Email ID',       '<a href="mailto:' . htmlspecialchars($email) . '" style="color:#008080;">' . htmlspecialchars($email) . '</a>')
              . infoRow('Address',        nl2br(htmlspecialchars($address))) . '
          </table>

          <!-- Section: Medical Information -->
          <h2 style="margin:0 0 12px;font-size:14px;text-transform:uppercase;
                     letter-spacing:1px;color:#008080;border-bottom:2px solid #e0f2f1;
                     padding-bottom:6px;">Medical Information</h2>

          <p style="margin:0 0 4px;font-weight:bold;font-size:13px;color:#555;">Case History</p>
          <div style="background:#f9fafb;border-left:4px solid #008080;padding:12px 16px;
                      border-radius:4px;margin-bottom:16px;white-space:pre-wrap;
                      font-size:13px;color:#333;line-height:1.6;">'
              . htmlspecialchars($caseHistory) . '
          </div>

          <p style="margin:0 0 4px;font-weight:bold;font-size:13px;color:#555;">Diagnosis Details</p>
          <div style="background:#f9fafb;border-left:4px solid #26a69a;padding:12px 16px;
                      border-radius:4px;margin-bottom:24px;white-space:pre-wrap;
                      font-size:13px;color:#333;line-height:1.6;">'
              . htmlspecialchars($diagnosisDetails ?: 'Not provided') . '
          </div>

          <!-- Section: Service Requirements -->
          <h2 style="margin:0 0 12px;font-size:14px;text-transform:uppercase;
                     letter-spacing:1px;color:#008080;border-bottom:2px solid #e0f2f1;
                     padding-bottom:6px;">Service Requirements</h2>
          <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
            ' . infoRow('Type of Staff Required', htmlspecialchars($staffRequired    ?: 'Not specified'))
              . infoRow('Patient Condition',       htmlspecialchars($patientCondition ?: 'Not specified'))
              . infoRow('Preferred Duration',      htmlspecialchars($serviceDuration  ?: 'Not specified')) . '
          </table>

        </td>
      </tr>

      <!-- Footer -->
      <tr>
        <td style="background:#f9fafb;padding:16px 32px;border-top:1px solid #e0e0e0;">
          <p style="margin:0;color:#999;font-size:12px;">
            Submitted: ' . date('Y-m-d H:i:s T') . '
          </p>
        </td>
      </tr>

    </table>
  </td></tr>
</table>
</body>
</html>';


// -- 8. Send via PHPMailer + SMTP ---------------------------------------------
$mail = new PHPMailer(true);

try {
    // SMTP Settings
    $mail->isSMTP();
    $mail->Host       = 'mail.wimbgo.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'info@wimbgo.com';
    $mail->Password   = 'kdg9hY3*N2&P';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;
    $mail->CharSet    = 'UTF-8';

    // Recipients
    $mail->setFrom(FROM_EMAIL, FROM_NAME);
    $mail->addAddress(RECIPIENT_EMAIL, RECIPIENT_NAME);
    $mail->addReplyTo($email, $name);

    // Content
    $mail->isHTML(true);
    $mail->Subject = SUBJECT_PREFIX . 'Patient Enquiry from ' . $name;
    $mail->Body    = $htmlBody;
    $mail->AltBody = $body;

    $mail->send();

    $_SESSION['kavi_contact_times'][] = $now;
    redirect('success', "Thank you, {$name}! Your enquiry has been received. Our team will contact you shortly.");

} catch (Exception $e) {
    error_log('[KaviContactForm] Mailer error: ' . $mail->ErrorInfo);
    redirect('error', 'Sorry, something went wrong. Please call us at +91 84387 82511.');
}


// -- Helpers -------------------------------------------------------------------

/** Strip HTML tags and trim */
function sanitize(string $value): string {
    return trim(strip_tags($value));
}

/** Two-column info row for the HTML email */
function infoRow(string $label, string $value): string {
    return '<tr>'
        . '<td style="padding:7px 10px 7px 0;font-size:13px;font-weight:bold;'
        . 'color:#555;width:160px;vertical-align:top;">' . $label . '</td>'
        . '<td style="padding:7px 0;font-size:13px;color:#333;">' . $value . '</td>'
        . '</tr>';
}

/** Redirect back to the form page with status & message */
function redirect(string $status, string $message): void {
    $params = http_build_query(['status' => $status, 'msg' => $message]);
    header('Location: ' . REDIRECT_PAGE . '?' . $params);
    exit;
}