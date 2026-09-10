<?php

namespace App\Mail;

use App\Domains\Billing\HotelPlan;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WaitlistCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Hotel $hotel,
        public string $plainPassword,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Quầy Signage Desk của bạn đã mở');
    }

    public function content(): Content
    {
        $limit = $this->hotel->device_limit;

        return new Content(
            html: 'mail.waitlist-credentials',
            text: 'mail.waitlist-credentials-text',
            with: [
                'loginUrl' => rtrim((string) config('app.cms_url'), '/').'/login',
                'planLabel' => HotelPlan::label($this->hotel->plan),
                'deviceLabel' => $limit === null ? 'không giới hạn TV' : $limit.' TV',
                'pairingLabel' => $this->hotel->allowsPairingLinks()
                    ? 'Mở link ghép trên TV, không cần gõ mã PIN.'
                    : 'TV hiện mã PIN. Nhập mã đó ở trang Phòng để ghép.',
            ],
        );
    }
}
