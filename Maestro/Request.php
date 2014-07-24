<?php
/**
 * Description of Request
 *
 * @author maestro.svl@gmail.com
 */
class Maestro_Request {

	/**
	 *
	 */
	public function __construct() {
		$this->normalizeRequest();
	}

	/**
	 * Normalizes the request data.
	 * This method strips off slashes in request data if get_magic_quotes_gpc() returns true.
	 */
	protected function normalizeRequest() {
		if (function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc()) {
			if (isset($_GET)) {
				$_GET = $this->stripSlashes($_GET);
			}
			if (isset($_POST)) {
				$_POST = $this->stripSlashes($_POST);
			}
			if (isset($_REQUEST)) {
				$_REQUEST = $this->stripSlashes($_REQUEST);
			}
			if (isset($_COOKIE)) {
				$_COOKIE = $this->stripSlashes($_COOKIE);
			}
		}
	}

	/**
	 * Strips slashes from input data.
	 * This method is applied when magic quotes is enabled.
	 * @param mixed input data to be processed
	 * @return mixed processed data
	 */
	public function stripSlashes(&$data) {
		return is_array($data) ? array_map(array($this, 'stripSlashes'), $data) : stripslashes($data);
	}

	/**
	 * Returns the named GET or POST parameter value.
	 * If the GET or POST parameter does not exist, the second parameter to this method will be returned.
	 * If both GET and POST contains such a named parameter, the GET parameter takes precedence.
	 * @param string the GET parameter name
	 * @param mixed the default parameter value if the GET parameter does not exist.
	 * @return mixed the GET parameter value
	 * @since 1.0.4
	 * @see getQuery
	 * @see getPost
	 */
	public function getParam($name, $defaultValue = null) {
		return isset($_GET[$name]) ? $_GET[$name] : (isset($_POST[$name]) ? $_POST[$name] : $defaultValue);
	}

	/**
	 * Returns the named GET parameter value.
	 * If the GET parameter does not exist, the second parameter to this method will be returned.
	 * @param string the GET parameter name
	 * @param mixed the default parameter value if the GET parameter does not exist.
	 * @return mixed the GET parameter value
	 * @since 1.0.4
	 * @see getPost
	 * @see getParam
	 */
	public function getQuery($name, $defaultValue = null) {
		return isset($_GET[$name]) ? $_GET[$name] : $defaultValue;
	}

	/**
	 * Returns the named POST parameter value.
	 * If the POST parameter does not exist, the second parameter to this method will be returned.
	 * @param string the POST parameter name
	 * @param mixed the default parameter value if the POST parameter does not exist.
	 * @return mixed the POST parameter value
	 * @since 1.0.4
	 * @see getParam
	 * @see getQuery
	 */
	public function getPost($name, $defaultValue = null) {
		return isset($_POST[$name]) ? $_POST[$name] : $defaultValue;
	}

	/**
	 * @param string the FILES parameter name
	 * @param mixed the default parameter value if the FILES parameter does not exist.
	 * @return mixed the FILES parameter value
	 */
	public function getFiles($name, $defaultValue = null) {
		return isset($_FILES[$name]) ? $_FILES[$name] : $defaultValue;
	}

	/**
	 * @param string the SERVER parameter name
	 * @param mixed the default parameter value if the SERVER parameter does not exist.
	 * @return mixed the SERVER parameter value
	 */
	public function getServer($name, $defaultValue = null) {
		return isset($_SERVER[$name]) ? $_SERVER[$name] : $defaultValue;
	}

	/**
	 * @param string the COOKIE parameter name
	 * @param mixed the default parameter value if the COOKIE parameter does not exist.
	 * @return mixed the COOKIE parameter value
	 */
	public function getCookie($name, $defaultValue = null) {
		return isset($_COOKIE[$name]) ? $_COOKIE[$name] : $defaultValue;
	}

	/**
	 * @return boolean if the request is sent via secure channel (https)
	 */
	public function getIsSecureConnection() {
		return isset($_SERVER['HTTPS']) && !strcasecmp($_SERVER['HTTPS'], 'on');
	}

	/**
	 * @return string request type, such as GET, POST, HEAD, PUT, DELETE.
	 */
	public function getRequestType() {
		return strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET');
	}

	/**
	 * @return boolean whether this is POST request.
	 */
	public function getIsPostRequest() {
		return isset($_SERVER['REQUEST_METHOD']) && !strcasecmp($_SERVER['REQUEST_METHOD'], 'POST');
	}

	/**
	 * @return boolean whether this is an AJAX (XMLHttpRequest) request.
	 */
	public function getIsAjaxRequest() {
		return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest';
	}

	/**
	 * @return string server name
	 */
	public function getServerName() {
		return $_SERVER['SERVER_NAME'];
	}

	/**
	 * @return integer server port number
	 */
	public function getServerPort() {
		return $_SERVER['SERVER_PORT'];
	}

	/**
	 * @return string
	 */
	public function getUrl() {
		return isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
	}

	/**
	 * @return string URL referrer, null if not present
	 */
	public function getUrlReferrer() {
		return isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : null;
	}

	/**
	 * @return string user agent
	 */
	public function getUserAgent() {
		return isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'unknown agent';
	}

	/**
	 * @return string user IP address
	 */
	public function getUserHostAddress() {
		return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
	}

	/**
	 * @return string browser language
	 */
	public function getBrowserLanguage() {
		return isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2) : 'en';
	}
}

?>
