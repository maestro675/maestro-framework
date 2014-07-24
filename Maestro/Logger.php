<?php

/**
 * Description of Logger
 *
 * @author maestro
 */
class Maestro_Logger {

	/**
	 * self instance
	 *
	 * @var Maestro_Logger
	 */
	private static $_instance;

	/**
	 *
	 * @var array
	 */
	protected static $_levels = array(
		'error' => 1,
		'debug' => 2,
		'info' => 3,
		'cache' => 4
	);

	/**
	 * Установленный в системе уровень протоколирования
	 *
	 * @var integer
	 */
	protected $_threshold = 1;
	protected $_date_format = 'Y-m-d H:i:s';

	/**
	 * Полное имя файла протокола
	 *
	 * @var string
	 */
	protected $_file_path;

	/**
	 * Флаг разрешения на запись в протокол
	 *
	 * @var bool
	 */
	protected $_enabled = true;

	/**
	 *
	 * @var string
	 */
	public static $last_message = '';

	/**
	 * Конструктор
	 *
	 */
	private function __construct() {
		$this->_file_path = Maestro_App::getConfig()->Path->logs;
		if (!is_dir($this->_file_path) or !is_writable($this->_file_path)) {
			$this->_enabled = false;
		}
		$this->_file_path = $this->_file_path . DIRECTORY_SEPARATOR . date('Y.m.d') . '.log';

		$_level = Maestro_App::getConfig()->System->log_threshold;
		if (is_numeric($_level)) {
			$this->_threshold = $_level;
		}

		$_format = Maestro_App::getConfig()->System->log_date_format;
		if (!empty($_format)) {
			$this->_date_format = $_format;
		}
	}

	/**
	 *
	 * @return string
	 */
	public static function file_path() {
		$instance = self::getInstance();
		return $instance->_file_path;
	}

	/**
	 *
	 * @param string $level
	 * @param string $message
	 * @return bool
	 */
	public static function write_log($level, $message) {
		$instance = self::getInstance();

		self::$last_message = $message;

		if (!$instance->_enabled) {
			return false;
		}

		$level = strtolower($level);
		if (!isset(self::$_levels[$level]) or (self::$_levels[$level] > $instance->_threshold)) {
			return false;
		}

		$f = fopen($instance->_file_path, 'a');
		if (!$f) {
			return false;
		}

		$_ip = Maestro_App::getRequest()->getServer('REMOTE_ADDR', '<unknown>');
		$_msg = sprintf("%5s - %-15s - [ %s ] - %s\n", $level, $_ip, date($instance->_date_format), $message
		);

		flock($f, LOCK_EX);
		fwrite($f, $_msg);
		flock($f, LOCK_UN);
		fclose($f);

		/**
		 * 
		 */
		$str = sprintf('%s<hr/>route: %s<br/>env: %s<br/>agent: %s<br/>ip: %s<br/>user: (%d) %s<br/>level: %s<br>created: %s', 
				$message, 
				Maestro_App::getRequest()->getUrl(),
				APPLICATION_ENV,
				Maestro_App::getRequest()->getUserAgent(),
				$_ip, 
				Session::subject(), 
				Session::get('userNameFull'), 
				$level, 
				date('d.m.Y H:i:s')
		);
		
		if ('production' == APPLICATION_ENV && 'error' == $level) {
			Common::sendmail(Maestro_App::getConfig()->Admin->email, 'site error', $str);
		} else if('development' == APPLICATION_ENV && 'info' !== $level) {
			echo '<pre><span class="muted">DEVELOPMENT BACKTRACE:</span><br/>';
			echo $str;
			echo '<hr/>' . Common::backtrace(0, true);
			echo '</pre>';
		}

		return true;
	}

	/**
	 *
	 */
	private function __clone() {
		
	}

	/**
	 * Return the single instance of object
	 *
	 * @return Maestro_Logger
	 */
	public static function &getInstance() {
		if (!isset(self::$_instance)) {
			self::$_instance = new self;
		}
		return self::$_instance;
	}

}

?>
