<?php
class SignMeUpComponent extends Component {
	private $controller;
	public $components = array( 'RequestHandler', 'Session', 'Email', 
		'Auth' => array(
			/*'loginAction' => array(
				'controller' => 'Home',
				'action' => 'login',
			),*/
			'authenticate' => array(
				'Form' => array(
					'fields' => array('username' => 'email')
				)
			)
		)
	);

	public $defaults = array(
		'activation_field' => false,
		'useractive_field' => 'active',
		'welcome_subject' => 'Welcome',
		'activation_subject' => 'Please Activate Your Account',
		'password_reset_field' => 'password_reset',
		'username_field' => 'username',
		'email_field' => 'email',
		'password_field' => 'password',
		'activation_template' => 'activate',
		'welcome_template' => 'welcome',
		'password_reset_template' => 'forgotten_password',
		'password_reset_subject' => 'Password Reset Request',
		'new_password_template' => 'recovered_password',
		'new_password_subject' => 'Your new Password',
	);
	public $helpers = array('Form', 'Html');
	public $name = 'SignMeUp';
	public $uses = array('SignMeUp');

	public function __construct(ComponentCollection $collection, $settings = array()) {
		$settings = array_merge($this->defaults, $settings);
		parent::__construct($collection, $settings);
	}

	public function initialize(Controller $controller) {
		$this->__loadConfig();
		$this->settings = array_merge(Configure::read('SignMeUp'), $this->defaults);
		$this->controller = &$controller;
	}

	private function __loadConfig() {
		if (Configure::load('SignMeUp.sign_me_up') === false) {
			die(__d('SignMeUp','Could not load sign me up config'));
		}

        $this->Email->welcome_subject           = Configure::read('SignMeUp.welcome_subject');
        $this->Email->activation_subject        = Configure::read('SignMeUp.activation_subject');
        $this->Email->password_reset_subject    = Configure::read('SignMeUp.password_reset_subject');
        $this->Email->new_password_subject      = Configure::read('SignMeUp.new_password_subject');



        //Gmail patch, comment those settings to use the default
        config('email');
        $ec = new EmailConfig();
        $this->Email->delivery = 'smtp';
        $this->Email->smtpOptions = $ec->gmail;
	}

	private function __setUpEmailParams($user) {
		$this->__loadConfig();

		if (Configure::read('SignMeUp')) {
			foreach ($this->settings as $key => $setting) {
				$this->Email->{$key} = $setting;
			}
		}

		extract($this->settings);
		$this->Email->to = $user[$username_field].' <'.$user[$email_field].'>';
		$this->controller->set(compact('user'));
	}

	/*private function __parseEmailSubject($action = '', $user = array()) {
		$subject = $this->Email->{$action.'_subject'};
		preg_match_all('/%(\w+?)%/', $subject, $matches);
		foreach ($matches[1] as $match) {
			if (!empty($user[$match])) {
				$this->Email->subject = str_replace('%'.$match.'%', $user[$match], $subject);
			}
		}
	}*/

	public function register() {
		$this->__isLoggedIn();
		if (!empty($this->controller->data)) {
			extract($this->settings);
			$model = $this->controller->modelClass;
			$this->controller->loadModel($model);
			$this->controller->{$model}->set($this->controller->data);
			if ($this->controller->{$model}->validates()) {
				$saveData = $this->controller->data;

				if (!empty($activation_field)) {
					//$this->controller->data[$model][$activation_field] = $this->controller->{$model}->generateActivationCode($this->controller->data);
					$saveData[$model][$activation_field] = $this->controller->{$model}->generateActivationCode($this->controller->data);
				} elseif (!empty($useractive_field)) {
					//$this->controller->data[$model][$useractive_field] = true;
					$saveData[$model][$useractive_field] = true;
				}
				if ($this->controller->{$model}->save($saveData, false)) {
                    //Load data from DB
                    $this->controller->{$model}->recursive = -1;
                    $userData = $this->controller->{$model}->find('first', array('conditions'=>array($this->controller->{$model}->primaryKey=>$this->controller->{$model}->id)));

					//If an activation field is supplied send out an email
					if (!empty($activation_field)) {
						$this->__sendActivationEmail($userData[$model]);
						if (!$this->RequestHandler->isAjax()) {
							$this->controller->redirect(array('action' => 'activate'));
						} else {
							return true;
						}
					} else {
						$this->__sendWelcomeEmail($userData[$model]);
					}
					if (!$this->RequestHandler->isAjax()) {
						$this->controller->redirect($this->Auth->loginAction);
					} else {
						return true;
					}
				}
			}   return false;
		}
	}

	private function __isLoggedIn() {
		if ($this->Auth->user()) {
			if (!$this->RequestHandler->isAjax()) {
				$this->controller->redirect($this->Auth->loginRedirect ? $this->Auth->loginRedirect : '/');
			}
		}
	}

	private function __setTemplate($template) {
        $this->Email->template = $template;
	}

	protected function __sendActivationEmail($userData) {
		$this->__setUpEmailParams($userData);
		//$this->__parseEmailSubject('activation', $userData);
        $this->Email->subject = $this->Email->activation_subject;
		if ($this->__setTemplate(Configure::read('SignMeUp.activation_template'))) {
			if ($this->Email->send()) {
				return true;
			}
		}
	}

	protected function __sendWelcomeEmail($userData) {
		$this->__setUpEmailParams($userData);
		//$this->__parseEmailSubject('welcome', $userData);
        $this->Email->subject = $this->Email->welcome_subject;
		if ($this->__setTemplate(Configure::read('SignMeUp.welcome_template'))) {
			if ($this->Email->send()) {
				return true;
			}
		}
	}

	public function activate() {
		$this->__isLoggedIn();
		extract($this->settings);
		//If there is no activation field specified, don't bother with activation
		if (!empty($activation_field)) {

			//Test for an activation code in the parameters
			if (!empty($this->controller->params[$activation_field])) {
				$activation_code = $this->controller->params[$activation_field];
			}

			//If there is an activation code supplied, either in _POST or _GET
			if (!empty($activation_code) || !empty($this->controller->data)) {
				$model = $this->controller->modelClass;
				$this->controller->loadModel($model);

				if (!empty($this->controller->data)) {
					$activation_code = $this->controller->data[$model][$activation_field];
				}

				$inactive_user = $this->controller->{$model}->find('first', array('conditions' => array($activation_field => $activation_code), 'recursive' => -1));
				
				
				if (!empty($inactive_user)) {
					$this->controller->{$model}->id = $inactive_user[$model][(isSet($this->controller->{$model}->primaryKey) ? $this->controller->{$model}->primaryKey : 'id')];
					if (!empty($useractive_field)) {
						$data[$model][$useractive_field] = true;
					}
					$data[$model][$activation_field] = null;
					if ($this->controller->{$model}->save($data)) {
						$this->__sendWelcomeEmail($inactive_user['User']);
						if (!$this->RequestHandler->isAjax()) {
							$this->Session->setFlash('Thank you '.$inactive_user[$model][$username_field].', your account is now active');
							$this->controller->redirect($this->Auth->loginAction);
						} else {
							return true;
						}
					}
				} else {
					$this->Session->setFlash('Sorry, that code is incorrect.');
				}
			}
		}
	}

	public function forgottenPassword() {
		extract($this->settings);
		$model = $this->controller->modelClass;
		if (!empty($this->controller->data[$model])) {
			$data = $this->controller->data[$model];
		}

		//User has code to reset their password
		if (!empty($this->controller->params[$password_reset_field])) {
			$this->__generateNewPassword($model);
		} elseif (!empty($password_reset_field) && !empty($data['email'])) {
			$this->__requestNewPassword($data, $model);
		}
	}

	private function __generateNewPassword($model = '') {
		extract($this->settings);
		$user = $this->controller->{$model}->find('first', array(
			'conditions' => array($password_reset_field => $this->controller->params[$password_reset_field]),
			'recursive' => -1
		));

		if (!empty($user)) {
			$password = substr(Security::hash(String::uuid(), null, true), 0, 8);
			$user[$model][$password_field] = Security::hash($password, null, true);
			$user[$model][$password_reset_field] = null;
			$this->controller->set(compact('password'));
			if ($this->controller->{$model}->save($user) && $this->__sendNewPassword($user[$model])) {
				if (!$this->RequestHandler->isAjax()) {
					$this->Session->setFlash(sprintf(__d('SignMeUp','Thank you %s, your new password has been emailed to you.'),$user[$model][$username_field])); //TOGO: log in the user+show him the reset password page
					$this->controller->redirect($this->Auth->loginAction);
				} else {
					return true;
				}
			}
		}
	}

	private function __sendNewPassword($user = array()) {
		$this->__setUpEmailParams($user);
		if ($this->__setTemplate(Configure::read('SignMeUp.new_password_template'))) {
			$this->Email->subject = $this->Email->new_password_subject;
			if ($this->Email->send()) {
				return true;
			}
		}
	}

	private function __requestNewPassword($data = array(), $model = '') {
		extract($this->settings);
		$this->controller->loadModel($model);
		$user = $this->controller->{$model}->find('first', array('conditions' => array('email' => $data['email']), 'recursive' => -1));
		if (!empty($user)) {
			$saveData[$model][$password_reset_field] = md5(String::uuid());
			$saveData[$model][$this->controller->{$model}->primaryKey] = $user[$model][$this->controller->{$model}->primaryKey];

			if ($this->controller->{$model}->save($saveData) && $this->__sendForgottenPassword($user[$model])) {
				if (!$this->RequestHandler->isAjax()) {
					$this->Session->setFlash( sprintf(__d('SignMeUp','Thank you. A password recovery email has now been sent to %s'), $data['email']) );
					$this->controller->redirect($this->Auth->loginAction);
				} else {
					return true;
				}
			}
		} else {
			$this->controller->{$model}->invalidate('email', sprintf(__d('SignMeUp','No user found with email: %s', $data['email'])));
		}
	}

	private function __sendForgottenPassword($user = array()) {
		$this->__setUpEmailParams($user);
		if ($this->__setTemplate(Configure::read('SignMeUp.password_reset_template'))) {
			$this->Email->subject = $this->Email->password_reset_subject;
			if ($this->Email->send()) {
				return true;
			}
		}
	}

   /* private function _generateUserName($data) {
        extract($this->settings);
        $prefix = '';
        if(isSet($data[$email_field]) && $data[$email_field]) {
            $tmp = explode('@', $data[$email_field]);
            $prefix = $tmp[0];
        } else if(isSet($data[$display_name_field]) && $data[$display_name_field]) {
            $prefix = str_ireplace(array(' ', "\n", "\t"), array('', '', ''), trim($display_name_field));
        } else {
            return null;
        }



        //Check that username is unique
        $model = $this->controller->modelClass;
        $newUsername = $prefix;
        $foundUsername = true;
        while($foundUsername) {
            $foundUsername = $this->controller->{$model}->find('first', array('conditions'=>array($username_field=>$newUsername)));
            if($foundUsername) {
                $newUsername = $prefix.'_'.rand(1, 99);
            }
        }

        return $newUsername;
    }*/

}