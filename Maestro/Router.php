<?php

/**
 * Description of Router
 *
 * @author maestro
 */
class Maestro_Router {

	/**
	 *
	 * @var string
	 */
	public $route;

	/**
	 * Проект
	 * 
	 * @var string 
	 */
	public $project;

	/**
	 * Контроллер
	 *
	 * @var string
	 */
	public $controller;

	/**
	 * Действие
	 *
	 * @var string
	 */
	public $action;

	/**
	 * Массив аргументов запроса
	 *
	 * @var array
	 */
	public $arguments;

	/**
	 * Имя класса контроллера
	 *
	 * @var string
	 */
	protected $controllerName;

	/**
	 * Имя метода действия класса контроллера
	 *
	 * @var string
	 */
	protected $actionName;

	/**
	 * Полный путь файла, содержащего контроллер
	 * 
	 * @var string
	 */
	protected $filename;

	/**
	 * Массив тэгов, заданных вручную, передаются в конструктор
	 * Имеют более высокий приоритет, чем теги, заданные в комментариях
	 * к действиям контроллеров.
	 *
	 * @var array
	 */
	protected $_tags;
	
	/**
	 *
	 * @var string
	 */
	protected $_origin_route;

	/**
	 *  Конструктор
	 *
	 * @param array $_atags
	 */
	public function __construct($_atags = null) {
		if (is_null($_atags)) {
			$_atags = array();
		}
		if (!is_array($_atags)) {
			$_atags = array($_atags);
		}
		// получаем тэги по умолчанию и замещаем их входящими значениями, переданными в конструктор
		$this->_tags = array_merge(Maestro_App::getConfig()->ActionTags->toArray(), $_atags);
		//
		Zend_Registry::set('Route', 'request', '/' . $this->getRoute());
	}
	
	/**
	 * если внутри модуля существует файл тэгов - внесем значения из него поверх существующих
	 * 
	 */
	protected function readModuleTags() {
		$file_path = Maestro_App::getConfig()->Path->modules . $this->project.DIRECTORY_SEPARATOR.'actiontags.yml';
		if(file_exists($file_path)) {
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
			$id = 'module_atags_' . $this->project;
			if(!($config = $cache->load($id))) {
				$config = new Zend_Config_Yaml($file_path);
				$cache->save($config, $id);
			}
			$this->_tags = array_merge($this->_tags, $config->toArray());
		}
	}
	
	/**
	 * 
	 */
	protected function getModuleRoutes() {
		$defaults = array(
			'_description'   => 'Unknown module',
			'_default_allow' => NULL,
		);
		$file_path = Maestro_App::getConfig()->Path->modules . $this->project.DIRECTORY_SEPARATOR.'routes.yml';
		if(file_exists($file_path)) {
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
			$id = 'module_routes_' . $this->project;
			if(!($config = $cache->load($id))) {
				$config = new Zend_Config_Yaml(
						$file_path,
						null,
						array('yaml_decoder' => array('Maestro_Yaml', 'parse'))
				);
				$cache->save($config, $id);
			}
			return array_merge($defaults, $config->toArray());
		} else {
			return $defaults;
		}
	}
	

	/**
	 * Стартовый метод обработки
	 *
	 * @param string $route
	 */
	public function run($route = '') {
		// разбираем строку запроса
		$this->parse($route);
		// выполняем подготовительные действия
		$this->prepare();
		// создание и запуск контроллера
		$this->start();
	}

	/**
	 * Возвращает строку запроса
	 *
	 * @return string
	 */
	public function getRoute() {
		return $this->route ? $this->route : Maestro_App::getRequest()->getQuery('route', 'index');
	}

	/**
	 * Разбирает строку запроса по типу: /project/controller/action/args
	 *
	 * @param string $route
	 */
	public function parse($route = '') {
		//echo '<pre><code>';
		// получаем строку адреса для разбора
		if (empty($route)) {
			$route = $this->getRoute();
		}
		$this->_origin_route = $route;
		// удаляем начальные и конечные слэши и разбиваем на элементы
		$route = trim($route, "/\\");
		$parts = explode("/", $route);
		// получаем абсолютный путь к модулям проектов 
		$start_path = Maestro_App::getConfig()->Path->modules;
		// инициализация параметров
		$this->project = '';
		$this->controller = '';
		$this->action = '';
		$this->clearArguments();
		
		//определяем модуль (проект)
		$part = array_shift($parts);
		$full_path = $start_path . $part;
		//echo PHP_EOL;
		//echo 'FULL: ' . $full_path . PHP_EOL;
		if(is_dir($full_path)) {
			$this->project = $part;
		} else {
			array_unshift($parts, $part);
			$this->project = 'index';
		}
		//echo 'PROJECT: <b>'.$this->project.'</b>'.PHP_EOL;
		
		// определяем контроллер
		$part = array_shift($parts);
		$full_path = $start_path . $this->project . DIRECTORY_SEPARATOR;
		//echo PHP_EOL;
		//echo 'FULL: ' . $full_path . PHP_EOL;
		$file_path = $full_path . 'controllers' . DIRECTORY_SEPARATOR . ucfirst($part) . 'Controller.php';
		if (is_file($file_path)) {
			//echo 'FILE: ' . $file_path . PHP_EOL;
			$this->controller = $part;
		} else {
			array_unshift($parts, $part);
			$this->controller = 'index';
		}
		//echo 'CONTROLLER: <b>'.$this->controller.'</b>'.PHP_EOL;
		
		// определяем действие
		$part = array_shift($parts);
		if(empty($part)) {
			$this->action = 'index';
		} elseif(is_numeric($part)) {
			$this->action = 'index';
			array_unshift($parts, $part);
		} else {
			$this->action = $part;
		}
		//echo PHP_EOL;
		//echo 'ACTION: <b>'.$this->action.'</b>'.PHP_EOL;
		
		// из названий модуля и контроллера вычисляем полный путь к файлу с классом контроллера
		$this->filename = $start_path . $this->project . DIRECTORY_SEPARATOR .
				'controllers' . DIRECTORY_SEPARATOR . ucfirst($this->controller) . 'Controller.php';
		
		// включаем в глобальный include_path папку с классами моделей, относящихся к 
		// текущему модулю (project), причем папка с моделями имеет больший приоритет, чем общие 
		// классы приложения и классы библиотек
		$path = $start_path . $this->project . DIRECTORY_SEPARATOR . 'models' . DIRECTORY_SEPARATOR;
		set_include_path($path . PATH_SEPARATOR . get_include_path());
		
		$this->readModuleTags();				
		
		//echo PHP_EOL;
		//echo 'FILENAME: ' . $this->filename.PHP_EOL;
		if(is_file($this->filename)) {
			//echo '<b>EXISTS</b>'.PHP_EOL;
		} else {
			throw new Maestro_Exception('Controller is not exists on route: ' . $this->_origin_route);
		}
		$this->setArguments($parts);
		$this->route = sprintf('/%s/%s/%s', $this->project, $this->controller, $this->action);
		Zend_Registry::set('Route', 'request', $this->route);
	}

	/**
	 * Подготовительные действия перед запуском контроллера.
	 * Для наследуемых классов - можно выполнять проверки
	 * валидации пользователей, права доступа к контроллеру/действию и т.п.
	 * Может содержать дополнительные вызовы $this->parse('....')
	 */
	protected function prepare() {
		
	}

	/**
	 * Создаем экземпляр класса контроллера и вызваем его метод processData.
	 * Параметры действия считываются из docblocks атрибутов метода-действия
	 */
	private function start() {
		// получаем реальные имена класса контроллера и метода действия
		$this->controllerName = '';
		if('index' != $this->project) {
			$this->controllerName = ucfirst($this->project).'_';
		}
		$this->controllerName .= ucfirst($this->controller) . 'Controller';
		$this->actionName = $this->action . 'Action';

		//try
		//{
		// подключаем файл с классом контроллера
		include_once $this->filename;
		// если класс контроллера существует
		if (class_exists($this->controllerName)) {
			// структура класса, рефлексия
			$rclass = new ReflectionClass($this->controllerName);
			// если метод (действие) определен
			if ($rclass->hasMethod($this->actionName)) {
				$rmethod = $rclass->getMethod($this->actionName);
				// считываем тэги из комментариев к действию и перекрываем ими текущие значения тэгов
				$atags = Maestro_Common::getDocTags($rmethod->getDocComment(), $this->_tags);
				foreach ($atags as $key => $value) {
					$this->_tags[$key] = $value;
				}
				// создаем экземпляр класса контроллера и передаем в него список тэгов
				$controller = new $this->controllerName($this, $this->_tags);
				// выполняем действие
				$controller->process($this->actionName);
			} else {
				//throw new Exception("Action not found in " . $this->_origin_route. '[' . $this->actionName. ']');
				$this->run('/noaction/Action not found');
			}
		} else {
			//throw new Exception("Controller not found: /" . $this->project . "/" . $this->controllerName . "/" . $this->actionName);
			$this->run('/noaction/Controller not found');
		}
		/* }
		  catch( Exception $e )
		  {
		  //$content = ob_get_contents();
		  //ob_end_clean();
		  //echo $content;
		  //Maestro_Common::backtrace();
		  //pre($e);
		  Common::completed( Common::MSG_ERROR, 'ROUTER: ERROR', $e->getMessage() );
		  //pre( $e->getMessage() );
		  } */
	}
	
	/**
	 * Возвращает код модуля
	 * 
	 * @return string
	 */
	public function getModule() {
		return $this->project;
	}
	
	/**
	 * Возвращает код контроллера
	 * 
	 * @return string
	 */
	public function getController() {
		return $this->controller;
	}
	
	/**
	 * Возвращает код действия
	 * 
	 * @return string
	 */
	public function getAction() {
		return $this->action;
	}

	/**
	 * Возвращает значение тега
	 *
	 * @param string $tag    Имя тэга
	 * @return mixed
	 */
	public function getActionTag($tag) {
		return isset($this->_tags[$tag]) ? $this->_tags[$tag] : false;
	}

	/**
	 * Устанавливает значение тега.
	 *
	 * @param string $tag    Имя тэга
	 * @param mixed $value   Значение
	 */
	public function setActionTag($tag, $value) {
		$this->_tags[$tag] = $value;
	}

	/**
	 *
	 * @param string $name
	 * @param string|integer $defaultValue
	 * @return string
	 */
	public function getArgument($name, $defaultValue = null) {
		return isset($this->arguments[$name]) ? $this->arguments[$name] : $defaultValue;
	}

	/**
	 *
	 * @return array
	 */
	public function getArguments() {
		return $this->arguments;
	}
	
	/**
	 * 
	 */
	protected function clearArguments() {
		$this->arguments = array();
	}

	/**
	 *
	 * @param array $items
	 */
	protected function setArguments($items) {
		if (!is_array($items)) {
			$items = array($items);
		}

		foreach ($items as $item) {
			$t = explode(':', $item);
			$c = count($t);
			if (1 == $c) {
				$this->arguments[] = $t[0];
			} else
			if ($c >= 2) {
				$key = $t[0];
				$this->arguments[$key] = $t[1];
			}
		}
	}

	/**
	 *
	 * @return string
	 */
	public function getFilename() {
		return $this->filename;
	}

}

?>
