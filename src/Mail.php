<?php

namespace AbuseIO\Notification;

use Config;
use Mail as SendMail;
use Marknl\Iodef;
use Zend\XmlRpc\Generator\DomDocument;

class Mail extends Notification
{
    private  $iodefDocument;

    /**
     * Create a new Notification instance
     */
    public function __construct()
    {
        $this->iodefDocument = new Iodef\Elements\IODEFDocument();
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
            $tickets = [];

            foreach ($notificationTypes as $notificationType => $tickets) {

                foreach ($tickets as $ticket) {
                    $this->addIodefObject($ticket);

                    $replacements = [
                        'IP_CONTACT_ASH_LINK'           => config('main.ash.url') . 'collect/' . $ticket->id . '/' .
                            md5($ticket->id . $ticket->ip . $ticket->ip_contact_reference),
                        'DOMAIN_CONTACT_ASH_LINK'       => config('main.ash.url') . 'collect/' . $ticket->id . '/' .
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
                    $iodef = new Iodef\Writer();
                    $iodef->write([
                        [
                            'name' => 'IODEF-Document',
                            'attributes' => $this->iodefDocument->getAttributes(),
                            'value' => $this->iodefDocument,
                        ]
                    ]);

                    // TODO - bug marksg to enable output formatting. For now this is a temporally fix.
                    $dom = new \DOMDocument;
                    $dom->formatOutput = true;
                    $dom->loadXML($iodef->outputMemory());
                    $XmlAttachmentData = $dom->saveXML();

                    $sent = SendMail::raw(
                        $mail,
                        function ($message) use ($subject, $recipient, $XmlAttachmentData) {

                            $message->to($recipient);
                            $message->subject($subject);
                            $message->attachData(
                                $XmlAttachmentData,
                                'iodef.xml',
                                [
                                    'as' => 'iodef.xml',
                                    'mime' => 'text/xml',
                                ]
                            );

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
    
    private function addIodefObject($ticket) {
        // Add report type, origin and date
        $incident = new Iodef\Elements\Incident();
        $incident->setAttributes(
            [
                'purpose'       => 'reporting'
            ]
        );

        $incidentID = new Iodef\Elements\IncidentID();
        $incidentID->setAttributes(
            [
                'name'          => 'https://myhost.isp.local/api/',
                'restriction'   => 'need-to-know',
            ]
        );
        $incidentID->value($ticket->id);
        $incident->addChild($incidentID);

        $reportTime = new Iodef\Elements\ReportTime();
        $reportTime->value(date('Y-m-d\TH:i:sP'));
        $incident->addChild($reportTime);

        // Add ticket abuse classification and type
        $assessment = new Iodef\Elements\Assessment();
        $impact = new Iodef\Elements\Impact();
        $severity = [
            '1' => 'low',
            '2' => 'medium',
            '3' => 'high',
        ];
        echo $ticket->class;
        $impact->setAttributes(
            [
                'type'          => 'ext-value',
                'ext-type'      => trans('classifications.' . $ticket->class_id . '.name'),
                'severity'      => $severity[$ticket->type_id],
            ]
        );
        $assessment->addChild($impact);
        $incident->addChild($assessment);

        // Add Origin/Creator contact information for this ticket
        $contact = new Iodef\Elements\Contact();
        $contact->setAttributes(
            [
                'role'          => 'creator',
                'type'          => 'ext-value',
                'ext-type'      => 'Software',
                'restriction'   => 'need-to-know',
            ]
        );

        $contactName = new Iodef\Elements\ContactName();
        $contactName->value('AbuseIO downstream provider');
        $contact->addChild($contactName);

        $email = new Iodef\Elements\Email();
        $email->value('jsmith@csirt.example.com');
        $contact->addChild($email);

        $incident->addChild($contact);

        // Add Abusedesk contact information for this ticket
        $contact = new Iodef\Elements\Contact();
        $contact->setAttributes(
            [
                'role'          => 'irt',
                'type'          => 'organization',
                'restriction'   => 'need-to-know',
            ]
        );

        $contactName = new Iodef\Elements\ContactName();
        $contactName->value('ISP Abusedesk');
        $contact->addChild($contactName);

        $email = new Iodef\Elements\Email();
        $email->value('abuse@isp.local');
        $contact->addChild($email);

        $incident->addChild($contact);

        // Add ticket events as records
        if ($ticket->events->count() >= 1) {
            foreach ($ticket->events as $event) {
                $eventData = new Iodef\Elements\EventData();

                $record = new Iodef\Elements\Record;

                $recordData = new Iodef\Elements\RecordData;

                $recordDateTime = new Iodef\Elements\DateTime;
                $recordDateTime->value = date('Y-m-d\TH:i:sP', $event->timestamp);
                $recordData->addChild($recordDateTime);

                $recordDescription = new Iodef\Elements\Description;
                $recordDescription->value = "Source:{$event->source}";
                $recordData->addChild($recordDescription);

                $recordItem = new Iodef\Elements\RecordItem;
                $recordItem->setAttributes(
                    [
                        'dtype'     => 'ext-value',
                        'ext-type'  => 'json',
                    ]
                );
                $recordItem->value = $event->information;
                $recordData->addChild($recordItem);

                $record->addChild($recordData);

                $eventData->addChild($record);

                $incident->addChild($eventData);
            }
        }

        // Add ticket notes as history items
        if ($ticket->notes->count() >= 1) {
            $history = new Iodef\Elements\History;

            foreach ($ticket->notes as $note) {
                if ($note->hidden == true) {
                    continue;
                }

                $historyItem = new Iodef\Elements\HistoryItem;
                $historyItem->setAttributes(
                    [
                        'action' => 'status-new-info',
                    ]
                );

                $historyDateTime = new Iodef\Elements\DateTime;
                $historyDateTime->value = date('Y-m-d\TH:i:sP', strtotime($note->created_at));
                $historyItem->addChild($historyDateTime);

                $historySubmitter = new Iodef\Elements\AdditionalData;
                $historySubmitter->setAttributes(
                    [
                        'name' => 'submitter',
                    ]
                );
                $historySubmitter->value = "{$note->submitter}";
                $historyItem->addChild($historySubmitter);

                $historyNote = new Iodef\Elements\AdditionalData;
                $historyNote->setAttributes(
                    [
                        'name' => 'text',
                    ]
                );
                $historyNote->value = "{$note->text}";
                $historyItem->addChild($historyNote);

                $history->addChild($historyItem);
            }

            $incident->addChild($history);
        }

        // Add incident to the document
        $this->iodefDocument->addChild($incident);
    }
}
