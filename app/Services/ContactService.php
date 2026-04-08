<?php

namespace App\Services;

use App\Mail\DirectContact;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;

class ContactService
{
    /**
     * Send contact email from user.
     */
    public function sendContactEmail(array $data, string $recipient)
    {
        return Mail::to($recipient)->send(new DirectContact($data));
    }
}
