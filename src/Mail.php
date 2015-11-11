<?php

namespace AbuseIO\Notification;

use Config;
use Mail as SendMail;

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

            $mails = [];

            foreach ($notificationTypes as $notificationType => $tickets) {

                foreach ($tickets as $ticket) {
                    $replacements = [
                        'IP_CONTACT_ASH_LINK'           => config('main.ash.url') . 'collect/' .
                            md5($ticket->id . $ticket->ip . $ticket->ip_contact_reference),
                        'DOMAIN_CONTACT_ASH_LINK'       => config('main.ash.url') . 'collect/' .
                            md5($ticket->id . $ticket->ip . $ticket->domain_contact_reference),
                        'TICKET_NUMBER'                 => $ticket->id,
                        'TICKET_IP'                     => $ticket->ip,
                        'TICKET_DOMAIN'                 => $ticket->domain,
                        'TICKET_TYPE_NAME'              => trans("types.type.{$ticket->type_id}.name"),
                        'TICKET_TYPE_DESCRIPTION'       => trans("types.type.{$ticket->type_id}.description"),
                        'TICKET_CLASS_NAME'             => trans("classifications.{$ticket->class_id}.name"),
                        'TICKET_EVENT_COUNT'            => $ticket->events->count(),
                    ];

                    $box = config("notifications.mail.templates.{$notificationType}_box");

                    foreach ($replacements as $search => $replacement) {
                        $box = str_replace("<<{$search}>>", $replacement, $box);
                    }

                    /*
                     * Even that all these tickets relate to the same customer reference, the contacts might be
                     * changed (added addresses, etc) for a specific ticket. To make sure people only get their own
                     * notificiations we aggregate them here before sending.
                     */
                    if ($notificationType == 'ip') {
                        $recipient = $ticket->ip_contact_email;
                        $mails[$recipient][] = $box;
                    }

                    if ($notificationType == 'domain') {
                        $recipient = $ticket->domain_contact_email;
                        $mails[$recipient][] = $box;
                    }

                }

            }

            foreach ($mails as $recipient => $boxes) {

                if (!empty($boxes)) {

                    $replacements = [
                        'BOXES'                         => trim(implode('', $boxes)),
                        'TICKET_COUNT'                  => count($tickets),
                    ];

                    $subject = config("notifications.mail.templates.subject");
                    $mail = config('notifications.mail.templates.mail');

                    foreach ($replacements as $search => $replacement) {
                        $mail = str_replace("<<{$search}>>", $replacement, $mail);
                    }

                    /*
                     * TODO:
                     * - catch swift error on SendMail
                     * - validate all mail addresses
                     */

                    $sent = SendMail::raw(
                        $mail,
                        function ($message) use ($subject, $recipient) {

                            $message->to($recipient);
                            $message->subject($subject);

                            $message->from(
                                Config::get('main.notifications.from_address'),
                                Config::get('main.notifications.from_name')
                            );

                            if (!empty(Config::get('main.notifications.bcc_enabled'))) {
                                $message->bcc(Config::get('main.notifications.bcc_address'));
                            }

                        }
                    );

                }

            }

        }

        return true;

    }
}
