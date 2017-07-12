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
			$this->assertNotNull($mailform);
		}
		
		public function test_initialization()
		{
			$mailform = new Mailform();
			$this->assertEquals("web form: a subject",
								$mailform->get_prefixed_subject('a subject'));
			$this->assertEquals('a73c72da0bbb7919e8041bbf68bcadbbabe491909497b796ecc83604cf7eee59',
								$mailform->generate_mail_hash("Joe Bob",
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
			$this->assertEquals('af3ff210e0af49e1b623ea6acd4fd4bc8bc153a638eaba0c50706cf7ca715c1e',
								$mailform->generate_mail_hash("Joe Bob",
															  "user@example.com",
															  "some subject",
															  "some message"));
			$this->assertEquals("bob@example.com",
								$mailform->get_message_destination());

		}
		
		public function test_has_digest()
		{
			$fixture = new Mailform();
			$this->assertFalse($fixture->has_digest());
			$_POST['mail_digest'] = 1299;
			$this->assertTrue($fixture->has_digest());
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
			$this->assertEquals(1299,strlen($fixture->render_step2()));
		}
		
		public function test_generate_mail_hash()
		{
			$fixture = new Mailform();
			$this->assertEquals('fc1f1ad3e8a523e987b1270b3cdf747a7137a287304bd6ce0dce207554970b1b',
								$fixture->generate_mail_hash("Joe Bob",
												  			 "user@example.com",
												  			 "Hello world",
												  			 "This is a message."));
			$fixture = new Mailform(['hash_algorithm' => 'md5',
									 'salt' => 'sample salt',
									 'recipient' => 'user@example.com',
									 'prefix' => 'some prefix' ]);
			$this->assertEquals('f3f15ffd0c0eb266eda6076d9e7b3d1f',
								$fixture->generate_mail_hash("Joe Bob",
												  			 "user@example.com",
												  			 "Hello world",
												  			 "This is a message."));
		}
		
		public function test_defaults()
		{
			$fixture = new Mailform([]);
			$this->assertEquals('6be832f95a8364b9780df015100556de73f663338d96f82a704877d99f1851e9',
								$fixture->generate_mail_hash("a","b","c","d"));
			$fixture = new Mailform(['hash_algorithm' => 'md5']);
			$this->assertEquals('9a297fd16e744e512b02e490fd9cca86',
								$fixture->generate_mail_hash("a","b","c","d"));
			$fixture = new Mailform(['salt' => 'different salt now']);
			$this->assertEquals('f2ce19fc931fbad618d81139534b4043a7c6991204d1c5a3806b16758044b3c1',
								$fixture->generate_mail_hash("a","b","c","d"));
			$fixture = new Mailform(['prefix' => 'my cool prefix']);
			$this->assertEquals('6be832f95a8364b9780df015100556de73f663338d96f82a704877d99f1851e9',
								$fixture->generate_mail_hash("a","b","c","d"));
			$this->assertEquals('my cool prefix: some subj',
								$fixture->get_prefixed_subject('some subj'));
			$fixture = new Mailform(['recipient' => 'user@example.com']);
			$this->assertEquals('user@example.com',
								$fixture->get_message_destination());
			$fixture = new Mailform();
			$this->assertEquals('webmaster',
								$fixture->get_message_destination());
			$_SERVER['SERVER_NAME'] = 'example.net';
			$this->assertEquals('webmaster@example.net',
								$fixture->get_message_destination());
			$fixture = new Mailform(['recipient' => 'webhamster']);
			$this->assertEquals('webhamster@example.net',
								$fixture->get_message_destination());
		}
		
	}
