<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Setting;

class SMTPSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp-relay.brevo.com',
            'mail_port' => '587',
            'mail_username' => 'noreply@dentrixpro.com.br',
            'mail_password' => 'bNrjD7mk1aCGnqwX',
            'mail_encryption' => 'tls',
            'mail_from_address' => 'noreply@dentrixpro.com.br',
            'mail_from_name' => 'JR Spice Analytics',
        ];

        foreach ($settings as $key => $value) {
            Setting::set($key, $value);
        }
    }
}
