<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use App\Helpers\AuditLog;

class SettingsController extends BaseController
{
    public function get()
    {
        $user = $this->requireRole(['admin']);

        // Create settings table if it doesn't exist
        try {
            DB::statement("CREATE TABLE IF NOT EXISTS settings (
                `key` VARCHAR(64) PRIMARY KEY,
                `value` TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Exception $e) {
            // Table might already exist
        }

        $settings = DB::table('settings')
            ->pluck('value', 'key')
            ->toArray();

        return response()->json(['success' => true, 'settings' => $settings]);
    }

    public function save(Request $request)
    {
        $user = $this->requireRole(['admin']);

        // Create settings table if it doesn't exist
        try {
            DB::statement("CREATE TABLE IF NOT EXISTS settings (
                `key` VARCHAR(64) PRIMARY KEY,
                `value` TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Exception $e) {
            // Table might already exist
        }

        $data = $request->all();
        $errors = [];

        // If SMTP settings are being saved, validate them
        // Only validate if at least one SMTP field is present (not just email_optional_enabled)
        $hasSmtpFields = isset($data['smtp_host']) || isset($data['smtp_port']) || isset($data['smtp_user']) || 
            isset($data['smtp_pass']) || isset($data['smtp_from']) || isset($data['smtp_from_name']) || 
            isset($data['smtp_secure']);
        
        if ($hasSmtpFields) {
            // When saving SMTP, validate all required fields
            $errors = array_merge($errors, $this->validateSmtpSettings($data, true));
        }

        // If there are validation errors, return them
        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'errors' => $errors
            ], 400);
        }

        $updatedKeys = [];
        foreach ($data as $k => $v) {
            $key = substr((string)$k, 0, 64);
            $val = is_scalar($v) ? (string)$v : json_encode($v);
            
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $val]
            );
            $updatedKeys[] = $key;
        }

        // Log settings update
        AuditLog::logAction(
            $user,
            'SETTINGS_UPDATE',
            'settings',
            null,
            "Updated settings: " . implode(', ', $updatedKeys)
        );

        return response()->json(['success' => true]);
    }

    private function validateSmtpSettings($data, $requireAll = false)
    {
        $errors = [];
        
        // Check if any SMTP field is present
        $hasAnySmtpField = isset($data['smtp_host']) || isset($data['smtp_port']) || isset($data['smtp_user']) || 
            isset($data['smtp_pass']) || isset($data['smtp_from']) || isset($data['smtp_from_name']) || 
            isset($data['smtp_secure']);
        
        // If requireAll is true, validate all fields even if not present
        // Otherwise, only validate fields that are present
        $validateHost = $requireAll || isset($data['smtp_host']);
        $validatePort = $requireAll || isset($data['smtp_port']);
        $validateUser = $requireAll || isset($data['smtp_user']);
        $validatePass = $requireAll || isset($data['smtp_pass']);
        $validateFrom = $requireAll || isset($data['smtp_from']);
        $validateFromName = $requireAll || isset($data['smtp_from_name']);
        $validateSecure = $requireAll || isset($data['smtp_secure']);

        // SMTP Host validation
        if ($validateHost) {
            $host = isset($data['smtp_host']) ? trim($data['smtp_host']) : '';
            if (empty($host)) {
                $errors['smtp_host'] = 'SMTP host is required.';
            } elseif (!$this->isValidHost($host)) {
                $errors['smtp_host'] = 'Enter a valid SMTP host (e.g., smtp.gmail.com).';
            }
        }

        // SMTP Port validation
        if ($validatePort) {
            $port = isset($data['smtp_port']) ? $data['smtp_port'] : '';
            if (empty($port) && $port !== '0') {
                $errors['smtp_port'] = 'SMTP port is required.';
            } else {
                $portInt = (int)$port;
                if (!is_numeric($port) || $portInt < 1 || $portInt > 65535) {
                    $errors['smtp_port'] = 'SMTP port must be a number between 1 and 65535.';
                }
            }
        }

        // SMTP Username validation
        if ($validateUser) {
            $username = isset($data['smtp_user']) ? trim($data['smtp_user']) : '';
            if (empty($username)) {
                $errors['smtp_user'] = 'SMTP username is required.';
            } elseif (!filter_var($username, FILTER_VALIDATE_EMAIL)) {
                $errors['smtp_user'] = 'Enter a valid email address for SMTP username.';
            }
        }

        // SMTP Password validation
        if ($validatePass) {
            $password = isset($data['smtp_pass']) ? $data['smtp_pass'] : '';
            if (empty($password)) {
                $errors['smtp_pass'] = 'SMTP password is required.';
            } elseif (strlen($password) < 6) {
                $errors['smtp_pass'] = 'SMTP password must be at least 6 characters.';
            }
        }

        // From Email validation
        if ($validateFrom) {
            $fromEmail = isset($data['smtp_from']) ? trim($data['smtp_from']) : '';
            if (empty($fromEmail)) {
                $errors['smtp_from'] = 'From email is required.';
            } elseif (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
                $errors['smtp_from'] = 'Enter a valid From email address.';
            }
        }

        // From Name validation
        if ($validateFromName) {
            $fromName = isset($data['smtp_from_name']) ? trim($data['smtp_from_name']) : '';
            if (empty($fromName)) {
                $errors['smtp_from_name'] = 'From name is required.';
            } else {
                $length = strlen($fromName);
                if ($length < 2 || $length > 60) {
                    $errors['smtp_from_name'] = 'From name must be between 2 and 60 characters.';
                }
            }
        }

        // Encryption validation
        if ($validateSecure) {
            $secure = isset($data['smtp_secure']) ? strtolower($data['smtp_secure']) : '';
            if (empty($secure) || !in_array($secure, ['tls', 'ssl', 'none'])) {
                $errors['smtp_secure'] = 'Please select an encryption type.';
            }
        }

        return $errors;
    }

    private function isValidHost($host)
    {
        if (empty($host) || strpos($host, ' ') !== false) {
            return false;
        }

        // Check if it's a valid hostname
        if (preg_match('/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}$/', $host)) {
            return true;
        }

        // Check if it's a valid IP address
        if (preg_match('/^(\d{1,3}\.){3}\d{1,3}$/', $host)) {
            $parts = explode('.', $host);
            foreach ($parts as $part) {
                if ((int)$part < 0 || (int)$part > 255) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    public function testSmtp(Request $request)
    {
        $user = $this->requireRole(['admin']);

        $data = $request->all();
        
        // Validate SMTP settings - require all fields for testing
        $errors = $this->validateSmtpSettings($data, true);
        
        if (!empty($errors)) {
            return response()->json([
                'success' => false,
                'errors' => $errors
            ], 400);
        }

        $host = $request->input('smtp_host');
        $port = (int)$request->input('smtp_port', 587);
        $username = $request->input('smtp_user');
        $password = $request->input('smtp_pass');
        $encryption = $request->input('smtp_secure', 'tls');
        $fromEmail = $request->input('smtp_from', $username);

        try {
            $mail = new PHPMailer(true);
            
            // Configure SMTP
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->Port = $port;
            
            // Set encryption
            if (strtolower($encryption) === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } else {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            }
            
            // Disable certificate verification for testing (not recommended for production)
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            // Set timeout
            $mail->Timeout = 10;
            
            // Test connection by connecting to SMTP server
            $mail->smtpConnect();
            
            if ($mail->smtpConnected()) {
                $mail->smtpClose();
                return response()->json([
                    'success' => true,
                    'message' => 'SMTP connection successful'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to connect to SMTP server'
                ], 500);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}





