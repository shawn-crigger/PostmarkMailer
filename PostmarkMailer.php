<?php

use LimeSurvey\PluginManager\PluginEvent;

class PostmarkMailer extends PluginBase
{
    protected $storage = 'DbStorage';
    static protected $description = 'Send LimeSurvey emails using the Postmark API';
    static protected $name = 'PostmarkMailer';

    protected $settings = array(
        'api_key' => array(
            'type' => 'string',
            'label' => 'Postmark API Key'
        ),
        'from_email' => array(
            'type' => 'string',
            'label' => 'From Email'
        ),
        'from_name' => array(
            'type' => 'string',
            'label' => 'From Name'
        ),
        'message_stream' => array(
            'type' => 'string',
            'label' => 'Message Stream'
        ),
    );

    public function init()
    {

        // @todo: finish this one day
        // $this->subscribe('listEmailPlugins');
        // $this->subscribe('afterSelectEmailPlugin');
        $this->subscribe('beforeTokenEmail');
        $this->subscribe('beforeSurveyEmail');
    }

    public function beforeTokenEmail()
    {
        $this->processEmail();
    }

    public function beforeSurveyEmail()
    {
        $this->processEmail();
    }

    private function processEmail()
    {
        $to = $this->event->get('to');
        $subject = $this->event->get('subject');
        $body = $this->event->get('body');

        if (empty($to) || empty($subject) || empty($body)) {
            $this->log('Skipped sending email due to missing fields.');
            return;
        }

        $apiKey = trim($this->get('api_key'));
        $fromEmail = trim($this->get('from_email'));
        $fromName = trim($this->get('from_name'));
        $stream = trim($this->get('message_stream'));

        if (empty($stream)) {
            $stream = 'outbound';
        }

        if (empty($apiKey) || empty($fromEmail)) {
            $this->log('PostmarkMailer not configured properly (missing API key or from address).');
            return;
        }

        $payload = [
            'From' => "{$fromName} <{$fromEmail}>",
            'To' => $to,
            'Subject' => $subject,
            'HtmlBody' => $body,
            'MessageStream' => $stream,
        ];

        $ch = curl_init('https://api.postmarkapp.com/email');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                "X-Postmark-Server-Token: {$apiKey}",
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status === 200) {
            $this->event->set('send', false);
            $this->log("Email successfully sent to {$to} via Postmark.");
        } else {
            $this->log("Postmark API error ({$status}): {$response}");
        }
    }

    /**
     * Adds the plugin to the list of email plugins
     * @todo: finish this one day
     */
    public function listEmailPlugins()
    {
        $event = $this->getEvent();
        $event->append('plugins', [
            'postmark' => $this->getEmailPluginInfo()
        ]);
    }

    /**
     * Handles the afterSelectEmailPlugin event, triggered when the plugin
     * is selected as email plugin in Global Settings
     * @todo: finish this one day
     */
    public function afterSelectEmailPlugin()
    {
        $setupStatus = $this->getSetupStatus();
        if ($setupStatus !== self::SETUP_STATUS_VALID_REFRESH_TOKEN) {
            $event = $this->getEvent();
            $event->set('warning', sprintf(gT("The %s plugin is not configured correctly. Please check the plugin settings."), self::getName()));
        }
    }

    public function log($msg, $level = CLogger::LEVEL_TRACE)
    {
        \LimeSurvey\Logger\Logger::log('[PostmarkMailer] ' . $msg);
    }
}
