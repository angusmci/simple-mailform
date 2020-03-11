<?php
/**
 * @package Nomadcode\Component\Mailform\Mailform
 */

namespace Nomadcode\Component\Mailform;

use Error;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

const DEFAULT_MAILFORM_SETTINGS = array('salt' => 'some salt',
    'recipient' => 'webmaster',
    'prefix' => 'web form',
    'log' => '',
    'check_failure_log' => '',
    'hash_algorithm' => 'sha256',
    'debug' => false,
    'placeholders' => false,
    'capture_dump_file' => '');
const DEFAULT_TEXTLINES = 8;

const MAXIMUM_BODY_SIZE = 10 * 1024;

/**
 * @property string $salt Salt used for generating message hashes.
 * @property string $recipient Recipient email address.
 * @property string $prefix Prefix to be added to subject of email.
 * @property string $logfile Path to message logfile
 * @property string $check_failure_logfile Path to logfile for failed messages
 */
class Mailform
{
    protected $salt;
    protected $recipient;
    protected $prefix;
    protected $logfile;
    protected $logger;
    protected $check_failure_logfile;
    protected $greeting;
    protected $textlines;
    protected $algorithm;
    protected $error;
    protected $debug;
    protected $placeholders;
    protected $capture_dump_file;

    /**
     * Constructor
     *
     * Construct a Mailform object.
     *
     * @param array $settings Array containing settings for the Mailform.
     * @param string $message
     * @param int $textlines
     */

    public function __construct($settings = DEFAULT_MAILFORM_SETTINGS,
                                $message = MailformStrings::DEFAULT_GREETING,
                                $textlines = DEFAULT_TEXTLINES)
    {
        if (isset($settings)) {
            $settings = array_merge(DEFAULT_MAILFORM_SETTINGS, $settings);
        } else {
            $settings = DEFAULT_MAILFORM_SETTINGS;
        }
        $this->greeting = $message;
        $this->textlines = $textlines;
        $this->salt = $settings['salt'];
        $this->recipient = $settings['recipient'];
        $this->prefix = $settings['prefix'];
        $this->algorithm = $settings['hash_algorithm'];
        $this->debug = $settings['debug'];
        $this->placeholders = $settings['placeholders'];
        $this->capture_dump_file = $settings['capture_dump_file'];

        if (isset($settings['log']) and $settings['log']) {
            $this->logfile = $settings['log'];
            $this->logger = new Logger('mail');
            $this->logger->pushHandler(new StreamHandler($this->logfile));
        }
        if (isset($settings['check_failure_log']) and
            $settings['check_failure_log']
        ) {
            $this->check_failure_logfile = $settings['check_failure_log'];
        }
        $this->error = "";

    }

    /**
     * Inject a mailform into a page (and possibly send mail).
     *
     * This is the main method for adding a mailform to a page. It
     * is controlled by the presence of certain $_POST variables. If
     * there is no $_POST data, it will render an empty mailform, ready
     * to accept information. If data has been supplied, it will render
     * an interstitial confirmation page. If a hash is included in
     * the data, it will send the message.
     */

    public function process()
    {
        if ($this->has_digest()) {
            print $this->render_step3();
        } else if ($this->has_data()) {
            $this->dump_message();
            print $this->render_step2();
        } else {
            print $this->render_step1();
        }
    }

    /**
     * Dump the initial data entered by the user to a dump file.
     *
     * This is primarily a debugging function. Output will only be written
     * if the configuration file includes a 'capture_dump_file' setting.
     */

    public function dump_message()
    {
        if ($this->capture_dump_file != '') {
            $fp = fopen($this->capture_dump_file, "a");
            $mail_from = $this->get_form_value($_POST, 'mail_from',
                MailformStrings::DEFAULT_FROM);
            $mail_email = $this->get_form_value($_POST, 'mail_email', "");
            $mail_subject = $this->get_form_value($_POST, 'mail_subject',
                MailformStrings::DEFAULT_SUBJECT);
            $mail_message = $this->get_form_value($_POST, 'mail_message', "");
            $ip = $this->get_form_value($_SERVER, 'REMOTE_ADDR', '');
            $script = $this->get_form_value($_SERVER, 'SCRIPT_FILENAME', '');
            $date = date("c");
            $record = <<<EndOfText
Date: {$date}
Name: {$mail_from}
Email: {$mail_email}
Subject: {$mail_subject}
Source-IP: {$ip}
Source-Script: {$script}
Text:
$mail_message
--------------------------------------------------------------------------------

EndOfText;
            try {
                if (flock($fp, LOCK_EX)) {
                    fwrite($fp, $record);
                    fflush($fp);
                    flock($fp, LOCK_UN);
                }
                fclose($fp);
            } catch (Error $e) {
                // Do nothing -- failure to write a dump is not a
                // critical error, so we can ignore it.
            }
        }
    }

    /**
     * Test if supplied data includes a checksum.
     *
     * Check the data in the $_POST array to see whether it includes
     * the 'mail_digest' checksum argument.
     *
     * @return Boolean Return TRUE if a checksum is present.
     */

    public function has_digest()
    {
        return array_key_exists('mail_digest', $_POST);
    }

    /**
     * Test if any user-supplied data is available.
     *
     * Check the data in the $_POST array to see if it contains any
     * of the expected data sent by the user, i.e. 'from', 'email',
     * 'subject' or 'message'.
     *
     * @return Boolean Return TRUE if data is present.
     */

    public function has_data()
    {
        return array_key_exists('mail_from', $_POST) or
            array_key_exists('mail_email', $_POST) or
            array_key_exists('mail_subject', $_POST) or
            array_key_exists('mail_message', $_POST);
    }

    /**
     * Return the HTML for a mail form.
     *
     * Return a string of HTML5 that defines a simple mail form.
     *
     * @return string Some HTML text.
     */

    public function render_step1()
    {
        $from_placeholder = "";
        $email_placeholder = "";
        $subject_placeholder = "";
        $message_placeholder = "";
        if ($this->placeholders) {
            $from_placeholder = MailformStrings::PLACEHOLDER_FROM;
            $email_placeholder = MailformStrings::PLACEHOLDER_EMAIL;
            $subject_placeholder = MailformStrings::PLACEHOLDER_SUBJECT;
            $message_placeholder = MailformStrings::PLACEHOLDER_MESSAGE;
        }
        $label_from = MailformStrings::LABEL_FROM;
        $label_email = MailformStrings::LABEL_EMAIL;
        $label_subject = MailformStrings::LABEL_SUBJECT;
        $label_message = MailformStrings::LABEL_MESSAGE;
        $label_submit = MailformStrings::LABEL_PREVIEW;

        return <<<EndOfHTML
<div class="mailform__greeting">
    <p>$this->greeting</p>
</div>
<form class="mailform__form" action="#" method="POST">
    <div>
        <label for="mail_from" class="mailform__label">{$label_from}</label>
        <input type="text" name="mail_from" id="mail_from" class="mailform__value" placeholder="{$from_placeholder}" />
    </div>
    <div>
        <label for="mail_email" class="mailform__label mailform__label--required">{$label_email}</label>
        <input type="email" name="mail_email" id="mail_email" class="mailform__value" placeholder="{$email_placeholder}" required="required"/>
    </div>
    <div>
        <label for="mail_subject" class="mailform__label">{$label_subject}</label>
        <input type="text" name="mail_subject" id="mail_subject" class="mailform__value" placeholder="{$subject_placeholder}" />
    </div>
    <div>
        <label for="mail_message" class="mailform__label mailform__label--required">{$label_message}</label>
        <textarea id="mail_message" name="mail_message" class="mailform__value" placeholder="{$message_placeholder}" required="required" rows="$this->textlines"></textarea>
    </div>
    <div>
        <button name="submit" type="submit" value="submit" class="mailform__button">{$label_submit}</button>
    </div>
</form>
EndOfHTML;
    }

    /**
     * Return the HTML for an interstitial page.
     *
     * Return a string of HTML5 that defines an interstitial page, or a
     * validation error message if the data supplied was not valid.
     *
     * @return string Some HTML text.
     */

    public function render_step2()
    {
        $mail_from = $this->get_form_value($_POST, 'mail_from',
            MailformStrings::DEFAULT_FROM);
        $mail_email = $this->get_form_value($_POST, 'mail_email', "");
        $mail_subject = $this->get_form_value($_POST, 'mail_subject',
            MailformStrings::DEFAULT_SUBJECT);
        $mail_message = $this->get_form_value($_POST, 'mail_message', "");

        $mail_from_encoded = htmlentities($mail_from, ENT_QUOTES, 'UTF-8');
        $mail_email_encoded = htmlentities($mail_email, ENT_QUOTES, 'UTF-8');
        $mail_subject_encoded = htmlentities($mail_subject, ENT_QUOTES, 'UTF-8');
        $mail_message_encoded = htmlentities($mail_message, ENT_QUOTES, 'UTF-8');
        $mail_message_formatted = str_replace("\n", "<br />", $mail_message_encoded);

        $mail_digest = $this->generate_mail_hash($mail_from,
            $mail_email,
            $mail_subject,
            $mail_message);

        // Validate email

        if (filter_var($mail_email, FILTER_VALIDATE_EMAIL) === FALSE) {
            return $this->render_notification('failure', MailformStrings::MESSAGE_INVALID_EMAIL);
        }

        // Message must be non-empty

        if ($mail_message == "") {
            return $this->render_notification('failure', MailformStrings::MESSAGE_EMPTY_MESSAGE);
        }

        // Output a confirmation page showing the complete email.

        $mail_content_length = strlen($mail_message);
        $interim_message = MailformStrings::MESSAGE_NOTICE_INTERIM;
        $label_from = MailformStrings::LABEL_FROM;
        $label_subject = MailformStrings::LABEL_SUBJECT;
        $label_message = MailformStrings::LABEL_MESSAGE;
        $label_submit = MailformStrings::LABEL_SEND;
        return <<<EndOfHTML
<div class="mailform__interim">
    <p>$interim_message</p>
</div>
<div class="mailform__summary">
    <div>
        <div class="mailform__label">{$label_from}</div>
        <div class="mailform__value">$mail_from_encoded ($mail_email_encoded)</div>
    </div>
    <div>   
        <div class="mailform__label">{$label_subject}</div>
        <div class="mailform__value">$mail_subject_encoded</div>
    </div>
    <div>
        <div class="mailform__label">{$label_message}</div>
        <div class="mailform__value">$mail_message_formatted</div>
    </div>
    <div>
        <form action="#" method="POST">
            <input type="hidden" name="mail_from" value="$mail_from_encoded" id="mail_from">
            <input type="hidden" name="mail_email" value="$mail_email_encoded" id="mail_email">
            <input type="hidden" name="mail_subject" value="$mail_subject_encoded" id="mail_subject">
            <input type="hidden" name="mail_message" value="$mail_message_encoded" id="mail_message">
            <input type="hidden" name="mail_digest" value="$mail_digest" id="mail_digest">
            <input type="hidden" name="mail_content_length" value="$mail_content_length" id="mail_content_length">
            <div>
                <button name="submit" type="submit" class="mailform__button" value="submit">{$label_submit}</button>
            </div>   
        </form>
    </div>
</div>
EndOfHTML;
    }

    /**
     * Return the HTML for a confirmation page.
     *
     * Return a string of HTML5 that defines a confirmation (success or failure) page.
     *
     * @return string Some HTML text.
     */

    public function render_step3()
    {
        $mail_from = $_POST['mail_from'];
        $mail_email = $_POST['mail_email'];
        $mail_subject = $_POST['mail_subject'];
        $mail_message = $_POST['mail_message'];
        $mail_digest = $_POST['mail_digest'];
        $mail_content_length = $_POST['mail_content_length'];

        if (!$this->verify_digest($mail_digest, $mail_content_length, $mail_from,
            $mail_email, $mail_subject, $mail_message)
        ) {
            return $this->render_notification(
                'failure',
                MailformStrings::MESSAGE_CHECKSUM_FAILURE);
        } else if (!$this->send_message($mail_from, $mail_email,
            $mail_subject, $mail_message)
        ) {
            return $this->render_notification(
                'failure',
                MailformStrings::MESSAGE_SUBMISSION_FAILURE);
        } else {
            return $this->render_notification(
                'success',
                MailformStrings::MESSAGE_SUBMISSION_SUCCESS);
        }
    }

    /**
     * Verify the digest passed with the message.
     *
     * Verify that the digest matches the content of the message.
     *
     * @param $mail_digest
     * @param $mail_content_length
     * @param $mail_from
     * @param $mail_email
     * @param $mail_subject
     * @param $mail_message
     * @return bool TRUE or FALSE.
     */

    public function verify_digest($mail_digest, $mail_content_length, $mail_from,
                                  $mail_email, $mail_subject, $mail_message)
    {
        $computed_checksum = $this->generate_mail_hash($mail_from,
            $mail_email,
            $mail_subject,
            $mail_message);
        if ($computed_checksum == $mail_digest) {
            return TRUE;
        }
        if ($this->logger) {
            $recipient = $this->get_message_destination();
            $this->logger->addInfo("Checksum failure",
                array('to' => $recipient,
                    'from' => $mail_email,
                    'name' => $mail_from,
                    'subject' => $mail_subject,
                    'size' => strlen($mail_message),
                    'expected_size' => $mail_content_length,
                    'checksum' => $computed_checksum,
                    'expected_checksum' => $mail_digest,
                    'ip' => $_SERVER['REMOTE_ADDR'],
                    'script' => $_SERVER['SCRIPT_FILENAME']));
        }
        return FALSE;
    }

    /**
     * Send the message.
     *
     * Use PHP's 'mail' function to send the message.
     *
     * @param string $mail_from Name of sender
     * @param string $mail_email Email of sender
     * @param string $mail_subject Subject of message
     * @param string $mail_message Body of message
     * @return boolean TRUE if message was sent.
     */

    public function send_message($mail_from, $mail_email, $mail_subject, $mail_message)
    {
        $mail_email = $this->filter_email($mail_email);
        $mail_from = $this->filter_name($mail_from);
        $mail_subject = $this->filter_other($mail_subject);
        $remote_addr = $this->filter_other($_SERVER['REMOTE_ADDR']);
        $http_user_agent = $this->filter_other($_SERVER['HTTP_USER_AGENT']);
        $headers = "From: $mail_from <$mail_email>" . "\r\n" .
            "X-Mailer: PHP/" . phpversion() . "\r\n" .
            "X-Server: " . $_SERVER['SERVER_NAME'] . "\r\n" .
            "X-Server-IP: " . $_SERVER['SERVER_ADDR'] . "\r\n" .
            "X-Submitter-IP: " . $remote_addr . "\r\n" .
            "X-User-Agent: " . $http_user_agent . "\r\n" .
            "X-Script-Name: " . $_SERVER['SCRIPT_FILENAME'];
        $recipient = $this->get_message_destination();
        $mail_message = $this->truncate_message_body($mail_message);

        $this->error = "";
        $success = mail($recipient,
            $this->get_prefixed_subject($mail_subject),
            $mail_message,
            $headers);
        $status = ($success ? "sent" : "not sent");
        if (!$success) {
            $this->error = error_get_last()['message'] . " in " .
                error_get_last()['file'] . " at line " .
                error_get_last()['line'];
        }

        try {
            if ($this->logger) {
                $this->logger->addInfo("Message $status",
                    array('to' => $recipient,
                        'from' => $mail_email,
                        'name' => $mail_from,
                        'subject' => $mail_subject,
                        'size' => strlen($mail_message),
                        'succeeded' => $success,
                        'error' => $this->error,
                        'ip' => $_SERVER['REMOTE_ADDR'],
                        'script' => $_SERVER['SCRIPT_FILENAME']));
            }
        } catch (\LogicException $e) {

        }
        return $success;
    }

    /**
     * Filter email address
     *
     * Filter function to remove nastiness from email addresses.
     *
     * @param string $email Email address
     * @return string
     **/

    private function filter_email($email)
    {
        $rule = array("\r" => '',
            "\n" => '',
            "\t" => '',
            '"' => '',
            ',' => '',
            '<' => '',
            '>' => '');
        return strtr($email, $rule);
    }

    private function filter_name($name)
    {
        $rule = array("\r" => '',
            "\n" => '',
            "\t" => '',
            '"' => "'",
            '<' => '[',
            '>' => ']'
        );
        return trim(strtr($name, $rule));
    }

    private function filter_other($data)
    {
        $rule = array("\r" => '',
            "\n" => '',
            "\t" => '',
        );
        return strtr($data, $rule);
    }

    /**
     * Render a message
     *
     * Return an HTML fragment that represents a notification message.
     *
     * @param string $status 'success' or 'failure'
     * @param string $message Message to output.
     * @return string Some HTML text.
     */

    public function render_notification($status, $message)
    {
        if (($status == "failure") and $this->debug) {
            return <<<EndOfHTML
<div class="mailform__notification mailform__notification--$status">
    <p>$message</p>
    <p>$this->error</p>
</div>
EndOfHTML;
        } else {
            return <<<EndOfHTML
<div class="mailform__notification mailform__notification--$status"><p>$message</p></div>
EndOfHTML;
        }
    }

    /**
     * Generate a hash for a message.
     *
     * Generate a hash for a message, based on the contents of the
     * message and a salt supplied as part of the Mailform object's settings.
     *
     * @param string $from Name of the user
     * @param string $email Email address of the user
     * @param string $subject Subject of the message
     * @param string $message Content of the message
     * @return string A hex string
     */

    public function generate_mail_hash($from, $email, $subject, $message)
    {
        return hash($this->algorithm,
            $this->salt . $from . $email . $subject . $message);
    }

    /**
     * Get a supplied form value
     *
     * Safely get a value from an array of data (i.e. $_POST). The
     * value is trimmed before returning it. If the requested item
     * does not exist, a default value is returned.
     *
     * @param array $data An array of values
     * @param string $key A key such as 'mail_from'
     * @param string $default A default value
     * @return string A value
     */

    public function get_form_value($data, $key, $default = "")
    {
        if (isset($data[$key])) {
            return trim($data[$key]);
        }
        return $default;
    }

    /**
     * Get a message subject with a prefix applied.
     *
     * Get the subject of the message to be sent. The subject is prefixed
     * with a string defined during the initialization of the Mailform, to
     * make it more identifiable. The prefix will usually identify the
     * website that sent the message.
     *
     * @param string $subject The subject of the message
     * @return string The subject with the prefix applied
     */

    public function get_prefixed_subject($subject)
    {
        return $this->prefix . ": " . $subject;
    }

    /**
     * Get the email address of the message recipient
     *
     * Get the address to which the message should be sent. This is
     * defined in the Mailform settings. If the message consists only
     * of a local username, the method will attempt to append the name
     * of the host.
     *
     * @return string An email address
     */

    public function get_message_destination()
    {
        if (strpos($this->recipient, "@") === FALSE) {
            if (isset($_SERVER['SERVER_NAME'])) {
                return $this->recipient . "@" . $_SERVER["SERVER_NAME"];
            }
        }
        return $this->recipient;
    }

    public function truncate_message_body($body)
    {
        return substr($body, 0, MAXIMUM_BODY_SIZE);
    }
}
    
