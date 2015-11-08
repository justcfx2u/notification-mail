<?php

namespace AbuseIO\Notification;

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
     * Sends out mail notifications
     * @return boolean  Returns if succeeded or not
     */
    public function send()
    {

        return true;
    }
}
