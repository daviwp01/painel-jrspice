<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReportUpdatedNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $user;
    public $updateDate;
    public $updateTime;
    public $customTitle;
    public $customIntro;
    public $customBtnText;
    public $customFooter;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, $updateDate, $updateTime)
    {
        $this->user = $user;
        $this->updateDate = $updateDate;
        $this->updateTime = $updateTime;

        $this->customTitle = \App\Models\Setting::get('email_template_title', __('Reports Updated'));
        $this->customIntro = \App\Models\Setting::get('email_template_intro', __('We are writing to inform you that our Power BI dashboards have been updated with the latest data.'));
        $this->customBtnText = \App\Models\Setting::get('email_template_btn_text', __('Access Dashboard'));
        $this->customFooter = \App\Models\Setting::get('email_template_footer');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->customTitle . ' - ' . config('app.name'),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.reports.updated',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
