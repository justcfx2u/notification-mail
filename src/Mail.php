<?php

namespace AbuseIO\Notification;

use Config;
use Swift_SmtpTransport;
use Swift_Mailer;
use Swift_Message;
use Swift_Signers_SMimeSigner;
use Swift_Attachment;
use Marknl\Iodef;

/**
 * Class Mail
 * @package AbuseIO\Notification
 */
class Mail extends Notification
{
    /**
     * @var Iodef\Elements\IODEFDocument
     */
    private $iodefDocument;

    /**
     * Create a new Notification instance
     */
    public function __construct()
    {
        parent::__construct($this);

        $this->iodefDocument = new Iodef\Elements\IODEFDocument();
    }

    /**
     * Sends out mail notifications for a specific $customerReference
     *
     * @param array $notifications
     * @return boolean  Returns if succeeded or not
     */
    public function send($notifications)
    {
        foreach ($notifications as $customerReference => $notificationTypes) {

            $mails = [];
            $tickets = [];

            foreach ($notificationTypes as $notificationType => $tickets) {

                foreach ($tickets as $ticket) {
                    $token['ip']        = md5($ticket->id . $ticket->ip . $ticket->ip_contact_reference);
                    $token['domain']    = md5($ticket->id . $ticket->ip . $ticket->domain_contact_reference);
                    $ashUrl             = config('main.ash.url') . 'collect/' . $ticket->id . '/';

                    $this->addIodefObject($ticket, $token[$notificationType], $ashUrl);

                    $replacements = [
                        'IP_CONTACT_ASH_LINK'           => $ashUrl . $token['ip'],
                        'DOMAIN_CONTACT_ASH_LINK'       => $ashUrl . $token['domain'],
                        'TICKET_NUMBER'                 => $ticket->id,
                        'TICKET_IP'                     => $ticket->ip,
                        'TICKET_DOMAIN'                 => $ticket->domain,
                        'TICKET_TYPE_NAME'              => trans("types.type.{$ticket->type_id}.name"),
                        'TICKET_TYPE_DESCRIPTION'       => trans("types.type.{$ticket->type_id}.description"),
                        'TICKET_CLASS_NAME'             => trans("classifications.{$ticket->class_id}.name"),
                        'TICKET_EVENT_COUNT'            => $ticket->events->count(),
                    ];

                    $box = config("{$this->configBase}.templates.{$notificationType}_box");

                    if (empty($box)) {
                        return $this->failed(
                            'Configuration error for notifier. Not all required fields are configured'
                        );
                    }

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

                    $subject = config("{$this->configBase}.templates.subject");
                    $mail = config("{$this->configBase}.templates.mail");

                    foreach ($replacements as $search => $replacement) {
                        $mail = str_replace("<<{$search}>>", $replacement, $mail);
                    }

                    $iodef = new Iodef\Writer();
                    $iodef->formatOutput = true;
                    $iodef->write(
                        [
                            [
                                'name' => 'IODEF-Document',
                                'attributes' => $this->iodefDocument->getAttributes(),
                                'value' => $this->iodefDocument,
                            ]
                        ]
                    );
                    $XmlAttachmentData = $iodef->outputMemory();

                    $message = Swift_Message::newInstance();

                    if (!empty(Config::get('mail.smime.enabled')) &&
                        (Config::get('mail.smime.enabled')) === true &&
                        !empty(Config::get('mail.smime.certificate')) &&
                        !empty(Config::get('mail.smime.key')) &&
                        is_file(Config::get('mail.smime.certificate')) &&
                        is_file(Config::get('mail.smime.key'))
                    ) {
                        $smimeSigner = Swift_Signers_SMimeSigner::newInstance();
                        $smimeSigner->setSignCertificate(
                            Config::get('mail.smime.certificate'),
                            Config::get('mail.smime.key')
                        );
                        $message->attachSigner($smimeSigner);
                    }

                    $message->setFrom(
                        [
                            Config::get('main.notifications.from_address') =>
                                Config::get('main.notifications.from_name')
                        ]
                    );

                    $message->setTo(
                        [
                            $recipient
                        ]
                    );

                    if (!empty(Config::get('main.notifications.bcc_enabled'))) {
                        $message->setBcc(
                            [
                                (Config::get('main.notifications.bcc_address'))
                            ]
                        );
                    }

                    $message->setPriority(1);

                    $message->setSubject($subject);

                    $message->setBody($mail, 'text/plain');

                    $message->attach(Swift_Attachment::newInstance($XmlAttachmentData, 'iodef.xml', 'text/xml'));

                    $transport = Swift_SmtpTransport::newInstance();

                    $transport->setHost(config('mail.host'));
                    $transport->setPort(config('mail.port'));
                    $transport->setUsername(config('mail.username'));
                    $transport->setPassword(config('mail.password'));
                    $transport->setAuthMode(config('mail.encryption'));
                    $transport->setEncryption(config('mail.encryption'));

                    $mailer = Swift_Mailer::newInstance($transport);

                    if (!$mailer->send($message)) {
                        return $this->failed(
                            "Error while sending message to {$recipient}"
                        );
                    }

                }

            }

        }

        return $this->success();

    }

    /**
     * Adds IOdef data from this ticket to the document
     *
     * @param object $ticket Ticket model
     * @param string $token ASH token
     * @param string $ashUrl ASH Url
     */
    private function addIodefObject($ticket, $token, $ashUrl)
    {
        // Add report type, origin and date
        $incident = new Iodef\Elements\Incident();
        $incident->setAttributes(
            [
                'purpose'       => 'reporting'
            ]
        );

        // Add ASH Link
        $ashlink = new Iodef\Elements\AdditionalData;
        $ashlink->setAttributes(
            [
                'dtype'         => 'string',
                'meaning'       => 'ASH Link',
                'restriction'   => 'private',
            ]
        );
        $ashlink->value = $ashUrl . $token;
        $incident->addChild($ashlink);

        // Add ASH Token seperatly
        $ashtoken = new Iodef\Elements\AdditionalData;
        $ashtoken->setAttributes(
            [
                'dtype'         => 'string',
                'meaning'       => 'ASH Token',
                'restriction'   => 'private',
            ]
        );
        $ashtoken->value = $token;
        $incident->addChild($ashtoken);

        // Add Incident data
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

                // Add the IP and Domain data to each record
                $flow = new Iodef\Elements\Flow;

                $system = new Iodef\Elements\System;
                $system->setAttributes(
                    [
                        'category' => 'source',
                        'spoofed' => 'no'
                    ]
                );

                $node = new Iodef\Elements\Node;

                if (!empty($ticket->ip)) {
                    $address = new Iodef\Elements\Address;
                    $category = [];
                    if (filter_var($ticket->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                        $category['category'] = 'ipv4-addr';
                    } elseif (filter_var($ticket->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                        $category['category'] = 'ipv6-addr';
                    } else {
                        $category['category'] = 'ext-value';
                        $category['ext-category'] = 'unknown';

                    }

                    $address->setAttributes($category);
                    $address->value = $ticket->ip;

                    $node->addChild($address);

                }

                if (!empty($ticket->domain)) {
                    $domain = new Iodef\Elements\NodeName;
                    $domain->value = $ticket->domain;

                    $node->addChild($domain);
                }

                $system->addChild($node);
                $flow->addChild($system);
                $eventData->addChild($flow);


                // Now actually add event data
                $record = new Iodef\Elements\Record;
                $record->setAttributes(
                    [
                        'restriction'   => 'need-to-know',
                    ]
                );

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
                        'ext-dtype'  => 'json',
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
                        'restriction' => 'need-to-know'
                    ]
                );

                $historyDateTime = new Iodef\Elements\DateTime;
                $historyDateTime->value = date('Y-m-d\TH:i:sP', strtotime($note->created_at));
                $historyItem->addChild($historyDateTime);

                $historySubmitter = new Iodef\Elements\AdditionalData;
                $historySubmitter->setAttributes(
                    [
                        'meaning' => 'submitter',
                    ]
                );
                $historySubmitter->value = "{$note->submitter}";
                $historyItem->addChild($historySubmitter);

                $historyNote = new Iodef\Elements\AdditionalData;
                $historyNote->setAttributes(
                    [
                        'meaning' => 'text',
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
