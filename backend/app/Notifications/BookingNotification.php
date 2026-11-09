<?php

namespace App\Notifications;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BookingNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public Booking $booking,
        public string $event,
        public array $context = [],
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$subject, $lines, $cta] = $this->content();
        $mail = (new MailMessage)->subject($subject)->greeting('Hello '.$notifiable->name.',');

        foreach ($lines as $line) {
            $mail->line($line);
        }

        if ($cta !== null) {
            $mail->action($cta['label'], $cta['url']);
        }

        return $mail;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        [$subject, $lines] = $this->content();

        return [
            'event' => $this->event,
            'title' => $subject,
            'message' => $lines[0] ?? '',
            'booking_id' => $this->booking->id,
            'booking_reference' => $this->booking->reference,
            'scheduled_date' => $this->booking->scheduled_date?->toDateString(),
        ];
    }

    /**
     * @return array{0: string, 1: array<int, string>, 2: array{label: string, url: string}|null}
     */
    private function content(): array
    {
        $ref = $this->booking->reference;
        $date = $this->booking->scheduled_date?->toDateString();
        $time = substr((string) $this->booking->start_time, 0, 5);
        $service = $this->booking->service?->name ?? 'your service';
        $cleaner = $this->context['cleaner_name'] ?? $this->booking->cleaner?->user?->name;
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        $customerLink = ['label' => 'View booking', 'url' => $frontend.'/account/bookings/'.$this->booking->id];
        $cleanerLink = ['label' => 'View job', 'url' => $frontend.'/cleaner/jobs/'.$this->booking->id];
        $reviewLink = ['label' => 'Leave a review', 'url' => $frontend.'/account/bookings/'.$this->booking->id.'?review=1'];
        $adminLink = ['label' => 'Open in admin', 'url' => $frontend.'/admin/bookings/'.$this->booking->id];

        return match ($this->event) {
            'booking_received' => ["We've received your booking $ref", [
                "Thanks for booking $service with LuxeShine Cleaning.",
                "Your booking $ref is scheduled for $date at $time and is now pending confirmation.",
            ], $customerLink],
            'booking_confirmed' => ["Your booking $ref is confirmed", [
                "Good news — your $service booking on $date at $time is confirmed.",
                $cleaner ? "Your cleaner will be $cleaner." : 'A cleaner will be assigned shortly.',
            ], $customerLink],
            'booking_rescheduled' => ["Your booking $ref has been rescheduled", [
                "Your $service booking is now scheduled for $date at $time.",
            ], $customerLink],
            'booking_cancelled' => ["Your booking $ref has been cancelled", [
                "Your $service booking for $date has been cancelled.",
                $this->context['reason'] ?? '',
            ], $customerLink],
            'booking_reminder' => ["Reminder: your booking $ref is tomorrow", [
                "This is a reminder that your $service booking is on $date at $time.",
            ], $customerLink],
            'booking_completed' => ["Your booking $ref is complete", [
                "We hope you enjoyed your $service. Your booking $ref is now complete.",
                'We would love to hear how it went — please leave a review.',
            ], $reviewLink],
            'job_assigned' => ["New job assigned: $ref", [
                "You have been assigned a $service job on $date at $time.",
            ], $cleanerLink],
            'job_unassigned' => ["Job $ref has been unassigned", [
                "You have been unassigned from the $service job on $date.",
            ], $cleanerLink],
            'job_reminder' => ["Reminder: job $ref tomorrow", [
                "You have a $service job tomorrow ($date) at $time.",
            ], $cleanerLink],
            'admin_new_booking' => ["New website booking: $ref", [
                "A new $service booking ($ref) was placed for $date at $time.",
            ], $adminLink],
            'admin_booking_cancelled' => ["Booking cancelled by customer: $ref", [
                "Booking $ref for $date was cancelled by the customer.",
                $this->context['reason'] ?? '',
            ], $adminLink],
            'admin_new_review' => ['A new review is awaiting moderation', [
                'A customer has left a review that is awaiting moderation.',
            ], $adminLink],
            default => ["Update on booking $ref", ["There is an update on your booking $ref."], $customerLink],
        };
    }
}
