<?php

namespace App\Notifications;

use App\Enums\DocumentNotificationType;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentWorkflowNotification extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(
        string $id,
        public readonly int $documentId,
        public readonly string $trackingNumber,
        public readonly DocumentNotificationType $eventType,
        public readonly string $title,
        public readonly string $message,
        public readonly string $safeUrl,
    ) {
        $this->id = $id;
        $this->onQueue('notifications');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (config('drms.notifications.email_enabled', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $data = FilamentNotification::make($this->id)
            ->title($this->title)
            ->body($this->message)
            ->icon('heroicon-o-bell-alert')
            ->iconColor('primary')
            ->actions([
                Action::make('view')
                    ->label('View document')
                    ->url($this->safeUrl)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();

        return [
            ...$data,
            'document_id' => $this->documentId,
            'tracking_number' => $this->trackingNumber,
            'event_type' => $this->eventType->value,
            'url' => $this->safeUrl,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("DRMS: {$this->title}")
            ->line($this->message)
            ->line("Tracking number: {$this->trackingNumber}")
            ->action('View document', $this->safeUrl);
    }

    public function databaseType(object $notifiable): string
    {
        return $this->eventType->value;
    }
}
