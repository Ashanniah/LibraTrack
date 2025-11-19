<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

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

        foreach ($data as $k => $v) {
            $key = substr((string)$k, 0, 64);
            $val = is_scalar($v) ? (string)$v : json_encode($v);
            
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $val]
            );
        }

        return response()->json(['success' => true]);
    }

    public function testSmtp(Request $request)
    {
        $user = $this->requireRole(['admin']);

        $host = $request->input('smtp_host');
        $port = (int)$request->input('smtp_port', 587);
        $username = $request->input('smtp_user');
        $password = $request->input('smtp_pass');
        $encryption = $request->input('smtp_secure', 'tls');
        $fromEmail = $request->input('smtp_from', $username);

        if (!$host || !$port || !$username || !$password) {
            return response()->json([
                'success' => false,
                'error' => 'Missing required SMTP configuration fields'
            ], 400);
        }

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





