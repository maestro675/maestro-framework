<?php

/**
 * BaseController: абстрактный родительский класс для контроллеров
 *
 * @author Sergey Lysiansky, s.lysiansky@ucb-ua.com
 * @version    1.0
 */
abstract class Maestro_Controller_Base extends Maestro_Controller_Abstract {

	/**
	 * Имя файла кэша
	 *
	 * @var string
	 */
	protected $cache_filename;
	/**
	 * Настройки из yaml-файла конфигурации контроллера
	 *
	 * @var Maestro_Unit
	 */
	//protected $config;

	/**
	 * Конструктор класса
	 *
	 * @param Maestro_Router $front
	 * @param array $action_tags
	 */
	public function __construct($front, $action_tags) {
		parent::__construct($front, $action_tags);

		// считываем конфигурацию контроллера
		//$this->readConfig();

		// устанавливаем файл кэша
		$this->setCacheFilename();

		$this->initialize();
	}

	/**
	 * действие по умолчанию, требует переопределения в дочерних классах
	 */
	abstract function indexAction();

	/**
	 * Инициализация, для дочерних классов
	 */
	protected function initialize() {

	}

	/**
	 *
	 */
	/*protected function readConfig() {
		$filename = $this->getRouter()->getFilename();
		$filename = dirname($filename) . DIRECTORY_SEPARATOR . basename($filename, '.php') . '.yml';
		if (is_readable($filename)) {
			$this->config = new Zend_Config_Yaml($filename);
		}
	}*/

	/**
	 * Создание View и выполнение действия данного контроллера.
	 *
	 * @param string $action
	 */
	public function process($action) {
		//ob_start();
		// обработка тэга view: создание View
		$viewtype = $this->getRouter()->getActionTag('view');
		$this->view = Maestro_View_Factory::factory($this, $viewtype);
		try {
			// обработка тэга @before:
			// вызываем метод перед действием
			$execute_action = $this->invoke($this->getRouter()->getActionTag('before'));

			if($execute_action) {
				// время актуальности кэша берем из роутера
				$_cachetime = $this->getRouter()->getActionTag('cachetime');
				// если задано в тэгах - берем его
				//echo "<div class='bold marked-red'>cachetime: ".$_cachetime."</div>";
				//logger('info', 'CACHE: time='.$_cachetime);
				// если разрешено кеширование документа
				if ($_cachetime) {
					// если файл кэша существует
					if (file_exists($this->cache_filename)) {// && is_readable( $this->cachefname ) )
						$_file_modifield = filemtime($this->cache_filename);
						$_expired = ( time() - $_file_modifield );
						// если файл кэша актуален либо кешируется всегда (=-1), то
						// запрещаем выполнение действия и вытаскиваем документ из кэша,
						if ($_cachetime < 0 || $_expired < $_cachetime) {
							//echo "extract action from cache: <div class='bold marked-red'>".$this->cache_filename."</div>";
							logger('cache', 'CACHE: ' . $_cachetime . ' extract=' . $this->cache_filename);
							$execute_action = false;
							$this->getCache();
						}
					}
				}
			}

			// если выполнение действия разрешено - пользуемся этим
			if ($execute_action) {
				$this->$action();

				// если разрешено - создаем файл кэша из сформированного документа
				if ($_cachetime) {
					//echo "save cache: <div class='bold marked-red'>".$this->cache_filename."</div>";
					logger('cache', 'CACHE: ' . $_cachetime . ' save=' . $this->cache_filename);
					$this->setCache();
				}
			}

			// блок тэгов действия контроллера
			$node = $this->xml->addNode(Maestro_Dom::ROOT, 'actiontags');
			if (is_array($this->_actionTags)) {
				foreach ($this->_actionTags as $name => $value) {
					$this->xml->addNode($node, $name, $value);
				}
			}

			// обработка тэга @after:
			// вызываем метод после действия
			$this->invoke($this->getRouter()->getActionTag('after'));
		} catch (Normbase_Exception $e) {
			$e->setHeader('Normbase error exception.');
			Maestro_Common::showexcept($e);
			//logger('error', $e->getHeader().PHP_EOL.$e->getMessage().PHP_EOL.$e->getTraceAsString());
		} catch (StopException $e) {
			//$e->setHeader('');
			Maestro_Common::alert('', $e->getMessage(), 'error', true);
			//logger('error', $e->getHeader().PHP_EOL.$e->getMessage().PHP_EOL.$e->getTraceAsString());
		} catch (Maestro_Exception $e) {
			$e->setHeader('Controller error exception.');
			Maestro_Common::showexcept($e);
			logger('error', $e->getHeader().PHP_EOL.$e->getMessage().PHP_EOL.$e->getTraceAsString());
		} catch (Exception $e) {
			Maestro_Common::showexcept($e);
			logger('error', 'BC:ERROR'.PHP_EOL.$e->getMessage().PHP_EOL.$e->getTraceAsString());
		}
		$this->view->displayResult();
	}

	/**
	 * Формирует вывод
	 *
	 */
	protected function render() {

	}

	/**
	 * Устанавливает параметры в View
	 *
	 */
	public function setParam($key, $value, $force = false) {
		if ($this->view) {
			$this->view->setParam($key, $value, $force);
		}
	}

	/**
	 * Возвращает аргументы запроса
	 *
	 * @return array
	 */
	/*public function getFrontArguments() {
		return $this->getRouter()->arguments;
	}*/

	/**
	 *
	 * @param string $tag
	 * @return mixed
	 */
	public function getActionTag($tag) {
		if (isset($this->_actionTags[$tag])) {
			return $this->_actionTags[$tag];
		}
		return null;
	}

	/**
	 *
	 * @param string $tag
	 * @param mixed $value
	 */
	public function setActionTag($tag, $value) {
		$this->_actionTags[$tag] = $value;
	}

	/**
	 * Устанавливает полное имя файла кэша.
	 * Если не задан входящий параметр, строим имя файла из пути /project/controller/action
	 *
	 * @param string $fname задать имя файла вручную (без пути)
	 * @return string
	 */
	protected function setCacheFilename($fname=null) {
		if (is_null($fname)) {
			$fname = $this->getRouter()->route;
		}

		$this->cache_filename = $this->decodeFilename($fname);
	}

	/**
	 *
	 * @param string $fname
	 * @return string
	 */
	protected function decodeFilename($fname) {
		$fname = trim($fname, '/');
		$fname = explode('/', $fname);
		$dir = array_shift($fname);
		$fname = $dir . DIRECTORY_SEPARATOR . implode('.', $fname);

		$fname = realpath(Maestro_App::getConfig()->Path->cache) . DIRECTORY_SEPARATOR . $fname;
		return $fname;
	}

	/**
	 * Записывает xml-документ контроллера в кэш
	 *
	 */
	protected function setCache() {
		$dir = dirname($this->cache_filename);
		if (!file_exists($dir)) {
			mkdir($dir);
		}
		$this->xml->save($this->cache_filename);
	}

	/**
	 * Получает xml-документ контроллера из кэша
	 *
	 */
	protected function getCache() {
		$this->xml->load($this->cache_filename);
	}

	/**
	 * Удаляет файл кэша
	 * @param string $filename
	 */
	protected function deleteCache($filename=null) {
		if (!$filename) {
			$filename = $this->cache_filename;
		} else {
			$filename = $this->decodeFilename($filename);
		}

		if (file_exists($filename)) {
			unlink($filename);
		}
	}
	
	/**
	 *
	 * @param string $route 
	 */
	protected function redirect($route) {
		ob_end_clean();
		Maestro_App::getRouter()->run($route);
		exit;
	}

}

?>
