<?php

namespace AbuseIO\Notification;

use Config;

class Mail extends Notification
{
    /**
     * Create a new Notification instance
     */
    public function __construct()
    {
        //
    }

    /**
     * Sends out mail notifications for a specific $customerReference
     * @return boolean  Returns if succeeded or not
     */
    public function send($notifications)
    {
        // TODO - Fix config service provider (move parser one into laravel default?)
        Config::set('notifications.mail', include('/opt/abuseio/vendor/abuseio/notification-mail/config/Mail.php'));

        foreach ($notifications as $customerReference => $notificationTypes) {
            foreach ($notificationTypes as $notificationType => $tickets) {
                $boxes = [ ];
                foreach ($tickets as $ticket) {
                    $replacements = [
                        'IP_CONTACT_ASH_LINK'       => config('main.ash.url') . 'collect/' .
                            md5($ticket->id . $ticket->ip . $ticket->ip_contact_reference),
                        'DOMAIN_CONTACT_ASH_LINK'   => config('main.ash.url') . 'collect/' .
                            md5($ticket->id . $ticket->ip . $ticket->domain_contact_reference),
                        'TICKET_NUMBER'             => $ticket->id,
                        'TICKET_IP'                 => $ticket->ip,
                        'TICKET_DOMAIN'             => $ticket->domain,
                        'TICKET_TYPE_NAME'          => trans("types.type.{$ticket->type_id}.name"),
                        'TICKET_TYPE_DESCRIPTION'   => trans("types.type.{$ticket->type_id}.description"),
                        'TICKET_CLASS_NAME'         => trans("classifications.{$ticket->class_id}.name"),
                        'TICKET_EVENT_COUNT'        => $ticket->events->count(),
                    ];

                    $box = config("notifications.mail.templates.{$notificationType}_box");

                    foreach ($replacements as $search => $replacement) {
                        $box = str_replace("<<{$search}>>", $replacement, $box);
                    }

                    $boxes[] = $box;
                }
            }
        }

        if (!empty($boxes)) {
            $boxes = trim(implode('bart', $boxes));
            $mail = config("notifications.mail.templates.mail");
            $mail = str_replace('<<BOXES>>', $boxes, $mail);

            print_r($mail);
        }

        return true;
    }
}
