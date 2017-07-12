# Simple Mailform

This is a minimalistic implementation of a mailform utility, designed to be used to provide a contact form for simple websites.

Installation
------------

> composer require nomadcode/simple-mailform

Usage
-----

To use the mailform, add a `Nomadcode\Component\Mailform\Mailform` object to your page, then call the object's `process()` method.

The object is initialized with three arguments. The first is a settings object, that contains configuration information for the script. The second is a greeting message to be displayed with the form. The third is the number of text lines to show in the message area of the form. All three arguments are optional, although it is very strongly recommended that you pass a settings object to configure the form. 

The settings object will contain the following entries:

| name                 | example           | description                               |
|----------------------|-------------------|-------------------------------------------|
| salt                 | HelloWorld99!     | String used as salt when hashing messages |
| recipient            | user@example.com  | Email address of the message recipient    |
| prefix               | My Website        | Prefix added to Subject line of messages  |
| log                  | logs/mailform.log | Path to logfile                           |
| checksum_failure_log | logs/failure.log  | Path to file for logging failed attempts  |

The `salt` setting should be a (very) complex string. If you're not sure what to put, you could use a value from WordPress's handy [salt generator](https://api.wordpress.org/secret-key/1.1/salt/). 

The message will be sent to `recipient`, and the Subject line of the message will be prefixed with `prefix`. Log activity will be written to the logfile at `log` (make sure that the log exists, and that it is writeable by your webserver). If you want to keep track of attempts to send spam through your mailform, you can also specify a path for `checksum_failure_log`.

Example
-------

```
// settings.php

$settings = [
	'mailform' => [
		'salt' => 'someverycomplicatedstring',
		'recipient' => 'user@example.com',
		'prefix' => 'My Website',
		'log' => $_SERVER['DOCUMENT_ROOT'] . "/../logs/mailform.log",
		'checksum_failure_log' => $_SERVER['DOCUMENT_ROOT'] . '/../logs/rejection.log'
	]
];
```

```php
// mywebpage.html

	include('settings.php');
	require __DIR__ . '/../phplib/autoload.php';
	$greeting = <<<EndOfText
Please fill in the form below to send me email.
EndOfText;
	$form = new Nomadcode\Component\Mailform\Mailform($settings["mailform"],
													  $greeting,
													  5);
	$form->process();
```

Notes
-----

The component implements a multi-step process for sending mail. Each of the steps uses the same webpage, but -- depending on the state of the interaction -- the component generates different content.

In the first step, the component creates a simple HTML5 form that allows the visitor to enter their name, email address, subject and a message. In the second step, the message is displayed back to them for confirmation. In the third step, the message is sent and an acknowledgement message is displayed to indicate success or failure.

The component implements a basic anti-spam technique. Messages submitted by the user are hashed using a secret salt defined in the settings file. During the third step, the hash is passed along with the message. If the hash passed does not match the content of the message, the submission will be rejected.

This provides only a basic defense. An adversary who knows the technique can modify their spambot to handle multi-step submissions, bypassing the security by imitating an actual user. However, must spambots are probably designed to cope with single-step submissions, where clicking 'Submit' on the mailform causes an immediate send of the message. It's likely that the majority of spambots will fail to recognize that an additional action -- clicking 'Submit' on the second page -- is required in order for the message to be submitted.

This approach may be somewhat less intrusive than a CAPTCHA. It's also not a bad thing to give the user a chance to review their message before sending, as it may reduce the number of ill-formed or ill-considered messages submitted.

Tests
-----

> ./vendor/bin/phpunit


Known issues
------------

None

