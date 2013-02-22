<?php
$config['SignMeUp'] = array(
	'from' => 'Universito.com <donotreplay@universito.com>',
	'layout' => 'default',

    'welcome_subject' => 'Welcome to MyDomain.com %username%!',
    'activation_subject' => 'Activate Your MyDomain.com Account %username%!',
    'password_reset_subject' => 'Password reset from MyDomain.com',
    'new_password_subject' => 'Your new password from MyDomain.com',

	'welcome_subject' => __d('SignMeUp','Welcome to Universito.com %username%!'),
	'sendAs' => 'html',
	'activation_template' => 'activate',
	'welcome_template' => 'welcome',
	'password_reset_template' => 'forgotten_password',
	'password_reset_subject' => __d('SignMeUp','Password reset from Universito.com'),
	'new_password_template' => 'new_password',
	'new_password_subject' => __d('SignMeUp','Your new password from Universito.com'),
	'xMailer' => 'Universito.com Email-bot',
);