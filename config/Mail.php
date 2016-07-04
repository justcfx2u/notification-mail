<?php

return [
    'notification' => [
        'name'          => 'Mail',
        'description'   => 'Sends notifications using e-mail',
        'enabled'       => true,
    ],

    'templates' => [

        'ip_box'                        => '
[Ticket <<TICKET_NUMBER>>] Reporting <<TICKET_TYPE_NAME>> (<<TICKET_CLASS_NAME>>) for IP addres: <<TICKET_IP>>
<<TICKET_TYPE_DESCRIPTION>>
More information at: <<IP_CONTACT_ASH_LINK>>
                                           ',

        'domain_box'                    => '
[Ticket <<TICKET_NUMBER>>] Reporting <<TICKET_TYPE_NAME>> (<<TICKET_CLASS_NAME>>) for domain name: <<TICKET_DOMAIN>>
<<TICKET_TYPE_DESCRIPTION>>
More information at: <<DOMAIN_CONTACT_ASH_LINK>>
                                           ',

        'subject'                       => '[' . date('Y-m-d') . '] Notification of (possible) abuse ticket(s)',

        'html_mail'                          => '
<html>
<img>
<p>
Dear Customer,
</p><p>
You have received this report because the <<TICKET_COUNT>> IP address(es) and/or domains linked
to this report are under your control. We ask you to examine this report and take the
necessary action(s).
</p><p>
After resolving the matter we like to receive feedback from you which measures you
have taken to resolve and prevent new reports. You can leave your feedback by clicking
the URL next to each report. The portal URL also contains additional information how
to solve the problem this reports entails.
</p><p>
<<BOXES>>
</p><p>
Some reports are not considered abuse but more informational to the fact you are running
a service that might be susceptible to abuse in the feature. While we believe preventing
abuse is better then resolving it, we ask you to take measures to prevent actual abuse of
your service. However if you feel this service is running as intended you can choose to
ignore these informational notifications by tagging the ignore option at the portal URL.
Warning: Ignoring abuse reports could lead to actions listed in our Abuse Policy or
Acceptable Use Policy!
</p><p>
All the information we received and are allowed to share on these report(s) are listed
in in the portal. In most cases we received this report externally and do not have any
more information nor can we put you in contact with the original reporter.
</p><p>
With regards,<br/>
<br/>
ISP Abuse Department
</p>
<img width="160px" src="<<LOGO_SRC>>"/>
</body>
</html>',
        'plain_mail'                          => '
Dear Customer,

You have received this report because the <<TICKET_COUNT>> IP address(es) and/or domains linked
to this report are under your control. We ask you to examine this report and take the
necessary action(s).

After resolving the matter we like to receive feedback from you which measures you
have taken to resolve and prevent new reports. You can leave your feedback by clicking
the URL next to each report. The portal URL also contains additional information how
to solve the problem this reports entails.

<<BOXES>>

Some reports are not considered abuse but more informational to the fact you are running
a service that might be susceptible to abuse in the feature. While we believe preventing
abuse is better then resolving it, we ask you to take measures to prevent actual abuse of
your service. However if you feel this service is running as intended you can choose to
ignore these informational notifications by tagging the ignore option at the portal URL.
Warning: Ignoring abuse reports could lead to actions listed in our Abuse Policy or
Acceptable Use Policy!

All the information we received and are allowed to share on these report(s) are listed
in in the portal. In most cases we received this report externally and do not have any
more information nor can we put you in contact with the original reporter.

With regards,

ISP Abuse Department
                                           ',

    ],

];
