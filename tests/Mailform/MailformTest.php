<?php
	use Nomadcode\Component\Mailform\Mailform as Mailform;
	use Nomadcode\Component\Mailform\MailformStrings as MailformStrings;

	class MailformTest extends PHPUnit_Framework_TestCase
	{
		protected function setUp()
		{
        	parent::setUp();
        	$_POST = array();
        	$_SERVER = array();
        	$_SERVER['SCRIPT_NAME'] = 'MailformTest.php';
		}

		public function test_constructor()
		{
			$mailform = new Mailform();
		}
		
		public function test_initialization()
		{
			$mailform = new Mailform();
			$this->assertEquals("web form: a subject",
								$mailform->get_prefixed_subject('a subject'));
			$this->assertEquals('82974363c4d607ffebb49da78ab1c56d',
								$mailform->generate_mail_checksum("Joe Bob",
																  "user@example.com",
																  "some subject",
																  "some message"));
			$this->assertEquals("webmaster",
								$mailform->get_message_destination());
			$_SERVER['SERVER_NAME'] = 'example.com';
			$this->assertEquals("webmaster@example.com",
								$mailform->get_message_destination());
								
			$settings = array('salt' => 'salt 1',
							  'recipient' => 'bob',
							  'prefix' => 'prefix 1');
			$mailform = new Mailform($settings);
			$this->assertEquals("prefix 1: a subject",
								$mailform->get_prefixed_subject('a subject'));
			$this->assertEquals('4df97c46b256ac4bb74a5699f89919d5',
								$mailform->generate_mail_checksum("Joe Bob",
																  "user@example.com",
																  "some subject",
																  "some message"));
			$this->assertEquals("bob@example.com",
								$mailform->get_message_destination());

		}
		
		public function test_has_checksum()
		{
			$fixture = new Mailform();
			$this->assertFalse($fixture->has_checksum());
			$_POST['mail_digest'] = 1234;
			$this->assertTrue($fixture->has_checksum());
		}
		
		public function test_has_data()
		{
			$fixture = new Mailform();
			$this->assertFalse($fixture->has_data());
			$_POST['mail_from'] = "John Smith";
			$this->assertTrue($fixture->has_data());
        	$_POST = array();
			$_POST['mail_email'] = "user@example.com";
			$this->assertTrue($fixture->has_data());
        	$_POST = array();
			$_POST['mail_subject'] = "Subject";
			$this->assertTrue($fixture->has_data());
        	$_POST = array();
			$_POST['mail_message'] = "A message to you, Rudi.";
			$this->assertTrue($fixture->has_data());
		}
		
		public function test_render_step2()
		{
			$fixture = new Mailform();
			$_POST['mail_email'] = "This is not a valid email";
			$this->assertEquals(
				$fixture->render_notification('failure',MailformStrings::MESSAGE_INVALID_EMAIL),
				$fixture->render_step2());
			$_POST['mail_email'] = "user@example.com";
			$this->assertEquals(
				$fixture->render_notification('failure',MailformStrings::MESSAGE_EMPTY_MESSAGE),
				$fixture->render_step2());
			$_POST['mail_message'] = "A token message";
			$this->assertEquals(1267,strlen($fixture->render_step2()));
		}
		
		public function test_generate_mail_checksum()
		{
			$fixture = new Mailform();
		}
		
	}
