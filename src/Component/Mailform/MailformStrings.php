<?php
    namespace Nomadcode\Component\Mailform;

	class MailformStrings
	{
		const LANGUAGE = "en";
		const LABEL_FROM = "From";
		const LABEL_EMAIL = "Email";
		const LABEL_SUBJECT = "Subject";
		const LABEL_MESSAGE = "Message";
		const LABEL_SEND = "Send Message";
		const LABEL_PREVIEW = "Preview Message";
		
		const PLACEHOLDER_FROM = "Your Name";
		const PLACEHOLDER_EMAIL = "you@yourdomain.com";
		const PLACEHOLDER_SUBJECT = "Comment";
		const PLACEHOLDER_MESSAGE = "Enter your message here";
		
		const DEFAULT_FROM = "Anonymous";
		const DEFAULT_SUBJECT = "Comment";

		const DEFAULT_GREETING = <<<EndOfText
To send email to the owner of this website, please fill in the form below. Please 
remember to include a valid email address so that we can reply to you.
EndOfText;
		
		const MESSAGE_VALIDATION_FAILURE = <<<EndOfText
Your message could not be sent, because there was a problem with the information that
you entered. Please make sure that you have entered a valid email address, your name,
and the text of your message.
EndOfText;
		const MESSAGE_CHECKSUM_FAILURE = <<<EndOfText
Your message could not be sent, because it appears that you are trying to use an
automated process to send mail. This form only accepts mail from human senders.
EndOfText;
		const MESSAGE_SUBMISSION_FAILURE = <<<EndOfText
Your message could not be sent because an error occurred. Please try again later, or
use an alternative way to contact us.
EndOfText;
		const MESSAGE_SUBMISSION_SUCCESS = <<<EndOfText
Your message has been sent successfully. Thank you.
EndOfText;
		const MESSAGE_INVALID_EMAIL = <<<EndOfText
You did not enter a valid email address. Please use your browser's Back button to go back and try again.
EndOfText;
		const MESSAGE_EMPTY_MESSAGE = <<<EndOfText
You did not enter a message. Please use your browser's Back button to go back and try again.
EndOfText;
		const MESSAGE_NOTICE_INTERIM = <<<EndOfText
Your message has not been sent yet. This is the message that will be sent. If everything looks OK, please click the Send button. 
Otherwise, use your browser's Back button to go back and edit your message.
EndOfText;
	}

