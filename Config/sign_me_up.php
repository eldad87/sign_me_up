<?php
$config['SignMeUp'] = array(
	'from' => 'Universito.com <donotreplay@universito.com>',
	'layout' => 'default',
	'welcome_subject' => __d('SignMeUp','Welcome to Universito.com %username%!'),
	'sendAs' => 'text',
	'activation_template' => 'activate',
	'welcome_template' => 'welcome',
	'password_reset_template' => 'forgotten_password',
	'password_reset_subject' => __d('SignMeUp','Password reset from Universito.com'),
	'new_password_template' => 'new_password',
	'new_password_subject' => __d('SignMeUp','Your new password from Universito.com'),
	'xMailer' => 'Universito.com Email-bot',
);