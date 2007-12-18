<?php

require_once 'SwatDB/SwatDB.php';
require_once 'Swat/SwatDetailsStore.php';
require_once 'Site/dataobjects/SiteAccount.php';
require_once 'Admin/exceptions/AdminNotFoundException.php';
require_once 'Admin/pages/AdminIndex.php';
require_once 'SwatDB/SwatDBClassMap.php';

/**
 * Details page for accounts
 *
 * @package   Site
 * @copyright 2006-2007 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 */
class SiteAccountDetails extends AdminIndex
{
	// {{{ protected properties

	/**
	 * @var string
	 */
	protected $ui_xml = 'Site/admin/components/Account/details.xml';

	/**
	 * @var integer
	 */
	protected $id;

	/**
	 * @var SiteAccount
	 */
	protected $account;

	// }}}

	// init phase
	// {{{ protected function initInternal()

	protected function initInternal()
	{
		parent::initInternal();

		$this->ui->mapClassPrefixToPath('Site', 'Site');
		$this->ui->loadFromXML($this->ui_xml);

		$this->id = SiteApplication::initVar('id');
	}

	// }}}
	// {{{ protected function getAccount()

	protected function getAccount()
	{
		if ($this->account === null) {
			$account_class = SwatDBClassMap::get('SiteAccount');

			$this->account = new $account_class();
			$this->account->setDatabase($this->app->db);

			if (!$this->account->load($this->id))
				throw new AdminNotFoundException(sprintf(
					Site::_('A account with an id of ‘%d’ does not exist.'),
					$this->id));
		}

		return $this->account;
	}

	// }}}

	// build phase
	// {{{ protected function buildInternal()

	public function buildInternal()
	{
		parent::buildInternal();

		$this->buildAccountDetails();

		$toolbar = $this->ui->getWidget('details_toolbar');
		$toolbar->setToolLinkValues($this->id);

		$this->buildNavBar();
	}

	// }}}
	// {{{ protected function getAccountDetailsStore()

	protected function getAccountDetailsStore()
	{
		return new SwatDetailsStore($this->getAccount());
	}

	// }}}
	// {{{ protected function buildAccountDetails()

	protected function buildAccountDetails()
	{
		$ds = $this->getAccountDetailsStore();
		$ds->fullname = $this->account->getFullname();

		$details_frame = $this->ui->getWidget('details_frame');
		$details_frame->title = Site::_('Account');
		$details_frame->subtitle = $ds->fullname;

		$details_view = $this->ui->getWidget('details_view');

		$date_field = $details_view->getField('createdate');
		$date_renderer = $date_field->getRendererByPosition();
		$date_renderer->display_time_zone = $this->app->default_time_zone;

		$details_view->data = $ds;
	}

	// }}}
	// {{{ protected function getTableModel()

	protected function getTableModel(SwatView $view)
	{
		return null;
	}

	// }}}
	// {{{ private function buildNavBar()

	private function buildNavBar()
	{
		$fullname = $this->getAccount()->getFullname();
		$this->navbar->addEntry(new SwatNavBarEntry($fullname));
		$this->title = $fullname;
	}

	// }}}
}

?>
