<?php
/**
 * Liga WOC - Email Service
 * Handles all out-bound emails for the platform.
 *
 * Paleta oficial Liga WOC:
 *   Void Black      #050308   Abyss Purple    #0D0618
 *   Dark Cosmos     #1A0A2E   Royal Obsidian  #2D1B4E
 *   Deep Violet     #4A1A8C   Mystic Purple   #6B2DB5
 *   Electric Indigo #7C3AED   Vivid Violet    #9D4EDD
 *   Bright Amethyst #B565F0   Neon Orchid     #C77DFF
 *   Soft Lavender   #D8A8FF   Crystal White   #F0E6FF
 */

class EmailService {

    // ─── Shared SMTP helper ────────────────────────────────────────────
    private static function mailer() {
        require_once BASE_PATH . 'libs/PHPMailer/src/Exception.php';
        require_once BASE_PATH . 'libs/PHPMailer/src/PHPMailer.php';
        require_once BASE_PATH . 'libs/PHPMailer/src/SMTP.php';

        $host = trim((string)SMTP_HOST);
        // Si el host base no responde SMTP, intenta tambien con mail.<dominio>
        if ($host !== '' && strpos($host, ';') === false && stripos($host, 'mail.') !== 0 && strpos($host, '.') !== false) {
            $host .= ';mail.' . $host;
        }

        $secureMode = strtolower((string)SMTP_SECURE);
        $secure = ($secureMode === 'tls' || $secureMode === 'starttls')
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = $secure;
        $mail->Port       = (int)SMTP_PORT;
        $mail->Timeout    = 20;
        $mail->SMTPOptions = ['ssl' => ['verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true]];
        if (SMTP_DEBUG) {
            $mail->SMTPDebug = 2;
            $mail->Debugoutput = static function ($str, $level) {
                error_log("[SMTP][$level] $str");
            };
        }
        $mail->CharSet  = 'UTF-8';
        $mail->XMailer  = 'Liga WOC Mailer 1.0';
        $mail->setFrom(SMTP_USER, SMTP_FROM_NAME);
        $mail->addReplyTo(SMTP_USER, 'Liga WOC - No Responder');
        return $mail;
    }

    // ─── Design System ─────────────────────────────────────────────────

    /**
     * Accent variant map — all colours drawn exclusively from the Liga WOC palette.
     * 'primary' : Electric Indigo / Mystic Purple  (default, auth)
     * 'vivid'   : Vivid Violet / Neon Orchid       (welcome, approved)
     * 'bright'  : Bright Amethyst / Soft Lavender  (password reset — more intense)
     * 'orchid'  : Neon Orchid / Crystal White      (invitations, team info)
     */
    private static function accent(string $variant): array {
        $map = [
            'primary' => ['hi' => '#7C3AED', 'mid' => '#6B2DB5', 'lo' => '#4A1A8C',
                          'text' => '#C77DFF',  'muted' => '#9D4EDD', 'bar' => '#7C3AED,#B565F0'],
            'vivid'   => ['hi' => '#9D4EDD', 'mid' => '#7C3AED', 'lo' => '#4A1A8C',
                          'text' => '#D8A8FF',  'muted' => '#9D4EDD', 'bar' => '#7C3AED,#C77DFF'],
            'bright'  => ['hi' => '#B565F0', 'mid' => '#9D4EDD', 'lo' => '#6B2DB5',
                          'text' => '#D8A8FF',  'muted' => '#B565F0', 'bar' => '#9D4EDD,#D8A8FF'],
            'orchid'  => ['hi' => '#C77DFF', 'mid' => '#9D4EDD', 'lo' => '#6B2DB5',
                          'text' => '#F0E6FF',  'muted' => '#C77DFF', 'bar' => '#B565F0,#F0E6FF'],
        ];
        return $map[$variant] ?? $map['primary'];
    }

    private static function emailHeader(
        string $title,
        string $subtitle = '',
        string $iconSvg  = '',
        string $variant  = 'primary'
    ): string {
        $a = self::accent($variant);

        $subtitleHtml = $subtitle
            ? "<p style='margin:8px 0 0;font-size:14px;color:{$a['muted']};letter-spacing:.015em;'>$subtitle</p>"
            : '';

        $iconHtml = '';
        if ($iconSvg) {
            $iconHtml = "
            <div style='margin:0 auto 22px;width:58px;height:58px;
                        border-radius:18px;
                        background:linear-gradient(145deg,#2D1B4E,#1A0A2E);
                        border:1px solid {$a['hi']}55;
                        text-align:center;line-height:58px;
                        box-shadow:0 0 0 5px #0D061866,0 0 32px {$a['hi']}44;'>
              <svg width='28' height='28' viewBox='0 0 24 24' fill='none'
                   stroke='{$a['hi']}' stroke-width='1.7'
                   stroke-linecap='round' stroke-linejoin='round'
                   style='vertical-align:middle;'>$iconSvg</svg>
            </div>";
        }

        return "<!DOCTYPE html>
<html lang='es'>
<head>
  <meta charset='UTF-8'>
  <meta name='viewport' content='width=device-width,initial-scale=1'>
  <title>Liga WOC</title>
</head>
<body style='margin:0;padding:0;background:#050308;
             font-family:\"Segoe UI\",Helvetica,Arial,sans-serif;
             -webkit-font-smoothing:antialiased;'>

<table width='100%' cellpadding='0' cellspacing='0' role='presentation'
       style='background:#050308;padding:48px 16px 64px;'>
  <tr><td align='center'>

  <!-- CARD -->
  <table width='560' cellpadding='0' cellspacing='0' role='presentation'
         style='max-width:560px;width:100%;border-radius:24px;overflow:hidden;
                background:#0D0618;
                border:1px solid #2D1B4E;
                box-shadow:0 0 0 1px #1A0A2E,
                           0 40px 100px rgba(5,3,8,.95),
                           0 0 80px {$a['hi']}1A;'>

    <!-- Accent bar -->
    <tr><td style='padding:0;line-height:0;'>
      <div style='height:2px;
                  background:linear-gradient(90deg,#1A0A2E 0%,{$a['bar']} 100%);'>
      </div>
    </td></tr>

    <!-- HERO -->
    <tr><td style='padding:42px 48px 34px;text-align:center;
                   background:radial-gradient(ellipse 100% 80% at 50% 0%,#2D1B4E44 0%,transparent 65%),
                               linear-gradient(180deg,#1A0A2E 0%,#0D0618 100%);'>

      <!-- Wordmark pill -->
      <div style='display:inline-block;
                  background:linear-gradient(145deg,#1A0A2E,#0D0618);
                  border:1px solid #2D1B4E;
                  border-radius:14px;
                  padding:10px 24px;
                  margin-bottom:24px;'>
        <span style='display:block;font-size:9px;letter-spacing:.55em;
                     color:#6B2DB5;font-weight:800;text-transform:uppercase;
                     margin-bottom:3px;'>LIGA</span>
        <span style='display:block;font-size:30px;font-weight:900;color:#F0E6FF;
                     letter-spacing:3px;line-height:1;
                     text-shadow:0 0 24px #7C3AED77,0 0 60px #7C3AED33;'>WOC</span>
      </div>

      <!-- Icon -->
      $iconHtml

      <!-- Title / Subtitle -->
      <h1 style='margin:0 0 8px;font-size:23px;font-weight:800;
                 color:#F0E6FF;letter-spacing:-.25px;line-height:1.3;'>$title</h1>
      $subtitleHtml

    </td></tr>

    <!-- Separator -->
    <tr><td style='padding:0 48px;'>
      <div style='height:1px;background:linear-gradient(90deg,transparent,#2D1B4E,transparent);'></div>
    </td></tr>

    <!-- BODY START -->
    <tr><td style='padding:36px 48px 0;background:#0D0618;'>
";
    }

    private static function emailFooter(string $note = ''): string {
        $noteHtml = $note
            ? "<div style='margin:28px 0 0;padding:16px 18px;border-radius:10px;
                           background:#1A0A2E;border-left:2px solid #2D1B4E;'>
                 <p style='margin:0;font-size:12px;color:#4A1A8C;line-height:1.7;'>$note</p>
               </div>"
            : '';

        return "$noteHtml
      </td>
    </tr>

    <!-- FOOTER -->
    <tr><td style='padding:30px 48px 34px;
                   border-top:1px solid #1A0A2E;
                   background:linear-gradient(180deg,#0D0618,#050308);
                   text-align:center;'>
      <p style='margin:0 0 5px;font-size:10px;font-weight:800;letter-spacing:.28em;
                color:#2D1B4E;text-transform:uppercase;'>LIGA WOC &mdash; DOMINICANA</p>
      <p style='margin:0;font-size:11px;color:#1A0A2E;'>
        <a href='" . SITE_URL . "'
           style='color:#4A1A8C;text-decoration:none;'>ligawocdominicana.com</a>
        &nbsp;&bull;&nbsp;&copy; " . date('Y') . " Todos los derechos reservados
      </p>
    </td></tr>

  </table>
  </td></tr>
</table>
</body></html>";
    }

    /** OTP digit boxes */
    private static function digitBoxes(string $code, string $variant = 'primary'): string {
        $a = self::accent($variant);
        $cells = '';
        foreach (str_split($code) as $d) {
            $cells .= "
            <td style='padding:0 5px;'>
              <div style='width:50px;height:62px;line-height:62px;
                          font-size:32px;font-weight:900;color:#F0E6FF;
                          text-align:center;font-family:\"Courier New\",monospace;
                          background:linear-gradient(160deg,#1A0A2E,#0D0618);
                          border:1px solid {$a['hi']}77;
                          border-radius:12px;
                          box-shadow:0 0 22px {$a['hi']}33,
                                     inset 0 1px 0 rgba(240,230,255,.06);'>$d</div>
            </td>";
        }
        return "<table cellpadding='0' cellspacing='0' role='presentation'
                       style='margin:0 auto;'><tr>$cells</tr></table>";
    }

    /** CTA button */
    private static function ctaButton(string $url, string $label, string $variant = 'primary'): string {
        $a = self::accent($variant);
        return "
        <table cellpadding='0' cellspacing='0' role='presentation' style='margin:28px auto 0;'>
          <tr>
            <td style='border-radius:12px;
                       background:linear-gradient(135deg,{$a['hi']},{$a['mid']});
                       box-shadow:0 6px 28px {$a['hi']}44,
                                  0 1px 0 {$a['text']}22 inset;'>
              <a href='$url'
                 style='display:block;padding:15px 38px;
                        font-size:12px;font-weight:800;letter-spacing:.2em;
                        color:#F0E6FF;text-decoration:none;text-transform:uppercase;
                        border-radius:12px;white-space:nowrap;'>$label</a>
            </td>
          </tr>
        </table>";
    }

    /** Section label */
    private static function sectionTag(string $text, string $variant = 'primary'): string {
        $a = self::accent($variant);
        return "<p style='margin:0 0 18px;font-size:10px;font-weight:800;
                          letter-spacing:.22em;text-transform:uppercase;
                          color:{$a['lo']};'>$text</p>";
    }

    /** Highlighted info strip */
    private static function infoCard(string $html, string $variant = 'primary'): string {
        $a = self::accent($variant);
        return "<div style='padding:18px 20px;border-radius:12px;margin-bottom:6px;
                            border-left:2px solid {$a['hi']};
                            background:linear-gradient(135deg,#1A0A2E,#0D0618);'>
                  $html
                </div>";
    }

    /** Named highlight box (team name, player name) */
    private static function nameCard(string $label, string $value, string $variant = 'primary'): string {
        $a = self::accent($variant);
        return "<div style='padding:22px 26px;border-radius:14px;text-align:center;
                            border:1px solid {$a['hi']}33;
                            background:linear-gradient(160deg,#1A0A2E,#0D0618);
                            margin-bottom:6px;
                            box-shadow:0 0 40px {$a['hi']}0D;'>
                  <p style='margin:0 0 6px;font-size:10px;letter-spacing:.22em;font-weight:800;
                            text-transform:uppercase;color:{$a['mid']};'>$label</p>
                  <p style='margin:0;font-size:26px;font-weight:900;color:#F0E6FF;
                            letter-spacing:-.3px;
                            text-shadow:0 0 20px {$a['hi']}55;'>$value</p>
                </div>";
    }

    /** Expiry badge */
    private static function expiryBadge(): string {
        return "<p style='margin:24px 0 0;text-align:center;'>
                  <span style='display:inline-block;padding:5px 18px;border-radius:99px;
                               font-size:11px;font-weight:700;letter-spacing:.06em;
                               background:#1A0A2E;color:#9D4EDD;
                               border:1px solid #4A1A8C;'>Expira en 15 minutos</span>
                </p>";
    }

    // ─── Auth Emails ──────────────────────────────────────────────────

    public static function sendVerificationEmail($email, $username, $code) {
        try {
            $mail = self::mailer();
            $mail->addAddress($email, $username);
            $mail->isHTML(true);
            $mail->Subject = "[$code] Codigo de activacion - Liga WOC";

            $icon = '<circle cx="12" cy="12" r="10"/><path d="M9 12l2 2 4-4"/>';

            $mail->Body = self::emailHeader("Verifica tu cuenta", "Un paso para entrar a la arena", $icon, 'primary')
                . self::sectionTag('Codigo de verificacion', 'primary')
                . "<p style='margin:0 0 26px;font-size:15px;color:#9D4EDD;line-height:1.8;'>
                     Hola <strong style='color:#D8A8FF;'>$username</strong>, introduce este codigo
                     de 6 digitos para activar tu cuenta de <strong style='color:#F0E6FF;'>Liga WOC</strong>.
                   </p>"
                . self::digitBoxes((string)$code, 'primary')
                . self::expiryBadge()
                . self::emailFooter('Si no administras esta solicitud de cuenta, puedes ignorar este correo de forma segura.');

            $mail->AltBody = "Liga WOC - Codigo de activacion\n\nHola $username,\n\nTu codigo: $code\n\nExpira en 15 minutos.\n\nLiga WOC - ligawocdominicana.com";
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("[MAIL] Verification to {$email}: {$e->getMessage()}");
            return false;
        }
    }

    public static function sendWelcomeEmail($email, $username) {
        try {
            $mail = self::mailer();
            $mail->addAddress($email, $username);
            $mail->isHTML(true);
            $mail->Subject = "Bienvenido a Liga WOC, $username - Cuenta activa";

            $dashboardUrl = SITE_URL . '/dashboard';
            $icon = '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>';

            $features = [
                ['🏆', 'Torneos activos',   'Compite en ligas y copas oficiales'],
                ['⚔️',  'Crea tu equipo',    'Recluta jugadores y lidera la escuadra'],
                ['📊', 'Ranking en vivo',    'Escala posiciones semana a semana'],
            ];

            $rows = '';
            foreach ($features as [$emoji, $ftitle, $fdesc]) {
                $rows .= "
                <tr>
                  <td style='padding:14px 0;border-bottom:1px solid #1A0A2E;
                             vertical-align:top;width:44px;'>
                    <div style='width:40px;height:40px;line-height:40px;text-align:center;
                                border-radius:10px;font-size:19px;
                                background:#1A0A2E;border:1px solid #2D1B4E;'>$emoji</div>
                  </td>
                  <td style='padding:14px 0 14px 14px;border-bottom:1px solid #1A0A2E;'>
                    <p style='margin:0;font-size:14px;font-weight:700;color:#D8A8FF;'>$ftitle</p>
                    <p style='margin:3px 0 0;font-size:12px;color:#4A1A8C;'>$fdesc</p>
                  </td>
                </tr>";
            }

            $mail->Body = self::emailHeader("Bienvenido, $username!", "Tu cuenta esta verificada y lista", $icon, 'vivid')
                . self::sectionTag('Todo lo que puedes hacer ahora', 'vivid')
                . "<table width='100%' cellpadding='0' cellspacing='0' role='presentation'
                         style='margin-bottom:4px;'>$rows</table>"
                . self::ctaButton($dashboardUrl, 'Ir a mi Dashboard', 'vivid')
                . self::emailFooter();

            $mail->AltBody = "Liga WOC - Cuenta activa\n\nHola $username,\n\nYa puedes acceder: $dashboardUrl\n\nLiga WOC - ligawocdominicana.com";
            $mail->send();
        } catch (Exception $e) {
            error_log("[MAIL] Welcome to {$email}: {$e->getMessage()}");
        }
    }

    public static function sendPasswordResetEmail($email, $username, $code) {
        try {
            $mail = self::mailer();
            $mail->addAddress($email, $username);
            $mail->isHTML(true);
            $mail->Subject = "[$code] Restablecer contrasena - Liga WOC";

            $icon = '<rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>';

            $mail->Body = self::emailHeader("Restablece tu contrasena", "Solicitud de seguridad detectada", $icon, 'bright')
                . self::sectionTag('Codigo de restablecimiento', 'bright')
                . "<p style='margin:0 0 26px;font-size:15px;color:#9D4EDD;line-height:1.8;'>
                     Hola <strong style='color:#D8A8FF;'>$username</strong>, recibimos una solicitud
                     para cambiar la contrasena de tu cuenta. Usa este codigo:
                   </p>"
                . self::digitBoxes((string)$code, 'bright')
                . self::expiryBadge()
                . self::emailFooter('Si no solicitaste este cambio, ignora este correo. Tu contrasena actual no ha sido modificada.');

            $mail->AltBody = "Liga WOC - Restablecer contrasena\n\nHola $username,\n\nCodigo: $code\n\nExpira en 15 min.\n\nLiga WOC - ligawocdominicana.com";
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("[MAIL] Reset password to {$email}: {$e->getMessage()}");
            return false;
        }
    }

    public static function sendEmailChangeCode($email, $username, $code) {
        try {
            $mail = self::mailer();
            $mail->addAddress($email, $username);
            $mail->isHTML(true);
            $mail->Subject = "[$code] Cambio de email - Liga WOC";

            $icon = '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>';

            $mail->Body = self::emailHeader("Cambio de correo electronico", "Solicitud de cambio de email detectada", $icon, 'primary')
                . self::sectionTag('Codigo de verificacion', 'primary')
                . "<p style='margin:0 0 26px;font-size:15px;color:#9D4EDD;line-height:1.8;'>
                     Hola <strong style='color:#D8A8FF;'>$username</strong>, hemos recibido una solicitud
                     para cambiar el correo de tu cuenta. Usa este codigo de 6 digitos:
                   </p>"
                . self::digitBoxes((string)$code, 'primary')
                . self::expiryBadge()
                . self::emailFooter('Si no solicitaste este cambio, ignora este correo. Tu email actual no ha sido modificado.');

            $mail->AltBody = "Liga WOC - Cambio de email\n\nHola $username,\n\nCodigo: $code\n\nExpira en 15 min.\n\nLiga WOC - ligawocdominicana.com";
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("[MAIL] Email change to {$email}: {$e->getMessage()}");
            return false;
        }
    }

    // ─── Team Emails ──────────────────────────────────────────────────

    public static function sendTeamInvitation($email, $username, $teamName, $captainName, $teamId) {
        try {
            $mail = self::mailer();
            $mail->addAddress($email, $username);
            $mail->isHTML(true);
            $mail->Subject = "Invitacion de equipo: $teamName te necesita - Liga WOC";

            $teamUrl = SITE_URL . '/teams/view/' . $teamId;
            $icon    = '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>';

            $mail->Body = self::emailHeader("Te han invitado", "Nueva invitacion de equipo", $icon, 'orchid')
                . self::sectionTag('Detalles de la invitacion', 'orchid')
                . "<p style='margin:0 0 22px;font-size:15px;color:#9D4EDD;line-height:1.8;'>
                     Hola <strong style='color:#D8A8FF;'>$username</strong>,
                     el capitan <strong style='color:#D8A8FF;'>$captainName</strong>
                     te ha enviado una invitacion formal para unirte a su escuadra oficial.
                   </p>"
                . self::nameCard('Equipo', $teamName, 'orchid')
                . self::ctaButton($teamUrl, 'Ver equipo y aceptar', 'orchid')
                . self::emailFooter('Si no deseas unirte, simplemente ignora este correo.');

            $mail->AltBody = "Liga WOC - Invitacion\n\n$captainName te invito a: $teamName\n\nVer en: $teamUrl\n\nLiga WOC - ligawocdominicana.com";
            $mail->send();
        } catch (Exception $e) {
            error_log("[MAIL] Team invite to {$email}: {$e->getMessage()}");
        }
    }

    public static function sendTeamJoinApproved($email, $username, $teamName, $teamId) {
        try {
            $mail = self::mailer();
            $mail->addAddress($email, $username);
            $mail->isHTML(true);
            $mail->Subject = "Solicitud aprobada: Eres miembro de $teamName - Liga WOC";

            $teamUrl = SITE_URL . '/teams/view/' . $teamId;
            $icon    = '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>';

            $mail->Body = self::emailHeader("Solicitud aprobada!", "Ahora eres parte del roster", $icon, 'vivid')
                . self::sectionTag('Bienvenido al equipo!', 'vivid')
                . "<p style='margin:0 0 22px;font-size:15px;color:#9D4EDD;line-height:1.8;'>
                     <strong style='color:#D8A8FF;'>$username</strong>, el capitan de
                     <strong style='color:#F0E6FF;'>$teamName</strong> ha aceptado tu solicitud.
                     Ya figuras en el roster oficial y puedes competir en torneos con tu equipo.
                   </p>"
                . self::infoCard(
                    "<p style='margin:0;font-size:13px;color:#9D4EDD;line-height:1.7;'>
                       Accede a tu panel para ver el calendario de partidos, comunicarte
                       con tu equipo y actualizar tu perfil de jugador.
                     </p>",
                    'vivid'
                )
                . self::ctaButton($teamUrl, 'Ver mi equipo', 'vivid')
                . self::emailFooter();

            $mail->AltBody = "Liga WOC - Solicitud aprobada\n\nFelicidades $username, ya eres parte de $teamName.\n\nVer en: $teamUrl\n\nLiga WOC - ligawocdominicana.com";
            $mail->send();
        } catch (Exception $e) {
            error_log("[MAIL] Team join approved to {$email}: {$e->getMessage()}");
        }
    }

    public static function sendTeamJoinRejected($email, $username, $teamName) {
        try {
            $mail = self::mailer();
            $mail->addAddress($email, $username);
            $mail->isHTML(true);
            $mail->Subject = "Actualizacion de tu solicitud a $teamName - Liga WOC";

            $teamsUrl = SITE_URL . '/teams';
            $icon     = '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>';

            $mail->Body = self::emailHeader("Solicitud no aprobada", "Estado de tu solicitud", $icon, 'primary')
                . self::sectionTag('Actualizacion de estado', 'primary')
                . "<p style='margin:0 0 22px;font-size:15px;color:#9D4EDD;line-height:1.8;'>
                     Hola <strong style='color:#D8A8FF;'>$username</strong>, el capitan de
                     <strong style='color:#F0E6FF;'>$teamName</strong> no pudo aceptar tu solicitud
                     en este momento.
                   </p>"
                . self::infoCard(
                    "<p style='margin:0;font-size:13px;color:#9D4EDD;line-height:1.7;'>
                       No te desanimes - hay cientos de equipos activos buscando talento.
                       Explora los equipos disponibles y encuentra el lugar donde brillaras.
                     </p>",
                    'primary'
                )
                . self::ctaButton($teamsUrl, 'Explorar otros equipos', 'primary')
                . self::emailFooter();

            $mail->AltBody = "Liga WOC - Solicitud\n\nHola $username,\n\nTu solicitud a $teamName no fue aprobada.\nExplora otros en: $teamsUrl\n\nLiga WOC - ligawocdominicana.com";
            $mail->send();
        } catch (Exception $e) {
            error_log("[MAIL] Team join rejected to {$email}: {$e->getMessage()}");
        }
    }

    public static function sendNewMemberAlert($captainEmail, $captainName, $playerName, $teamName, $teamId) {
        try {
            $mail = self::mailer();
            $mail->addAddress($captainEmail, $captainName);
            $mail->isHTML(true);
            $mail->Subject = "Nuevo miembro: $playerName se unio a $teamName - Liga WOC";

            $teamUrl = SITE_URL . '/teams/view/' . $teamId;
            $icon    = '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/>';

            $mail->Body = self::emailHeader("Roster actualizado", "Nuevo integrante en tu equipo", $icon, 'orchid')
                . self::sectionTag('Movimiento de roster', 'orchid')
                . "<p style='margin:0 0 22px;font-size:15px;color:#9D4EDD;line-height:1.8;'>
                     Capitan <strong style='color:#D8A8FF;'>$captainName</strong>,
                     el jugador <strong style='color:#C77DFF;'>$playerName</strong> ha aceptado
                     tu invitacion y ahora forma parte oficial del roster de
                     <strong style='color:#F0E6FF;'>$teamName</strong>.
                   </p>"
                . self::nameCard('Nuevo miembro', $playerName, 'orchid')
                . self::ctaButton($teamUrl, 'Ver roster completo', 'orchid')
                . self::emailFooter();

            $mail->AltBody = "Liga WOC - Nuevo miembro\n\nHola $captainName,\n\n$playerName se unio a $teamName.\n\nVer roster en: $teamUrl\n\nLiga WOC - ligawocdominicana.com";
            $mail->send();
        } catch (Exception $e) {
            error_log("[MAIL] New member alert to {$captainEmail}: {$e->getMessage()}");
        }
    }
}