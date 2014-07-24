<?php
/**
 * Класс по управлению списокм объектов страниц (отчетов).
 * Отвечает за создание экземпляров страниц и обмен данными между ними.
 *
 * @author maestro
 */
class Maestro_Calc_Book {

	/**
	 * Массив конфигурации книги
	 *
	 * @var array
	 */
	protected $_config;

	/**
	 * Массив объектов страниц книги
	 *
	 * @var array
	 */
	protected $_sheets;

	/**
	 *
	 * @var Maestro_Unit
	 */
	protected $_params;

	/**
	 *
	 * @var string
	 */
	protected $_name;

	/**
	 * Конструктор класса
	 *
	 * @param $filename Имя файла конфигурации
	 * $param array $params
	 */
	public function __construct($filename, $params) {
		// устанавливаем параметры
		if(!($params instanceof Maestro_Unit)) {
			$this->_params = new Maestro_Unit(NULL, $params);
		}

		// считываем конфигурацию книги
		if(!file_exists($filename) || !is_readable($filename)) {
			throw new Maestro_Exception('Cannot read  configuration file: '.$filename);
		}
		$this->_config = new Zend_Config_Yaml($filename);
	}

	/**
	 *
	 * @param string $name
	 * @return Maestro_Calc_Book
	 */
	public function setName($name) {
		$this->_name = $name;
		return $this;
	}

	/**
	 *
	 * @return string
	 */
	public function getName() {
		return $this->_name;
	}

	/**
	 * Создает список страниц. Класс элемента страницы определяется из конфигурации,
	 * в конструктор передается ключ страницы и указатель на текущий экземпляр класса книги
	 *
	 */
	public function createSheets() {
		$this->_sheets = array();
		$build_list = $this->getConfig('build');

		foreach($build_list as $key) {
			// получим имя класса для модели отчета-страницы
			$class = $this->getConfig('sheets', $key, 'model');
			// свойства отчета
			$properties = $this->getConfig('sheets', $key);
			$properties = array_merge($properties, $this->_params->toArray());
			// создаем модель отчета
			$this->_sheets[$key] = new $class($key, $this, $properties);
			$this->getSheet($key)->debug = $this->_params->debug;

			// расчитываем данные или восстанавливаем из хранилища
			if($this->_params->restore_only) {
				$this->getSheet($key)->restore();
			} else {
				$this->getSheet($key)->create()->backup();
			}
		}
	}

	/**
	 * Возвращает страницу по ключу
	 *
	 * @param string $key
	 * @return Maestro_Calc_Sheet
	 */
	public function getSheet($key) {
		return isset($this->_sheets[$key]) ? $this->_sheets[$key] : null;
	}

	/**
	 *
	 * @return Maestro_Unit
	 */
	public function getParams() {
		return $this->_params;
	}

	/**
	 * Возвращает значение из конфигурации. Произвольное количество параметров (от 0 до 4)
	 * определяют ключи, по которым выбирается значение.
	 *
	 * В случае отсутствия параметров возвращается массик конфигурации целиком.
	 *
	 * @return mixed
	 */
	public function getConfig(/*...*/) {
		$an = func_num_args();
		$ag = func_get_args();

		if(!$an) {
			return $this->_config;
		} elseif(1 == $an) {
			$key1 = $ag[0];
			return isset($this->_config[$key1]) ? $this->_config[$key1] : null;
		} elseif(2 == $an) {
			$key1 = $ag[0];
			$key2 = $ag[1];
			return isset($this->_config[$key1][$key2]) ? $this->_config[$key1][$key2] : null;
		} elseif(3 == $an) {
			$key1 = $ag[0];
			$key2 = $ag[1];
			$key3 = $ag[2];
			return isset($this->_config[$key1][$key2][$key3]) ? $this->_config[$key1][$key2][$key3] : null;
		} elseif(4 == $an) {
			$key1 = $ag[0];
			$key2 = $ag[1];
			$key3 = $ag[2];
			$key4 = $ag[3];
			return isset($this->_config[$key1][$key2][$key3][$key4]) ? $this->_config[$key1][$key2][$key3][$key4] : null;
		}

	}
}

?>
