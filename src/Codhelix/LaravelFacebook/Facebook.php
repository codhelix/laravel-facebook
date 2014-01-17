<?php namespace Codhelix\LaravelFacebook;

use Config;
use Session;
use \Purl\Url;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class Facebook extends \Facebook\Facebook {

	function __construct() {
		$config = array(
			'appId'  => Config::get('laravel-facebook::appId'),
			'secret' => Config::get('laravel-facebook::secret')
		);

		parent::__construct($config);

		parse_str($_SERVER['QUERY_STRING'], $fbQueryStrings);

		if (isset($fbQueryStrings['state'])) {
			$_REQUEST['state'] = $fbQueryStrings['state'];
		}

		if (isset($fbQueryStrings['code'])) {
			$_REQUEST['code'] = $fbQueryStrings['code'];
		}
	}

	public function getSignedRequest($useSession = true) {

		$signedRequest = parent::getSignedRequest();

		if( is_array($signedRequest) ) {
			Session::put('signed_request', $signedRequest);
			return $signedRequest;
		}
		else if( $useSession ) {
			return Session::get('signed_request');
		}
	}

	/**
	 * Checks to see if the user has "liked" the page by checking a signed request
	 * @return int -1 don't know, 0 doesn't like, 1 liked
	 */
	public function hasLiked() {
		$signedRequest = $this->getSignedRequest();

		if ( !is_array($signedRequest) || !array_key_exists('page', $signedRequest) ) {
			// We dont know
			return -1;
		}
		else {
			// Return the value Facebook told us
			return $signedRequest['page']['liked'];
		}
	}

	public function getMe() {
		$user = $this->getUser();

		if ( $user ) {
			try {
				$me = $this->api('/me');
				Config::set('laravel-facebook::locale', $me['locale']);
			} catch(\FacebookApiException $e) {
				$url  = $this->getLoginUrl( array('scope'=> $this->getScope()) );
				// Log::error($e);

				$user = NULL;
				$me   = null;

				echo "<script language=\"javascript\" type=\"text/javascript\"> top.location.href=\"{$url}\"; </script>";
				exit;
			} catch(Exception $e) {
				// Log::error($e);
			}
			return $me;
		}
		else {
			$options = array('scope'=> $this->getScope());

			$input = Request::createFromGlobals();
			if ( $input->is('tab*') ) {
				$options['redirect_uri'] = $this->getCanvasUrl();
			}

			$url  = $this->getLoginUrl( $options );
			echo "<script language=\"javascript\" type=\"text/javascript\"> top.location.href=\"{$url}\"; </script>";
			exit;
		}
	}

	public function getAppId() {
		return Config::get('laravel-facebook::appId');
	}

	public function getNamespace() {
		return Config::get('laravel-facebook::namespace');
	}

	public function getPageId() {
		return Config::get('laravel-facebook::pageId');
	}

	public function getLocale() {
		return Config::get('laravel-facebook::locale');
	}

	public function getScope() {
		return Config::get('laravel-facebook::scope');
	}

	public function getSecret() {
		return Config::get('laravel-facebook::secret');
	}

	public function getTabAppUrl( $redirect=false, $withRequestParams = false, $openInParent = true ){

		$pageId = $this->getPageId();

		if($pageId){
			$appId = $this->getAppId();

			$url = "http://www.facebook.com/pages/null/{$pageId}?sk=app_{$appId}";

			if ($withRequestParams) {
				$url = $this->_urlWithRequestParams($url);
			}

			if ($openInParent ) {
				$parent = ".top";
			}
			else {
				$parent = "";
			}

			if ($redirect) {
				$url = "<script type=\"text/javascript\">window{$parent}.location.href=\"{$url}\"</script>";
			}

			return $url;
		}

		return null;
	}

	public function getShareUrl($data = array()) {
		$appId = $this->getAppId();

		if( $appId ) {
			$shareUrl = new Url('https://www.facebook.com/dialog/feed');

			$defaults = array(
				'app_id' => $this->getAppId(),
				'redirect_uri' => url()
			);

			$shareParams = array_merge($defaults, $data);
		}
		else {
			// http://stackoverflow.com/questions/12547088/how-do-i-customize-facebooks-sharer-php
			// Map the new og key names to the old sharer format
			$sharerData = array();

			foreach ($data as $key => $value) {
				switch ($key) {
				case 'link':
					$sharerData['url'] = $value;
					break;

				case 'name':
					$sharerData['title'] = $value;
					break;

				case 'description':
					$sharerData['summary'] = $value;
					break;

				case 'picture':
					$sharerData['images'][] = $value;
					break;

				default:
					$sharerData[$key] = $value;
					break;
				}
			}

			$shareUrl = new Url('https://www.facebook.com/sharer/sharer.php');
			$shareParams = array('s' => 100, 'p' => $sharerData);
		}

		$shareUrl->query->setData($shareParams);
		return $shareUrl;
	}

	public function getCanvasUrl($path = '') {
		return ($this->getNamespace()) ? 'http://apps.facebook.com/' . $this->getNamespace() . '/' . $path : null;
	}


	/**
	 * Posts a notification to users
	 *
	 * This method pushes a message to
	 *
	 * @param string  $message the message that will be pushed to the user
	 * @param boolean $user_id the user id that we are pushing the notification to
	 *
	 * @return mixed
	 * @throws FBIgnitedException
	 */
	public function sendNotification($message, $user_id = null)
	{
		if ($user_id === null) {
			$user_id = $this->getUser();
		}

		$access_token = $this->getAppId().'|'.$this->getSecret();

		$data = array(
			'href'         => '?notification_id=2',
			'access_token' => $access_token,
			'template'     => $message,
		);

		try {
			$send_result = $this->api("/$user_id/notifications", 'post', $data);
		} catch (\FacebookApiException $e) {
			$send_result = false;
			Log::error($e);
		}

		return $send_result;
	}


	private function _urlWithRequestParams( $url ) {
		$exclude = array('fb_locale', 'signed_request');

		$separator = ( strstr($url, '?') ) ? '&' : '?';

		foreach( $_REQUEST as $key => $value ) {
			$url = $url.$separator.$key.'='.$value;
			$separator = '&';
		}

		return $url;
	}
}

