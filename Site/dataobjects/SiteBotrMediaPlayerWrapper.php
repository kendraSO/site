<?php

/**
 * A recordset wrapper class for SiteBotrMediaPlayer objects
 *
 * @package   Site
 * @copyright 2011-2016 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @see       SiteBotrMediaPlayer
 */
class SiteBotrMediaPlayerWrapper extends SwatDBRecordsetWrapper
{
	// {{{ protected function init()

	protected function init()
	{
		parent::init();

		$this->row_wrapper_class = SwatDBClassMap::get('SiteBotrMediaPlayer');

		$this->index_field = 'id';
	}

	// }}}
}

?>
