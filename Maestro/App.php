<?php

/**
 * Description of App
 *
 * @author maestro
 */
class Maestro_App {

	/**
	 * self instance
	 *
	 * @var Maestro_Config
	 */
	private static $instance;

	/**
	 *
	 * @var Maestro_Router
	 */
	private $router;
	
	/**
	 *
	 * @var Maestro_Request
	 */
	private $request;
	
	/**
	 *
	 * @var Zend_Cache
	 */
	private $cache;
	
	/**
	 *
	 * @var Zend_Config
	 */
	private $config;
	
	/**
	 * массив объектов-сервисов
	 * 
	 * @var array
	 */
	private $_services = array();
	
	/**
	 *
	 * @var sfEventDispatcher
	 */
	//private $dispatcher;
	
	/**
	 *
	 * @var integer
	 */
	public static $startTime;
	
	/**
	 * 
	 * 
	 * @param string $name
	 * @return mixed
	 * @throws Maestro_Exception
	 */
	public static function get($name) {
		$instance = self::getInstance();
		if(!array_key_exists($name, $instance->_services)) {
			$className = self::getConfig()->Services->$name;
			if($className) {
				$instance->_services[$name] = new $className();
			} else {
				throw new Maestro_Exception(sprintf('Service "%s" is not registered.', $name));
			}
		}
		return $instance->_services[$name];
	}

	/**
	 * Точка входа приложения
	 *
	 *
	 */
	public static function run() {
		$instance = self::getInstance();
		$instance->request = new Maestro_Request();
		//$instance->dispatcher = new sfEventDispatcher();
		
		$instance->getCache();
		
		ob_start();

		// инициализируем хуки, если разрешено
		/*if (self::$config->System->enable_hooks)) {
			Maestro_Hooks::init();
			//Maestro_Hooks::dump();
		}*/

		// создаем и запускаем роутер
		try {
			$duration = number_format(microtime(true) - self::$startTime, 4);
			logger('info', sprintf('%10s - U:%-4s - APP:%s', 
					$duration, 
					Session::subject(), 
					self::getRequest()->getServer('REQUEST_URI')
			));			
			
			/*$routerClass = Maestro_App::getConfig()->System->routerClass;
			$instance->router = new $routerClass();
			$instance->router->run();*/
			self::get('Router')->run();
		} catch (Maestro_Exception $e) {
			logger('error', 'URI: ' . self::getRequest()->getServer('REQUEST_URI') . PHP_EOL . $e->getMessage());
			$e->setHeader('Application error exception');
			Maestro_Common::showexcept($e);
		} catch (Exception $e) {
			logger('error', 'URI: ' . self::getRequest()->getServer('REQUEST_URI') . PHP_EOL . $e->getMessage());
			Maestro_Common::showexcept($e);			
		}
	}
	
	/**
	 * объект глобального кэширования
	 */
	public static function getCache() {
		$instance = self::getInstance();
		if(!$instance->cache) {
			$instance->cache = Zend_Cache::factory('Core', 'File',
					array(
						'caching' => true,
						'automatic_serialization' => true
					),
					array(
						'hashed_directory_level' => 1,
						'hashed_directory_umask' => 0777,
						'cache_dir' => APPLICATION_PATH.'/../cache/zend/'
					)
			);
		}
		return $instance->cache;
	}

	/**
	 *
	 * @return Maestro_Request object
	 */
	public static function getRequest() {
		return self::getInstance()->request;
	}

	/**
	 *
	 * @return Maestro_Router
	 */
	public static function getRouter() {
		//return self::getInstance()->router;
		return self::get('Router');
	}
	
	/**
	 * @return sfEventDispatcher 
	 */
	public static function getDispatcher() {
		$instance = self::getInstance();
		/*if(!$instance->dispatcher) {
			$instance->dispatcher = new sfEventDispatcher();
		}*/
		return $instance->dispatcher;
	}
	
	/**
	 *
	 * @return Zend_Config
	 */
	public static function getConfig() {
		$instance = self::getInstance();
		if(!$instance->config) {
			// считываем конфигурацию
			$file_path = APPLICATION_PATH.DIRECTORY_SEPARATOR.'application.yml';
			$cache = Zend_Cache::factory('File', 'File',
					array(
						'caching' => true,					
						'automatic_serialization' => true,
						'master_file' => $file_path
					),
					array(
						'hashed_directory_level' => 1,
						'hashed_directory_umask' => 0777,
						'cache_dir' => APPLICATION_PATH . '/../cache/zend/'
					)
			);
			$id = 'appconfig';		
			if(!($instance->config = $cache->load($id))) {
				$instance->config = new Zend_Config_Yaml($file_path, APPLICATION_ENV);
				$cache->save($instance->config, $id);
			}
		}
		return $instance->config;
	}

	/**
	 *
	 */
	private function __construct() {
		self::$startTime = microtime(true);
	}

	/**
	 *
	 */
	private function __clone() {
		
	}

	/**
	 * Return the single instance of object
	 *
	 * @return Maestro_App
	 */
	public static function &getInstance() {
		if (!isset(self::$instance)) {
			self::$instance = new self;
		}
		return self::$instance;
	}

}

?>
