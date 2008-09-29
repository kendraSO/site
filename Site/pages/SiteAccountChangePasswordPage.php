<?php

require_once 'Site/pages/SiteEditPage.php';

/**
 * Page for changing the password of an account
 *
 * @package   Site
 * @copyright 2006-2008 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       SiteAccount
 */
class SiteAccountChangePasswordPage extends SiteEditPage
{
	// {{{ protected function getUiXml()

	protected function getUiXml()
	{
		return 'Site/pages/account-change-password.xml';
	}

	// }}}
	// {{{ protected function isNew()

	protected function isNew(SwatForm $form)
	{
		return false;
	}

	// }}}

	// init phase
	// {{{ public function init()

	public function init()
	{
		if (!$this->app->session->isLoggedIn()) {
			$this->app->relocate('account/login');
		}

		parent::init();

		$this->ui->init();
	}

	// }}}
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();
		$confirm = $this->ui->getWidget('confirm_password');
		$confirm->password_widget = $this->ui->getWidget('password');
	}

	// }}}

	// process phase
	// {{{ public function process()

	public function process()
	{
		parent::process();

/*		$form = $this->ui->getWidget('edit_form');
		$form->process();

		if ($form->isProcessed()) {
			if (!$form->hasMessage())
				$this->validate();

			if (!$form->hasMessage()) {
				$password = $this->ui->getWidget('password')->value;
				$this->app->session->account->setPassword($password);
				$this->app->session->account->save();

				$message = new SwatMessage(Site::_(
					'Account password has been updated.'));

				$this->app->messages->add($message);

				$this->app->relocate('account');
			}
		}*/
	}

	// }}}
	// {{{ protected function save()

	protected function save(SwatForm $form)
	{
		$password = $this->ui->getWidget('password')->value;
		$this->app->session->account->setPassword($password);
		$this->app->session->account->save();

		$message = new SwatMessage(Site::_(
			'Account password has been updated.'));

		$this->app->messages->add($message);
	}

	// }}}
	// {{{ protected function validate()

	protected function validate(SwatForm $form)
	{
		$account = $this->app->session->account;

		$old_password = $this->ui->getWidget('old_password');

		$salt = $account->password_salt;
		// salt might be base-64 encoded
		$decoded_salt = base64_decode($salt, true);
		if ($decoded_salt !== false)
			$salt = $decoded_salt;

		$value = md5($old_password->value.$salt);

		if ($value != $account->password) {
			$message = new SwatMessage(Site::_('Your password is incorrect.'),
				SwatMessage::ERROR);

			$message->content_type = 'text/xml';
			$old_password->addMessage($message);
		}
	}

	// }}}
	// {{{ protected function relocate()

	protected function relocate(SwatForm $form)
	{
		$this->app->relocate('account');
	}

	// }}}

	// build phase
	// {{{ protected function buildTitle()

	protected function buildTitle()
	{
		parent::buildTitle();
		$this->layout->data->title = Site::_('Choose a New Password');
	}

	// }}}
	// {{{ protected function buildNavBar()

	protected function buildNavBar()
	{
		parent::buildNavBar();
		$this->layout->navbar->createEntry(Site::_('New Password'));
	}

	// }}}
	// {{{ protected function load()

	protected function load(SwatForm $form)
	{
	}

	// }}}
}

?>
