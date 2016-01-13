<?php
	/**
	 * @package Nomadcode\Component\Mailform\Mailform
 	 */

    namespace Nomadcode\Component\Mailform;
    
	use Monolog\Logger;
	use Monolog\Handler\StreamHandler;
    
	const DEFAULT_MAILFORM_SETTINGS = array('salt' => 'some salt',
											'recipient' => 'webmaster',
											'prefix' => 'web form',
											'log' => '',
											'checksum_failure_log' => '');
    
    /**
	 * @property string $salt Salt used for generating message checksums.
	 * @property string $recipient Recipient email address.
	 * @property string $prefix Prefix to be added to subject of email.
	 * @property string $logfile Path to message logfile
	 * @property string $checksum_failure_logfile Path to logfile for failed messages
	 */
    
    class Mailform
    {
    	protected $salt;
    	protected $recipient;
    	protected $prefix;
    	protected $logfile;
    	protected $logger;
    	protected $checksum_failure_logfile;
    	
    	/**
 		 * Constructor
 		 *
 		 * Construct a Mailform object.
 		 * 
 		 * @param array $settings Array containing settings for the Mailform.
		 */
		 
		public function __construct($settings = "")
		{
			if (!$settings) {
				$settings = DEFAULT_MAILFORM_SETTINGS;
			}
			$this->salt = $settings['salt'];
			$this->recipient = $settings['recipient'];
			$this->prefix = $settings['prefix'];
			if (isset($settings['log']) and $settings['log'])
			{
				$this->logfile = $settings['log'];
				$this->logger = new Logger('mail');
				$this->logger->pushHandler(new StreamHandler($this->logfile));
			}
			if (isset($settings['checksum_failure_log']) and
				$settings['checksum_failure_log'])
			{
				$this->checksum_failure_logfile = $settings['checksum_failure_log'];
			}
		}
		
		/**
		 * Inject a mailform into a page (and possibly send mail).
		 *
		 * This is the main method for adding a mailform to a page. It
		 * is controlled by the presence of certain $_POST variables. If
		 * there is no $_POST data, it will render an empty mailform, ready
		 * to accept information. If data has been supplied, it will render
		 * an interstitial confirmation page. If a checksum is included in
		 * the data, it will send the message.
		 */
		 
		public function process()
		{
			if ($this->has_checksum()) {
				print $this->render_step3();
			}
			else if ($this->has_data()) {
				print $this->render_step2();
			}
			else {
				print $this->render_step1();
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
		 
		public function has_checksum() {
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
		 
		public function has_data() {
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
        	return <<<EndOfHTML
<form action="#" method="POST">
	<div>
		<label for="mail_from">From</label>
		<input type="text" name="mail_from" id="mail_from" placeholder="Your Name" />
	</div>
	<div>
		<label for="mail_email">Email</label>
		<input type="email" name="mail_email" id="mail_email" placeholder="you@yourdomain.com" required="required"/>
	</div>
	<div>
		<label for="mail_subject">Subject</label>
		<input type="text" name="mail_subject" id="mail_subject" placeholder="Comment" />
	</div>
	<div>
		<label for="mail_message">Message</label>
		<textarea id="mail_message" name="mail_message" placeholder="Enter your message here" required="required" rows="8"></textarea>
	</div>
	<div>
		<button name="submit" type="submit" value="submit">Send Message</button>
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
			$mail_message = $this->get_form_value($_POST,'mail_message',"");
			
			$mail_from_encoded = htmlentities($mail_from, ENT_QUOTES, 'UTF-8');
			$mail_email_encoded = htmlentities($mail_email, ENT_QUOTES, 'UTF-8');
			$mail_subject_encoded = htmlentities($mail_subject, ENT_QUOTES, 'UTF-8');
			$mail_message_encoded = htmlentities($mail_message, ENT_QUOTES, 'UTF-8');
			
			$mail_checksum = $this->generate_mail_checksum($mail_from,
														   $mail_email,
														   $mail_subject,
														   $mail_message);

			// Validate email
			
			if (filter_var($mail_email, FILTER_VALIDATE_EMAIL) === FALSE) {
				return $this->render_notification('failure',MailformStrings::MESSAGE_INVALID_EMAIL);
			}
			
			// Message must be non-empty
			
			if ($mail_message == "") {
				return $this->render_notification('failure',MailformStrings::MESSAGE_EMPTY_MESSAGE);
			}
			
			// Output a confirmation page showing the complete email.
			
			$mail_content_length = strlen($mail_message);
			$interim_message = MailformStrings::MESSAGE_NOTICE_INTERIM;
			return <<<EndOfHTML
<div class="mail_notice_interim">
	<p>$interim_message</p>
</div>
<div class="mail_summary">
	<div>
		<div class="mail_field_label">From</div>
		<div class="mail_field_value">$mail_from_encoded ($mail_email_encoded)</div>
	</div>
	<div>	
		<div class="mail_field_label">Subject</div>
		<div class="mail_field_value">$mail_subject_encoded</div>
	</div>
	<div>
		<div class="mail_field_label">Message</div>
		<div class="mail_field_value">$mail_message_encoded</div>
	</div>
	<div>
		<form action="#" method="POST">
			<input type="hidden" name="mail_from" value="$mail_from_encoded" id="mail_from">
			<input type="hidden" name="mail_email" value="$mail_email_encoded" id="mail_email">
			<input type="hidden" name="mail_subject" value="$mail_subject_encoded" id="mail_subject">
			<input type="hidden" name="mail_message" value="$mail_message_encoded" id="mail_message">
			<input type="hidden" name="mail_digest" value="$mail_checksum" id="mail_digest">
			<input type="hidden" name="mail_content_length" value="$mail_content_length" id="mail_content_length">
			<button name="submit" type="submit" value="submit">Send Message</button>	
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
			$mail_checksum = $_POST['mail_digest'];
			$mail_content_length = $_POST['mail_content_length'];
			
			if (!$this->verify_checksum($mail_checksum, $mail_content_length, $mail_from, 
									    $mail_email, $mail_subject, $mail_message)) 
			{
				return $this->render_notification(
					'failure', 
					MailformStrings::MESSAGE_CHECKSUM_FAILURE);  
    		}
    		else if (!$this->send_message($mail_from, $mail_email, 
    									  $mail_subject, $mail_message)) 
    		{
    			return $this->render_notification(
    				'failure', 
    				MailformStrings::MESSAGE_SUBMISSION_FAILURE);
    		}
    		else {
    			return $this->render_notification(
    				'success', 
    				MailformStrings::MESSAGE_SUBMISSION_SUCCESS);
    		}
    	}
    	
    	public function verify_checksum($mail_checksum, $mail_content_length, $mail_from,
    									$mail_email, $mail_subject, $mail_message)
    	{
    		$computed_checksum = $this->generate_mail_checksum($mail_from, 
    														   $mail_email,
    														   $mail_subject, 
    														   $mail_message);
    		if ($computed_checksum == $mail_checksum)
    		{
    			return TRUE;
    		}
    		if ($this->logger) 
    		{
				$recipient = $this->get_message_destination();
				$this->logger->addInfo("Checksum failure", 
										 array('to' => $recipient,
											   'from' => $mail_email,
											   'name' => $mail_from,
											   'subject' => $mail_subject,
											   'size' => strlen($mail_message),
											   'expected_size' => $mail_content_length,
											   'checksum' => $computed_checksum,
											   'expected_checksum' => $mail_checksum,
											   'ip' => $_SERVER['REMOTE_ADDR'],
											   'script' => $_SERVER['SCRIPT_FILENAME']));
			}
			return FALSE;
    	}
    	
    	public function send_message($mail_from,$mail_email,$mail_subject,$mail_message) {
			$headers = "From: $mail_from ($mail_email)" . "\r\n" .
					   "X-Mailer: PHP/" . phpversion() . "\r\n" .
					   "X-Server: " . $_SERVER['SERVER_NAME'] . "\r\n" .
					   "X-Submitter-IP: " . $_SERVER['REMOTE_ADDR'] . "\r\n" .
					   "X-User-Agent: " . $_SERVER['HTTP_USER_AGENT'] . "\r\n" .
					   "X-Script-Name: " . $_SERVER['SCRIPT_FILENAME'];
			$recipient = $this->get_message_destination();
			
			$success = mail($recipient,
							$this->get_prefixed_subject($mail_subject),
							$mail_message,
							$headers);
			$status = ( $success ? "sent" : "not sent" );
			try 
			{
				if ($this->logger) {
					$this->logger->addInfo("Message $status", 
											 array('to' => $recipient,
												   'from' => $mail_email,
												   'name' => $mail_from,
												   'subject' => $mail_subject,
												   'size' => strlen($mail_message),
												   'succeeded' => $success,
												   'ip' => $_SERVER['REMOTE_ADDR'],
												   'script' => $_SERVER['SCRIPT_FILENAME']));
				}
			}
			catch (LogicException $e)  
			{
				
			}
			return $status;
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
    		return <<<EndOfHTML
<div class="mail_notification_$status">$message</div>
EndOfHTML;
    	}
    	
    	/**
    	 * Generate a checksum for a message.
    	 *
    	 * Generate an MD5 checksum for a message, based on the contents of the
    	 * message and a salt supplied as part of the Mailform object's settings.
    	 *
    	 * @param string $from Name of the user
    	 * @param string $email Email address of the user
    	 * @param string $subject Subject of the message
    	 * @param string $message Content of the message
    	 * @return string A hex string
    	 */
    	  
    	public function generate_mail_checksum($from,$email,$subject,$message) 
    	{
    		return md5($this->salt . $from . $email . $subject . $message);
    	}
    	
    	/**
    	 * Get a supplied form value
    	 *
    	 * Safely get a value from an array of data (i.e. $_POST). The
    	 * value is trimmed before returning it. If the requested item
    	 * does not exist, a default value is returned.
    	 *
    	 * @param Array $data An array of values
    	 * @param string $key A key such as 'mail_from'
    	 * @param string $default A default value
    	 * @return string A value
    	 */
    	 
    	public function get_form_value($data, $key, $default="")
    	{
    		if (isset($data[$key])) 
    		{
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
    		if (strpos($this->recipient,"@") === FALSE) {
    			if (isset($_SERVER['SERVER_NAME'])) {
    				return $this->recipient . "@" . $_SERVER["SERVER_NAME"];
    			}
    		}
			return $this->recipient;
    	}
    }
    
