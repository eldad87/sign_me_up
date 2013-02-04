<?php
class SignMeUpBehavior extends ModelBehavior {

	public $validate = array(
		'email' => array(
			'validEmail' => array(
				'rule'      => array('email', true),
				'message'   => 'Please supply a valid & active email address'
			),
			'emailExists' => array(
				'rule'      => 'isUnique',
				'message'   => 'Sorry, this email address is already in use',
                'required'	=> true,
                'on'        => 'create',
			),
		),
		'password' => array(
            'match' => array(
                'rule'      => array('confirmPassword', 'password', 'password2'),
                'message'   => 'Passwords do not match'
            ),
            'confirmPasswordNotAsEmail' => array(
                'rule'      => array('confirmPasswordNotAsEmail', 'password', 'email'),
                'message'   => 'Passwords Cannot be identical to your email'
            ),
			'minRequirements' => array(
				'rule'      => array('minLength', 6),
				'message'   => 'Passwords need to be at least 6 characters long'
			),
            'strength' => array(
                'rule'      => '/^(?=.*\d)(?=.*[a-z]).{6,}$/i',
                'message'   => 'Passwords must have at least one alpha and one numeric, min 6 characters'
            )
		)
	);

	public function beforeValidate(Model $Model) {
		$this->model = $Model;
		$this->model->validate = am($this->validate, $this->model->validate);
	}

	public function confirmPasswordNotAsEmail($field, $password1, $password2) {
		if ($this->model->data[$this->model->alias]['password'] == $this->model->data[$this->model->alias]['email']) {
			return false;
		}
        return true;
	}
    public function confirmPassword($field, $password1, $password2) {
		if ($this->model->data[$this->model->alias]['password2'] == $this->model->data[$this->model->alias]['password']) {
			return true;
		}
        return false;
	}
	
	public function beforeSave( Model $Model ) {
		if(!isSet($Model->data['User']['password'])) {
			return true;
		}
		App::uses('AuthComponent', 'Controller/Component');
		$Model->data['User']['password'] = AuthComponent::password($Model->data['User']['password']);
		return true;
	}

	public function generateActivationCode($data) {
		return Security::hash(serialize($data).microtime().rand(1,100), null, true);
	}


    public function getFailedLoginCount(Model $Model, $email, $minInterval=5) {
        $query = '
                    SELECT
                        IF( DATE_ADD(last_failed_login, INTERVAL '.$minInterval.' MINUTE)>NOW(), failed_login_count, 0 ) AS last_failed_login
                    FROM `users` AS `'.$Model->alias.'`
                    WHERE `email` = \''.Sanitize::escape($email).'\'
                    LIMIT 1
                 ';

        $Model->recursive = -1;
        $found = $Model->query($query);
        if(!$found) {
           return false;
       }

        return $found[0][0]['last_failed_login'];
    }
    public function updateFailedLoginCounter(Model $Model, $email, $minInterval=5) {
        $update = '
                     UPDATE users
                     SET
                        failed_login_count =
                            IF(
                                DATE_ADD(last_failed_login, INTERVAL '.$minInterval.' MINUTE)>NOW(),
                                failed_login_count+1,
                                1
                            ),
                        last_failed_login=NOW()
                    WHERE
                        email = \''.Sanitize::escape($email).'\'
                ';


        //Update DB
        return $Model->query($update);
    }
}
?>