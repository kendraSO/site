<?php

/**
 * Web application module for handling site analytics.
 *
 * Currently has support for Google Analytics, Facebook Pixels,
 * Bing Universal Event Tracking, Pardot, VWO and Hotjar.
 *
 * @package   Site
 * @copyright 2007-2018 silverorange
 * @license   http://www.gnu.org/copyleft/lesser.html LGPL License 2.1
 * @link      http://code.google.com/apis/analytics/docs/tracking/asyncTracking.html
 * @link      https://developers.facebook.com/docs/facebook-pixel/api-reference
 * @link      http://help.bingads.microsoft.com/apex/index/3/en-ca/n5012
 */
class SiteAnalyticsModule extends SiteApplicationModule
{
	// {{{ class constants

	/**
	 * Total number of available slots for custom variables.
	 */
	const CUSTOM_VARIABLE_SLOTS = 5;

	/**
	 * Available scopes for custom variables.
	 */
	const CUSTOM_VARIABLE_SCOPE_VISITOR = 1;
	const CUSTOM_VARIABLE_SCOPE_SESSION = 2;
	const CUSTOM_VARIABLE_SCOPE_PAGE    = 3;

	// }}}
	// {{{ protected properties

	/**
	 * Google Analytics Account
	 *
	 * @var string
	 */
	protected $google_account;

	/**
	 * Flag to tell whether analytics are enabled on this site.
	 *
	 * @var boolean
	 */
	protected $analytics_enabled = true;

	/**
	 * Flag to tell whether the user has opted out of analytics.
	 *
	 * @var boolean
	 */
	protected $analytics_opt_out = false;

	/**
	 * Flag to tell whether to load the enchanced link attribution plugin.
	 *
	 * @var boolean
	 * @link https://support.google.com/analytics/answer/2558867
	 */
	protected $enhanced_link_attribution = false;

	/**
	 * Flag to tell whether to use the display advertisor features.
	 *
	 * These are used for demographic and interest reports on GA, as well as
	 * remarketing and Google Display Network impression reporting.
	 *
	 * @var boolean
	 * @link https://support.google.com/analytics/answer/2444872
	 */
	protected $display_advertising = false;

	/**
	 * Stack of commands to send to google analytics
	 *
	 * Each entry is an array where the first value is the google analytics
	 * command, and any following values are optional command parameters.
	 *
	 * @var array
	 */
	protected $ga_commands = array();

	/**
	 * Facebook Pixel Account
	 *
	 * @var string
	 */
	protected $facebook_pixel_id;

	/**
	 * Stack of commands to send to facebook pixels
	 *
	 * Each entry is an array where the first value is the facebook pixel
	 * command, and any following values are optional command parameters.
	 *
	 * @var array
	 */
	protected $facebook_pixel_commands = array();

	/**
	 * Bing UET Account
	 *
	 * @var string
	 */
	protected $bing_uet_id;

	/**
	 * Stack of commands to send to bing UET
	 *
	 * Each entry is an array where the first value is the bing UET
	 * command, and any following values are optional command parameters.
	 *
	 * @var array
	 */
	protected $bing_uet_commands = array();

	/**
	 * Twitter Pixel User-Tracking Tag
	 *
	 * @var string
	 */
	protected $twitter_track_pixel_id;

	/**
	 * Twitter Pixel Purchase Tag
	 *
	 * @var string
	 */
	protected $twitter_purchase_pixel_id;

	/**
	 * Stack of commands to send to twitter pixels
	 *
	 * Commands are key-value pairs.
	 *
	 * @var array
	 */
	protected $twitter_pixel_commands = array();

	/**
	 * Salesforce Pardot Account ID
	 *
	 * @var string
	 */
	protected $pardot_account_id;

	/**
	 * Salesforce Pardot Campaign ID
	 *
	 * @var string
	 */
	protected $pardot_campaign_id;

	/**
	 * VWO Account ID
	 *
	 * @var string
	 */
	protected $vwo_account_id;

	/**
	 * Hotjar Account ID
	 *
	 * @var string
	 */
	protected $hotjar_account_id;

	// }}}
	// {{{ public function init()

	public function init()
	{
		$config = $this->app->getModule('SiteConfigModule');

		$this->google_account = $config->analytics->google_account;
		$this->enhanced_link_attribution =
			$config->analytics->google_enhanced_link_attribution;

		$this->display_advertising =
			$config->analytics->google_display_advertising;

		$this->facebook_pixel_id = $config->analytics->facebook_pixel_id;
		$this->bing_uet_id = $config->analytics->bing_uet_id;

		$this->twitter_track_pixel_id =
			$config->analytics->twitter_track_pixel_id;

		$this->twitter_purchase_pixel_id =
			$config->analytics->twitter_purchase_pixel_id;

		$this->pardot_account_id =
			$config->analytics->pardot_account_id;

		$this->pardot_campaign_id =
			$config->analytics->pardot_campaign_id;

		$this->friendbuy_account_id =
			$config->analytics->friendbuy_account_id;

		$this->vwo_account_id =
			$config->analytics->vwo_account_id;

		$this->hotjar_account_id =
			$config->analytics->hotjar_account_id;

		if (!$config->analytics->enabled) {
			$this->analytics_enabled = false;
		}

		$this->initOptOut();

		// skip init of the commands if we're opted out.
		if (!$this->analytics_opt_out) {
			$this->initGoogleAnalyticsCommands();
			$this->initFacebookPixelCommands();
			$this->initBingUETCommands();
		}
	}

	// }}}
	// {{{ public function hasAnalytics()

	public function hasAnalytics()
	{
		return (
			$this->hasGoogleAnalytics() ||
			$this->hasFacebookPixel() ||
			$this->hasTwitterPixel() ||
			$this->hasBingUET() ||
			$this->hasPardot() ||
			$this->hasVWO() ||
			$this->hasHotjar()
		);
	}

	// }}}
	// {{{ public function displayNoScriptContent()

	public function displayNoScriptContent()
	{
		$this->displayFacebookPixelImage();
		$this->displayTwitterPixelImages();
		$this->displayBingUETImage();
	}

	// }}}
	// {{{ public function displayScriptContent()

	public function displayScriptContent()
	{
		$js = '';

		if ($this->hasVWO()) {
			$js.= $this->getVWOInlineJavascript();
		}

		if ($this->hasHotjar()) {
			$js.= $this->getHotjarInlineJavascript();
		}

		if ($this->hasFacebookPixel()) {
			$js.= $this->getFacebookPixelInlineJavascript();
		}

		if ($this->hasBingUET()) {
			$js.= $this->getBingUETInlineJavascript();
		}

		if ($this->hasGoogleAnalytics()) {
			$js.= $this->getGoogleAnalyticsInlineJavascript();
		}

		if ($this->hasTwitterPixel()) {
			$js.= $this->getTwitterPixelInlineJavascript();
		}

		if ($this->hasPardot()) {
			$js.= $this->getPardotInlineJavascript();
		}

		if ($js != '') {
			Swat::displayInlineJavaScript($js);
		}
	}

	// }}}
	// {{{ protected function initOptOut()

	protected function initOptOut()
	{
		$cookie_module = null;

		if ($this->app->hasModule('SiteCookieModule')) {
			$cookie_module = $this->app->getModule('SiteCookieModule');

			if (isset($cookie_module->AnalyticsOptOut)) {
				$this->analytics_opt_out = true;
			}
		}

		if (isset($_GET['AnalyticsOptIn'])) {
			$this->analytics_opt_out = false;
			if (!$cookie_module instanceof SiteCookieModule) {
				$e = new SiteException(
					'Attempting to remove Analytics Opt '.
					'Out Cookie with no SiteCookieModule available.'
				);

				$e->processAndContinue();
			} else {
				$cookie_module->removeCookie('AnalyticsOptOut');
			}
		}

		// Opt Out trumps opt in if you include them both flags in your query
		// string for some reason.
		if (isset($_GET['AnalyticsOptOut'])) {
			$this->analytics_opt_out = true;
			if (!$cookie_module instanceof SiteCookieModule) {
				$e = new SiteException(
					'Attempting to set Analytics Opt Out '.
					'Cookie with no SiteCookieModule available.'
				);

				$e->processAndContinue();
			} else {
				// 10 years should be equivalent to never expiring.
				$cookie_module->setCookie(
					'AnalyticsOptOut',
					'1',
					strtotime('+10 years')
				);
			}
		}
	}

	// }}}

	// Google Analytics
	// {{{ public function hasGoogleAnalytics()

	public function hasGoogleAnalytics()
	{
		return (
			$this->google_account != '' &&
			!$this->analytics_opt_out &&
			$this->analytics_enabled
		);
	}

	// }}}
	// {{{ public function pushGoogleAnalyticsCommands()

	public function pushGoogleAnalyticsCommands(array $commands)
	{
		foreach ($commands as $command) {
			$this->ga_commands[] = $command;
		}
	}

	// }}}
	// {{{ public function prependGoogleAnalyticsCommands()

	public function prependGoogleAnalyticsCommands(array $commands)
	{
		$comands = array_reverse($commands);
		foreach ($commands as $command) {
			array_unshift($this->ga_commands, $command);
		}
	}

	// }}}
	// {{{ public function getGoogleAnalyticsInlineJavascript()

	public function getGoogleAnalyticsInlineJavascript()
	{
		$javascript = null;

		if ($this->hasGoogleAnalytics() && count($this->ga_commands) > 0) {
			$javascript = $this->getGoogleAnalyticsCommandsInlineJavascript();
			$javascript.= "\n";
			$javascript.= $this->getGoogleAnalyticsTrackerInlineJavascript();
		}

		return $javascript;
	}

	// }}}
	// {{{ public function getGoogleAnalyticsCommandsInlineJavascript()

	public function getGoogleAnalyticsCommandsInlineJavascript()
	{
		$javascript = null;

		if ($this->hasGoogleAnalytics() && count($this->ga_commands) > 0) {
			$commands = '';

			if ($this->enhanced_link_attribution) {
				// Enhanced link attribution plugin comes before _setAccount in
				// Google documentation, so put it first. Note: the plugin URI
				// doesn't load properly from https://ssl.google-analytics.com/.
				$plugin_uri = '//www.google-analytics.com/plugins/ga/'.
					'inpage_linkid.js';

				$commands.= $this->getGoogleAnalyticsCommand(
					array(
						'_require',
						'inpage_linkid',
						$plugin_uri,
					)
				);
			}

			// Always set the account before any further commands.
			$commands.= $this->getGoogleAnalyticsCommand(
				array(
					'_setAccount',
					$this->google_account,
				)
			);

			foreach ($this->ga_commands as $command) {
				$commands.= $this->getGoogleAnalyticsCommand($command);
			}

			$javascript = <<<'JS'
var _gaq = _gaq || [];
%s
JS;

			$javascript = sprintf(
				$javascript,
				$commands
			);
		}

		return $javascript;
	}

	// }}}
	// {{{ public function getGoogleAnalyticsTrackerInlineJavascript()

	public function getGoogleAnalyticsTrackerInlineJavascript()
	{
		$javascript = null;

		if ($this->hasGoogleAnalytics()) {
			$javascript = <<<'JS'
(function() {
	var ga = document.createElement('script');
	ga.type = 'text/javascript';
	ga.async = true;
	ga.src = '%s';
	var s = document.getElementsByTagName('script')[0];
	s.parentNode.insertBefore(ga, s);
})();
JS;

			$javascript = sprintf(
				$javascript,
				$this->getGoogleAnalyticsTrackingCodeSource()
			);
		}

		return $javascript;
	}

	// }}}
	// {{{ protected function initGoogleAnalyticsCommands()

	protected function initGoogleAnalyticsCommands()
	{
		// Default commands for all sites:
		// * Speed sampling 100% of the time.
		// * Track the page view.
		$this->ga_commands = array(
			array(
				'_setSiteSpeedSampleRate',
				100
			),
			'_trackPageview',
		);
	}

	// }}}
	// {{{ protected function getGoogleAnalyticsTrackingCodeSource()

	protected function getGoogleAnalyticsTrackingCodeSource()
	{
		if ($this->display_advertising) {
			$source = ($this->app->isSecure())
				? 'https://stats.g.doubleclick.net/dc.js'
				: 'http://stats.g.doubleclick.net/dc.js';
		} else {
			$source = ($this->app->isSecure())
				? 'https://ssl.google-analytics.com/ga.js'
				: 'http://www.google-analytics.com/ga.js';
		}

		return $source;
	}

	// }}}
	// {{{ protected function getGoogleAnalyticsCommand()

	protected function getGoogleAnalyticsCommand($command)
	{
		$method  = '';
		$options = '';

		if (is_array($command)) {
			$method = array_shift($command);

			foreach ($command as $part) {
				$quoted_part = (is_float($part) || is_int($part))
					? $part
					: SwatString::quoteJavaScriptString($part);

				$options.= ', '.$quoted_part;
			}
		} else {
			$method = $command;
		}

		return sprintf(
			'_gaq.push([%s%s]);',
			SwatString::quoteJavaScriptString($method),
			$options
		);
	}

	// }}}

	// Facebook
	// {{{ public function hasFacebookPixel()

	public function hasFacebookPixel()
	{
		return (
			$this->facebook_pixel_id != '' &&
			!$this->analytics_opt_out &&
			$this->analytics_enabled
		);
	}

	// }}}
	// {{{ public function pushFacebookPixelCommands()

	public function pushFacebookPixelCommands(array $commands)
	{
		foreach ($commands as $command) {
			$this->facebook_pixel_commands[] = $command;
		}
	}

	// }}}
	// {{{ public function prependFacebookPixelCommands()

	public function prependFacebookPixelCommands(array $commands)
	{
		$comands = array_reverse($commands);
		foreach ($commands as $command) {
			array_unshift($this->facebook_pixel_commands, $command);
		}
	}

	// }}}
	// {{{ public function getFacebookPixelImage()

	public function getFacebookPixelImage()
	{
		// @codingStandardsIgnoreStart
		$xhtml = <<<'XHTML'
<noscript><img height="1" width="1" style="display:none" src="https://www.facebook.com/tr?id=%s&ev=PageView&noscript=1"/></noscript>
XHTML;
		// @codingStandardsIgnoreEnd
		return sprintf(
			$xhtml,
			SwatString::minimizeEntities(rawurlencode($this->facebook_pixel_id))
		);
	}

	// }}}
	// {{{ public function getFacebookPixelInlineJavascript()

	public function getFacebookPixelInlineJavascript()
	{
		$javascript = null;

		if ($this->hasFacebookPixel() &&
			count($this->facebook_pixel_commands) > 0) {
			$javascript = $this->getFacebookPixelTrackerInlineJavascript();
			$javascript.= "\n";
			$javascript.= $this->getFacebookPixelCommandsInlineJavascript();
		}

		return $javascript;
	}

	// }}}
	// {{{ public function getFacebookPixelTrackerInlineJavascript()

	public function getFacebookPixelTrackerInlineJavascript()
	{
		$javascript = null;

		if ($this->hasFacebookPixel()) {
			$javascript = <<<'JS'
!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;
n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,
document,'script','//connect.facebook.net/en_US/fbevents.js');
JS;
		}

		return $javascript;
	}

	// }}}
	// {{{ public function getFacebookPixelCommandsInlineJavascript()

	public function getFacebookPixelCommandsInlineJavascript()
	{
		$javascript = null;

		if ($this->hasFacebookPixel() &&
			count($this->facebook_pixel_commands) > 0) {
			// Always init with the account and track the pageview before any
			// further commands.
			$javascript = $this->getFacebookPixelCommand(
				array(
					'init',
					$this->facebook_pixel_id,
				)
			);

			foreach ($this->facebook_pixel_commands as $command) {
				$javascript.= $this->getFacebookPixelCommand($command);
			}
		}

		return $javascript;
	}

	// }}}
	// {{{ protected function initFacebookPixelCommands()

	protected function initFacebookPixelCommands()
	{
		// Default commands for all sites:
		// * Track the page view.
		$this->facebook_pixel_commands = array(
			array(
				'track',
				'PageView',
			),
		);
	}

	// }}}
	// {{{ protected function displayFacebookPixelImage()

	protected function displayFacebookPixelImage()
	{
		if ($this->hasFacebookPixel()) {
			$image = $this->getFacebookPixelImage();
			if ($image != '') {
				echo $image;
			}
		}
	}

	// }}}
	// {{{ protected function getFacebookPixelCommand()

	protected function getFacebookPixelCommand($command)
	{
		if (!is_array($command)) {
			$command = array($command);
		}

		return sprintf(
			'fbq(%s);',
			implode(', ', array_map('json_encode', $command))
		);
	}

	// }}}

	// Twitter
	// {{{ public function hasTwitterPixel()

	public function hasTwitterPixel()
	{
		return (
			$this->twitter_track_pixel_id != '' &&
			!$this->analytics_opt_out &&
			$this->analytics_enabled
		);
	}

	// }}}
	// {{{ public function pushTwitterPixelCommands()

	public function pushTwitterPixelCommands(array $commands)
	{
		foreach ($commands as $name => $value) {
			$this->twitter_pixel_commands[$name] = $value;
		}
	}

	// }}}
	// {{{ public function getTwitterPixelImages()

	public function getTwitterPixelImages()
	{
		// @codingStandardsIgnoreStart
		$xhtml = <<<'XHTML'
<noscript>
<img height="1" width="1" style="display:none;" alt="" src="https://analytics.twitter.com/i/adsct?txn_id=%1$s&amp;p_id=Twitter" />
<img height="1" width="1" style="display:none;" alt="" src="//t.co/i/adsct?txn_id=%1$s&amp;p_id=Twitter" />
</noscript>
XHTML;
		// @codingStandardsIgnoreEnd
		if (count($this->twitter_pixel_commands) > 0) {
			//@codingStandardsIgnoreStart
			$xhtml.= <<<'XHTML'
<noscript>
<img height="1" width="1" style="display:none;" alt="" src="https://analytics.twitter.com/i/adsct?txn_id=%2$s&amp;p_id=Twitter&amp;%3$s" />
<img height="1" width="1" style="display:none;" alt="" src="//t.co/i/adsct?txn_id=%2$s&amp;p_id=Twitter&amp;%3$s" />
</noscript>
XHTML;
			// @codingStandardsIgnoreEnd
		}

		$track_pixel = rawurlencode($this->twitter_track_pixel_id);
		$purchase_pixel = rawurlencode($this->twitter_purchase_pixel_id);

		$query_vars = array();
		foreach ($this->twitter_pixel_commands as $name => $value) {
			$query_vars[$name] = sprintf(
				'%s=%s',
				SwatString::minimizeEntities(rawurlencode($name)),
				SwatString::minimizeEntities(rawurlencode($value))
			);
		}

		return sprintf(
			$xhtml,
			SwatString::minimizeEntities($track_pixel),
			SwatString::minimizeEntities($purchase_pixel),
			implode('&amp;', $query_vars)
		);
	}

	// }}}
	// {{{ public function getTwitterPixelInlineJavascript()

	public function getTwitterPixelInlineJavascript()
	{
		$javascript = '';

		if ($this->hasTwitterPixel()) {
			$javascript = $this->getTwitterPixelTrackerInlineJavascript();
		}

		return $javascript;
	}

	// }}}
	// {{{ public function getTwitterPixelTrackerInlineJavascript()

	public function getTwitterPixelTrackerInlineJavascript()
	{
		$twitter_functions = sprintf(
			"\ntwttr.conversion.trackPid(%s);\n",
			SwatString::quoteJavaScriptString($this->twitter_track_pixel_id)
		);

		if (count($this->twitter_pixel_commands) > 0) {
			$twitter_functions.= "\n";
			$twitter_functions.= sprintf(
				"twttr.conversion.trackPid(%s, %s);\n",
				SwatString::quoteJavaScriptString(
					$this->twitter_purchase_pixel_id
				),
				json_encode($this->twitter_pixel_commands)
			);
		}

		$javascript = <<<'JS'
(function() {
var twitter_script = document.createElement('script');
twitter_script.type = 'text/javascript';
twitter_script.src = '//platform.twitter.com/oct.js';

var onload = function() { %s };

if (typeof document.attachEvent === 'object') {
	// Support IE8
	twitter_script.onreadystatechange = function() {
		if (['loaded', 'complete'].contains(twitter_script.readyState)) {
			twitter_script.onreadystatechange = null;
			onload();
		}
	};
} else {
	twitter_script.onload = onload;
}

var s = document.getElementsByTagName('script')[0];
s.parentNode.insertBefore(twitter_script, s);
})();
JS;

		return sprintf(
			$javascript,
			$twitter_functions
		);
	}

	// }}}
	// {{{ protected function displayTwitterPixelImages()

	protected function displayTwitterPixelImages()
	{
		if ($this->hasTwitterPixel()) {
			$images = $this->getTwitterPixelImages();
			if ($images != '') {
				echo $images;
			}
		}
	}

	// }}}

	// Bing
	// {{{ public function hasBingUET()

	public function hasBingUET()
	{
		return (
			$this->bing_uet_id != '' &&
			!$this->analytics_opt_out &&
			$this->analytics_enabled
		);
	}

	// }}}
	// {{{ public function pushBingUETCommands()

	public function pushBingUETCommands(array $commands)
	{
		foreach ($commands as $command) {
			$this->bing_uet_commands[] = $command;
		}
	}

	// }}}
	// {{{ public function prependBingUETCommands()

	public function prependBingUETCommands(array $commands)
	{
		$comands = array_reverse($commands);
		foreach ($commands as $command) {
			array_unshift($this->bing_uet_commands, $command);
		}
	}

	// }}}
	// {{{ public function getBingUETImage()

	public function getBingUETImage()
	{
		// @codingStandardsIgnoreStart
		$xhtml = <<<'XHTML'
<noscript><img src="//bat.bing.com/action/0?ti=%s&Ver=2" height="0" width="0" style="display:none; visibility: hidden;" /></noscript>
XHTML;
		// @codingStandardsIgnoreEnd
		return sprintf(
			$xhtml,
			SwatString::minimizeEntities(rawurlencode($this->bing_uet_id))
		);
	}

	// }}}
	// {{{ public function getBingUETInlineJavascript()

	public function getBingUETInlineJavascript()
	{
		$javascript = null;

		// Bing UET doens't have an init command, and the initial tracker setup
		// happens as part of the code in
		// SiteAnalyticsModule::getBingUETTrackerInlineJavascript().
		// This is different that the other trackers in SiteAnalyticsModule.
		if ($this->hasBingUET()) {
			$javascript = $this->getBingUETTrackerInlineJavascript();
			if (count($this->bing_uet_commands) > 0) {
				$javascript.= "\n";
				$javascript.= $this->getBingUETCommandsInlineJavascript();
			}
		}

		return $javascript;
	}

	// }}}
	// {{{ public function getBingUETTrackerInlineJavascript()

	public function getBingUETTrackerInlineJavascript()
	{
		$javascript = null;

		if ($this->hasBingUET()) {
			// @codingStandardsIgnoreStart
			$javascript = <<<'JS'
(function(w,d,t,r,u){var f,n,i;w[u]=w[u]||[],f=function(){var o={ti:"%s"};o.q=w[u],w[u]=new UET(o),w[u].push("pageLoad")},n=d.createElement(t),n.src=r,n.async=1,n.onload=n.onreadystatechange=function(){var s=this.readyState;s&&s!=="loaded"&&s!=="complete"||(f(),n.onload=n.onreadystatechange=null)},i=d.getElementsByTagName(t)[0],i.parentNode.insertBefore(n,i)})(window,document,"script","//bat.bing.com/bat.js","uetq");
window.uetq = window.uetq || [];
JS;
			// @codingStandardsIgnoreEnd
		}

		return sprintf(
			$javascript,
			SwatString::quoteJavaScriptString($this->bing_uet_id)
		);
	}

	// }}}
	// {{{ public function getBingUETCommandsInlineJavascript()

	public function getBingUETCommandsInlineJavascript()
	{
		$javascript = null;

		if ($this->hasBingUET() &&
			count($this->bing_uet_commands) > 0) {
			foreach ($this->bing_uet_commands as $command) {
				$javascript.= $this->getBingUETCommand($command);
			}
		}

		return $javascript;
	}

	// }}}
	// {{{ protected function initBingUETCommands()

	protected function initBingUETCommands()
	{
		// No default commands to init, as the basic track page view happens
		// in the tracker setup javascript in
		// SiteAnalyticsModule::getBingUETTrackerInlineJavascript().
	}

	// }}}
	// {{{ protected function getBingUETCommand()

	protected function getBingUETCommand($command)
	{
		if (!is_array($command)) {
			$command = array($command);
		}

		return sprintf(
			'window.uetq.push(%s);',
			json_encode($command)
		);
	}

	// }}}
	// {{{ protected function displayBingUETImage()

	protected function displayBingUETImage()
	{
		if ($this->hasBingUET()) {
			$image = $this->getBingUETImage();
			if ($image != '') {
				echo $image;
			}
		}
	}

	// }}}

	// Salesforce Pardot
	// {{{ public function hasPardot()

	public function hasPardot()
	{
		return (
			$this->pardot_account_id != '' &&
			$this->pardot_campaign_id != '' &&
			!$this->analytics_opt_out &&
			$this->analytics_enabled
		);
	}

	// }}}
	// {{{ public function getPardotInlineJavascript()

	public function getPardotInlineJavascript()
	{
		$javascript = null;

		if ($this->hasPardot()) {
			$javascript.= $this->getPardotTrackerInlineJavascript();
		}

		return $javascript;
	}

	// }}}
	// {{{ public function getPardotTrackerInlineJavascript()

	public function getPardotTrackerInlineJavascript()
	{
		$javascript = null;

		if ($this->hasPardot()) {
			// @codingStandardsIgnoreStart
			$javascript = <<<'JS'
piAId = %s;
piCId = %s;

(function() {
	function async_load(){
		var s = document.createElement('script'); s.type = 'text/javascript';
		s.src = ('https:' == document.location.protocol ? 'https://pi' : 'http://cdn') + '.pardot.com/pd.js';
		var c = document.getElementsByTagName('script')[0]; c.parentNode.insertBefore(s, c);
	}
	if(window.attachEvent) { window.attachEvent('onload', async_load); }
	else { window.addEventListener('load', async_load, false); }
})();
JS;
			// @codingStandardsIgnoreEnd
		}

		return sprintf(
			$javascript,
			SwatString::quoteJavaScriptString($this->pardot_account_id),
			SwatString::quoteJavaScriptString($this->pardot_campaign_id)
		);
	}

	// }}}

	// VWO
	// {{{ public function hasVWO()

	public function hasVWO()
	{
		return (
			$this->vwo_account_id != '' &&
			!$this->analytics_opt_out &&
			$this->analytics_enabled
		);
	}

	// }}}
	// {{{ public function getVWOInlineJavascript()

	public function getVWOInlineJavascript()
	{
		$javascript = null;

		if ($this->hasVWO()) {
			$javascript.= $this->getVWOTrackerInlineJavascript();
		}

		return $javascript;
	}

	// }}}
	// {{{ public function getVWOTrackerInlineJavascript()

	public function getVWOTrackerInlineJavascript()
	{
		$javascript = null;

		if ($this->hasVWO()) {
			// @codingStandardsIgnoreStart
			$javascript = <<<'JS'
var _vwo_code=(function(){
var account_id=%s,
settings_tolerance=2000,
library_tolerance=2500,
use_existing_jquery=false,
/* DO NOT EDIT BELOW THIS LINE */
f=false,d=document;return{use_existing_jquery:function(){return use_existing_jquery;},library_tolerance:function(){return library_tolerance;},finish:function(){if(!f){f=true;var a=d.getElementById('_vis_opt_path_hides');if(a)a.parentNode.removeChild(a);}},finished:function(){return f;},load:function(a){var b=d.createElement('script');b.src=a;b.type='text/javascript';b.innerText;b.onerror=function(){_vwo_code.finish();};d.getElementsByTagName('head')[0].appendChild(b);},init:function(){settings_timer=setTimeout('_vwo_code.finish()',settings_tolerance);var a=d.createElement('style'),b='body{opacity:0 !important;filter:alpha(opacity=0) !important;background:none !important;}',h=d.getElementsByTagName('head')[0];a.setAttribute('id','_vis_opt_path_hides');a.setAttribute('type','text/css');if(a.styleSheet)a.styleSheet.cssText=b;else a.appendChild(d.createTextNode(b));h.appendChild(a);this.load('//dev.visualwebsiteoptimizer.com/j.php?a='+account_id+'&u='+encodeURIComponent(d.URL)+'&r='+Math.random());return settings_timer;}};}());_vwo_settings_timer=_vwo_code.init();
JS;
			// @codingStandardsIgnoreEnd
		}

		return sprintf(
			$javascript,
			SwatString::quoteJavaScriptString($this->vwo_account_id)
		);
	}

	// }}}

	// Hotjar
	// {{{ public function hasHotjar()

	public function hasHotjar()
	{
		return (
			$this->hotjar_account_id != '' &&
			!$this->analytics_opt_out &&
			$this->analytics_enabled
		);
	}

	// }}}
	// {{{ public function getHotjarInlineJavascript()

	public function getHotjarInlineJavascript()
	{
		$javascript = null;

		if ($this->hasHotjar()) {
			$javascript.= $this->getHotjarTrackerInlineJavascript();
		}

		return $javascript;
	}

	// }}}
	// {{{ public function getHotjarTrackerInlineJavascript()

	public function getHotjarTrackerInlineJavascript()
	{
		$javascript = null;

		if ($this->hasHotjar()) {
			// @codingStandardsIgnoreStart
			$javascript = <<<'JS'
(function(h,o,t,j,a,r){
    h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};
    h._hjSettings={hjid:%s,hjsv:6};
    a=o.getElementsByTagName('head')[0];
    r=o.createElement('script');r.async=1;
    r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;
    a.appendChild(r);
})(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');
JS;
			// @codingStandardsIgnoreEnd
		}

		return sprintf(
			$javascript,
			$this->hotjar_account_id
		);
	}

	// }}}
}

?>
