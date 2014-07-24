<?php

/**
 * Description of Sheet
 *
 * @author maestro
 */
//abstract 
class Maestro_Calc_Sheet implements Maestro_XmlObject_Interface {

	/**
	 *
	 * @var Maestro_Calc_Book
	 */
	private $_book;

	/**
	 * Уникальный ключ, под которым сохраняются данные во временное хранилище
	 * типа переменных сессии, файл, меркэш и т.п.
	 *
	 * @var string
	 */
	private $_key;

	/**
	 *
	 * @var boolean
	 */
	public $debug;
	
	/**
	 * Определяет нужно ли форматировать данные ячеек перед выводом. 
	 * Для вывода в Эксель необходимо отключить.
	 * 
	 * @var boolean
	 */
	public $formatting = true;

	/**
	 * Константы, используемые в формулах  (+VARXX;)
	 *
	 * @var array
	 */
	protected $_vars = array();

	/**
	 *
	 * @var Maestro_Unit
	 */
	private $_properties = array(
		'precision' => 2,   // точность вывода дробных чисел
		'header' => true,   // показывать шапку таблицы
		'export' => true,   // дать возможность выгрузить в Эксель
		'titles' => true,   // выводить заголовки таблицы
		'eyeplus' => false, // показывать знак "+" рядом с числами
		'negative' => true, // помечать отрицательные числа
		'template' => 'calc.html.xsl', // файл шаблона
		'outfilename' => '',
		'export_route' => '/exportsheet',
		'indexes' => false
	);

	/**
	 * Массив строк
	 *
	 * @var array
	 */
	private $_rows = array();

	/**
	 * Массив колонок
	 *
	 * @var type
	 */
	private $_cols = array();

	/**
	 * Массив данных
	 *
	 * @var array
	 */
	private $_data = array();

	/**
	 * Массив свойств ячеек
	 *
	 * @var array
	 */
	private $_cell = array();

	/**
	 * Служебный массив, в который заносятся отметки ячеек, по которым формулы были расчитаны.
	 * Заносятся только булевы значения
	 *
	 * @var array
	 */
	private $_estimated = array();

	/**
	 *
	 * @var array
	 */
	private $_relations = array();

	/**
	 * Массив связей между символьным индексом колонки и её произвольным идентификатором
	 *
	 * @var type
	 */
	private $_aa2c = array();

	/**
	 * DOM-документ с данными формы для трансформации
	 *
	 * @var Maestro_Dom
	 */
	protected $_xml;

	/**
	 *
	 * @var array
	 */
	private static $_init_col = array(
		'name' => '',
		'type' => 'string',
		'show' => true,
		'width' => null,
		'align' => null,
		'class' => null,
		'wrap' => true,
		'formula' => null,
		'AAA' => false // символьный идентификатор порядкового номера колонки
	);

	/**
	 *
	 * @var array
	 */
	private static $_init_row = array(
		'name' => '',
		'class' => '',
		'type' => 'normal', // тип колонки, normal - обычный, header - заголовки, th - заголовочные ячейки
		'show' => true
	);

	/**
	 * Различные наобры типов данных колонок
	 *
	 * @var array
	 */
	protected static $_types = array(
		'numeric' => array('float', 'integer', 'nominal', 'percent', 'rate'),
		'summary' => array('float', 'integer')
	);

	/**
	 * Конструктор
	 *
	 * @param string $key
	 * @param array $properties
	 */
	public function __construct($key, $properties = false) {
		$this->_key = $key;
		
		$this->_properties['class'] = 'table-report';
		
		
		if(is_array($properties)) {
			$this->_properties = array_merge($this->_properties, $properties);
		}
		$this->init();
	}

	/**
	 * Дополнительная инициализация для наследуемых классов
	 *
	 * @return void
	 */
	protected function init() {

	}

	/**
	 * Возвращает ключ страницы
	 *
	 * @return string
	 */
	public function getKey() {
		return $this->_key;
	}
	
	/**
	 *
	 * @param Maestro_Calc_Book $book 
	 */
	public function setBook($book) {
		$this->_book = $book;
	}

	/**
	 *
	 * @return Maestro_Calc_Book
	 */
	public function getBook() {
		return $this->_book;
	}

	/**
	 * Создает строку или устанавливает свойства текущей строки. Если
	 * установлен флаг $unique, то производится только добавление новых записей.
	 * Значения по умолчанию:
	 *
	 * <pre>
	 * 	'name' => '',
	 * 	'class' => '',
	 * 	'type' => 'normal', // normal-обычный, header-копировать заголовок,
	 * 	'show' => true
	 * </pre>
	 *
	 * где типы строки:
	 *	- normal - обычный
	 *	- header - копировать заголовок вместо строки
	 *	- th - ячейки в виде ячеек заголовков (<th></th>)
	 *
	 * @param integer|string $id
	 * @param array $info
	 * @param boolean $unique проверять существование строки
	 */
	public function setRow($id, $info = null, $unique = false) {
		if (!is_array($info)) {
			$info = array();
		}

		// если строка существует - пропишем в неё свойства из массива $info,
		// иначе берем начальные значения self::_init_row и совмещаем с массив $info
		if (array_key_exists($id, $this->_rows)) {
			$this->_rows[$id] = array_merge($this->_rows[$id], $info);
		} else {
			// новая строка
			$this->_rows[$id] = array_merge(self::$_init_row, $info);
		}
	}

	/**
	 *
	 * @param integer|string $id
	 * @param string $key
	 * @param mixed $value
	 * @return Maestro_Calc_Sheet
	 */
	public function setRowProperty($id, $key, $value) {
		if (array_key_exists($id, $this->_rows)) {
			$this->_rows[$id][$key] = $value;
		}
		return $this;
	}

	/**
	 * Возвращает значение указанного свойства строки
	 *
	 * @param integer|string $id
	 * @param string $key
	 * @return mixed
	 */
	public function getRowProperty($id, $key) {
		return isset($this->_rows[$id][$key]) ? $this->_rows[$id][$key] : null;
	}

	/**
	 *
	 */
	public function getRows() {
		return $this->_rows;
	}

	/**
	 * Возвращает массив идентификаторов строк
	 *
	 * @return array
	 */
	public function getRowKeys() {
		return array_keys($this->_rows);
	}

	/**
	 * Создает колонку или устанавливает свойства существующей колонки.
	 * Значения по умолчанию:
	 *
	 * <code>
	 * 	'name' => '',
	 * 	'type' => 'string',
	 * 	'show' => true,
	 * 	'width' => null,
	 * 	'align' => null,
	 * 	'class' => null,
	 * 	'wrap' => true,
	 * 	'formula' => null,
	 * 	'AAA' => false
	 * </code>
	 *
	 * @param integer|string $id
	 * @param array $info
	 * @return Maestro_Calc_Sheet
	 */
	public function setCol($id, $info = array()) {
		if (!is_array($info)) {
			$info = array();
		}

		// если колонка существует - пропишем в неё свойства из массива $info,
		// иначе берем начальные значения self::_init_col и совмещаем с массив $info
		if (array_key_exists($id, $this->_cols)) {
			$this->_cols[$id] = array_merge($this->_cols[$id], $info);
		} else {
			// новая колонка
			$this->_cols[$id] = array_merge(self::$_init_col, $info);

			// символьный индекс колонки - из текущего количества колонок
			$AA = Maestro_Calc_Cell::stringFromColumnIndex(count($this->_cols) - 1);
			$this->_cols[$id]['AAA'] = $AA;
			// связь индекс и идентификатора
			$this->_aa2c[$AA] = $id;

			// вычисляемые значения по умолчанию для чисел (если не задано)
			if (in_array($this->getColProperty($id, 'type'), self::$_types['numeric'])) {
				if (!isset($info['align'])) {
					$this->_cols[$id]['align'] = 'right';
				}
				if (!isset($info['wrap'])) {
					$this->_cols[$id]['wrap'] = false;
				}
			}
		}
		return $this;
	}
	
	/**
	 * Устанавливает свойства массива столбоцов
	 * 
	 * @param array $cols 
	 */
	public function setColumns($cols) {
		if(is_array($cols)) {
			foreach($cols as $name => $column) {
				$this->setCol($name, $column);
			}
		}
		return $this;
	}

	/**
	 *
	 * @param integer|string $id
	 * @param string $key
	 * @param mixed $value
	 * @return Maestro_Calc_Sheet
	 */
	public function setColProperty($id, $key, $value) {
		if (array_key_exists($id, $this->_cols)) {
			$this->_cols[$id][$key] = $value;
		}
		return $this;
	}

	/**
	 * Возвращает все свойства указанной колонки
	 *
	 * @param integer|string $id
	 * @param string $key
	 * @return mixed
	 */
	public function getColumn($id) {
		return isset($this->_cols[$id]) ? $this->_cols[$id] : null;
	}

	/**
	 * Возвращает значение указанного свойства колонки
	 *
	 * @param integer|string $id
	 * @param string $key
	 * @return mixed
	 */
	public function getColProperty($id, $key) {
		return isset($this->_cols[$id][$key]) ? $this->_cols[$id][$key] : null;
	}

	/**
	 * Возвращает массив колонок
	 *
	 * @return array
	 */
	public function getCols() {
		return $this->_cols;
	}

	/**
	 * Возвращает массив идентификаторов колонок
	 *
	 * @return array
	 */
	public function getColKeys() {
		return array_keys($this->_cols);
	}
	
	/**
	 *
	 * @param string $aaa
	 * @return strin
	 */
	public function byAAA($aaa) {
		if (!isset($this->_aa2c[$aaa])) {
			throw new Maestro_Exception('Unknown column index: ' . $aaa);
		}
		return $this->_aa2c[$aaa];
	}

	/**
	 *
	 * @param string $aaa
	 * @return strin
	 */
	public function byId($col) {
		if (!isset($this->_cols[$col])) {
			throw new Maestro_Exception('Unknown column id: ' . $col);
		}
		return $this->_cols[$col]['AAA'];
	}

	/**
	 *
	 * @return mixed
	 */
	public function getProperty($name, $default = null) {
		return isset($this->_properties[$name]) ? $this->_properties[$name] : $default;
	}

	/**
	 *
	 * @param string $name
	 * @param mixed $value
	 * @return Maestro_Calc_Sheet
	 */
	public function setProperty($name, $value) {
		$this->_properties[$name] = $value;
		return $this;
	}
	
	/**
	 * 
	 * @param string $name
	 * @param mixed $value
	 * @return \Maestro_Calc_Sheet
	 */
	public function setVar($name, $value) {
		$this->_vars[$name] = $value;
		return $this;
	}

	/**
	 * Возвращает свойство ячейки.
	 * Возможные свойства:
	 *	- formula
	 *	- rowspan
	 *	- colspan
	 *	- class
	 *
	 * @param integer|string $row
	 * @param integer|string $col
	 * @param string $name
	 * @param mixed $default
	 * @return string
	 */
	public function getCellProperty($row, $col, $name, $default = null) {
		return isset($this->_cell[$row][$col][$name]) ? $this->_cell[$row][$col][$name] : $default;
	}

	/**
	 * Устанавливает свойство ячейки.
	 * Возможные свойства:
	 *	- formula
	 *	- rowspan
	 *	- colspan
	 *	- class
	 *	- nowrap
	 *
	 * Если выключен флаг `overwrite`, то перезаписывается только предыдущее
	 * положительное (непустое) значение.
	 *
	 * @param integer|string $row
	 * @param integer|string $col
	 * @param string $name
	 * @param mixed $value
	 * @param boolean $overwrite
	 * @return Maestro_Calc_Sheet
	 */
	public function setCellProperty($row, $col, $name, $value, $overwrite = true) {

		if ($overwrite) {
			// просто записываем формулу
			$this->_cell[$row][$col][$name] = $value;
		} else if (!$this->getCellProperty($row, $col, $name)) {
			// только если формулы нет
			$this->_cell[$row][$col][$name] = $value;
		}
		return $this;
	}

	/**
	 *
	 * @param integer|string $row
	 * @param integer|string $col
	 * @return boolean
	 */
	public function getEstimated($row, $col) {
		return isset($this->_estimated[$row][$col]) ? $this->_estimated[$row][$col] : false;
	}

	/**
	 *
	 * @param integer|string $row
	 * @param integer|string $col
	 * @return Maestro_Calc_Sheet
	 */
	public function setEstimated($row, $col, $result = true) {
		$this->_estimated[$row][$col] = ($result);
		return $this;
	}

	/**
	 *
	 * @param integer|string $row
	 * @param integer|string $col
	 * @return string
	 */
	public function getFormula($row, $col) {
		return $this->getCellProperty($row, $col, 'formula');
	}

	/**
	 *
	 * @param integer|string $row
	 * @param integer|string $col
	 * @param boolean $overwrite
	 * @return Maestro_Calc_Sheet
	 */
	public function setFormula($row, $col, $formula, $overwrite = true) {
		$this->setCellProperty($row, $col, 'formula', $formula, $overwrite);
		return $this;
	}

	/**
	 * Возвращает значение из ячейки данных
	 *
	 * @param integer|string $row
	 * @param integer|string $col
	 * @return float
	 */
	public function getData($row, $col) {
		return isset($this->_data[$row][$col]) ? $this->_data[$row][$col] : 0;
	}

	/**
	 * Устанавливает значение в ячейке данных
	 *
	 * @param integer|string $row
	 * @param integer|string $col
	 * @param integer $rspan
	 * @param integer $cspan
	 * @param mixed $value
	 */
	public function setData($row, $col, $value, $rspan = false, $cspan = false) {
		$this->_data[$row][$col] = $value;
		if($rspan && is_integer($rspan)) {
			$this->setCellProperty($row, $col, 'rowspan', $rspan);
		}
		if($cspan && is_integer($cspan)) {
			$this->setCellProperty($row, $col, 'colspan', $cspan);
		}
	}
	
	/**
	 * Устанавливает массив данных
	 * 
	 * @param array $values 
	 * @return Maestro_Calc_Sheet
	 */
	/*public function initData($values) {
		if(is_array($values)) {
			$this->_data = $values;
		}
		return $this;
	}*/

	/**
	 * Получение и заполнение данных
	 *
	 * @return Maestro_Calc_Sheet
	 */
	//abstract 
	public function create() {}

	/**
	 *
	 * @param string $name
	 * @return Maestro_XmlObject_Grid
	 */
	/* public function grid($name) {
	  } */

	/**
	 * Устанавливает имя формы.
	 * Используется для идентификации виджета в `view`.
	 * Дополнительно, как название массива имен полей формы,
	 * т.е. имена полей будут иметь вид: name[field_name]
	 *
	 * @param string $name
	 * @return Maestro_Calc_Sheet
	 */
	public function setName($name) {
		$name = Maestro_Common::filterName($name);
		if ('' == (string) $name) {
			throw new Maestro_XmlObject_Exception('Некорректное имя формы. Должно быть непустым и содержать только корректные символы');
		}

		$this->_key = $name;
		return $this;
	}

	/**
	 * Возвращает имя формы
	 *
	 * @return string
	 */
	public function getName() {
		return $this->_key;// . 'Sheet';
	}

	/**
	 *
	 */
	public function getDocument($calculate = true) {
		$stylesheet = new DomDocument();
		$stylesheet->formatOutput = true;
		$stylesheet->preserveWhiteSpace = false;

		$tplFileName = Maestro_App::getConfig()->Path->templates . $this->getProperty('template');

		if (file_exists($tplFileName)) {
			$stylesheet->load($tplFileName);
		}

		if($calculate) {
			// предварительные подсчеты и расстановка формул
			$this->_prepareCells();
			// расчитаем формулы
			$this->estimateStart();
		}
		// создадим документ
		$this->_createDocument();

		if ($this->debug) {
			//pre(htmlspecialchars(htmlspecialchars($this->_xml->saveXML())), '#b0d51c');
			//pre(htmlspecialchars(htmlspecialchars($stylesheet->saveXML())), '#357e9d', 'white');
		}

		$processor = new XSLTProcessor();
		$processor->importStylesheet($stylesheet);		

		return $processor->transformToDoc($this->_xml);
	}

	/**
	 * Устанавливает зависимость "строка <-> итоговая (суммирующая) строка"
	 *
	 * @param integer|string $owner_row
	 * @param integer|string $child_row
	 * @return Maestro_Calc_Sheet
	 */
	public function setRelations($owner_row, $child_row) {
		if ($owner_row !== false && $child_row !== false) {
			$this->_relations[$owner_row][] = $child_row;
		}
		return $this;
	}

	/**
	 * Пропишем формулы в ячеках колонок, для которых (колонок) заданы свойства `formula`
	 *
	 * @return Maestro_Calc_Sheet
	 */
	protected function _prepareCells() {
		// прописываем формулы из колонок в ячейки
		foreach ($this->_cols as $col => $cinfo) {
			if ($cinfo['formula']) {
				foreach ($this->_rows as $row => $rinfo) {
					$rtype = $this->getRowProperty($row, 'type');
					if('header' != $rtype && 'th'  != $rtype) {
						$this->setFormula($row, $col, $cinfo['formula']);
					}
				}
			}
		}
		// прописываем формулы суммирования "дочерних" строк на основании
		// данных массива $this->_relations
		foreach ($this->_rows as $row => $rinfo) {
			$f = '';
			// составляем формулу суммирования по всем дочерним строкам
			if (array_key_exists($row, $this->_relations) && is_array($this->_relations[$row])) {
				$f = sprintf('+SUMR(%s);', implode(',', $this->_relations[$row]));
			}
			// в каждую "числовую" ячейку строки прописываем данную формулу
			// при условии, что там еще нет формулы
			foreach ($this->_cols as $col => $cinfo) {
				if (in_array($cinfo['type'], self::$_types['summary'])) {
					$this->setFormula($row, $col, $f, false);
				}
			}
		}
		// если включен параметр "показывать всё", показываем скрытые колонки и подсвечиваем их,
		// со строками поступаем так же
		if ($this->getProperty('show_all')) {
			foreach ($this->_cols as $col => $cinfo) {
				if (!$cinfo['show']) {
					$this->_cols[$col]['show'] = true;
					$this->_cols[$col]['class'] = 'behind';
				}
			}
			foreach ($this->_rows as $row => $rinfo) {
				if (!$rinfo['show']) {
					$this->_rows[$row]['show'] = true;
					$this->_rows[$row]['class'] = 'behind';
				}
			}
		}
		return $this;
	}

	/**
	 *
	 */
	protected function _createDocument() {
		$this->_xml = new Maestro_Dom('1.0', 'UTF-8');
		$this->_xml->formatOutput = true;

		// таблица и свойства		
		$this->_properties['outfilename'] = 'report.' . date('YmdHis') . '.xml';
		$nodeGrid = $this->_xml->addNode($this->_xml, 'objectGrid', NULL, $this->getName());
		$nodeGrid->setAttribute('key', $this->_key);
		$this->_xml->addArray($this->_xml->addNode($nodeGrid, 'properties'), $this->_properties);

		// колонки
		$nodeCols = $this->_xml->addNode($nodeGrid, 'columns');
		foreach ($this->_cols as $cid => $cinfo) {
			if(!$this->formatting && 'viewonly' == $this->getColProperty($cid, 'type')) {
				continue;
			}
			if ($this->getColProperty($cid, 'show')) {
				$nodeCol = $this->_xml->addNode($nodeCols, 'col', null, $cid);
				$this->_xml->addArray($nodeCol, $cinfo);
			}
		}

		$hidedCells = array();
		// данные
		$rowIndex = -1;
		$nodeData = $this->_xml->addNode($nodeGrid, 'data');
		foreach ($this->_rows as $rid => $rinfo) {
			$rowIndex++;
			if (!$this->getRowProperty($rid, 'show')) {
				continue;
			}
			$nodeRow = $this->_xml->addNode($nodeData, 'row', null, $rid);
			// class
			$_class = $this->getRowProperty($rid, 'class');
			if ($_class) {
				$nodeRow->setAttribute('class', $_class);
			}
			// type
			$nodeRow->setAttribute('type', $this->getRowProperty($rid, 'type'));

			$colIndex = -1;
			$colAbsoluteIndex = 0;
			foreach ($this->_cols as $cid => $cinfo) {
				$colIndex++;
				// тип столбца
				$ctype = $this->getColProperty($cid, 'type');
				//
				if(!$this->formatting && 'viewonly' == $ctype) {
					continue;
				}
				// пропускаем скрытые столбцы
				if (!$this->getColProperty($cid, 'show')) {
					continue;
				}
				$colAbsoluteIndex++;
				// пропускаем перекрываемые ячейки
				if(isset($hidedCells[$rowIndex][$colIndex])) {
					continue;
				}			
				$is_input = (bool)($this->getCellProperty($rid, $cid, 'input'));
				// получаем значение ячейки
				if ('rowname' == $ctype) {
					$value = isset($rinfo['name']) ? $rinfo['name'] : '&#xa0;';
				} else if ($this->debug) {
					$value = $this->getFormula($rid, $cid);
				} else {
					$value = $this->getDataFormat($rid, $cid, $is_input);
				}
				//
				$nodeCol = $this->_xml->addNode($nodeRow, 'col', $value, $cid);				
				$nodeCol->setAttribute('index', $colAbsoluteIndex);
				$nodeCol->setAttribute('type', $ctype);
				// align
				$_align = $this->getColProperty($cid, 'align');
				if ($_align) {
					$nodeCol->setAttribute('align', $_align);
				}
				// class
				$_class = $this->getColProperty($cid, 'class');
				if ($_class) {
					$nodeCol->setAttribute('class', $_class);
				}
				// отрицательные числа
				if (!$this->debug && in_array($ctype, self::$_types['numeric']) && $this->getProperty('negative')) {
					if((float)$this->getData($rid, $cid) < 0) {
						$nodeCol->setAttribute('negative', true);
					}
				}
				// wrap
				$_nowrap = !$this->getColProperty($cid, 'wrap');
				if ($_nowrap) {
					$nodeCol->setAttribute('nowrap', $_nowrap);
				}
				// ajax - значение ячейки в виде ссылки, где ajax - обработка события onclick
				if(($_ajax = $this->getCellProperty($rid, $cid, 'ajax'))) {
				    $_ajax = str_replace('{row}', $rid, $_ajax);
				    $_ajax = str_replace('{col}', $cid, $_ajax);
					$nodeCol->setAttribute('ajax', $_ajax);
				}
				$spannedRows = array();
				//rowspan
				if(($_span = $this->getCellProperty($rid, $cid, 'rowspan'))) {
					$nodeCol->setAttribute('rowspan', $_span);
					// помечаем как скрытые перекрываемые ячейки по столбцу
					$_rn = $rowIndex + 1;
					$_re = $rowIndex + $_span - 1;
					while($_rn <= $_re) {
						$hidedCells[$_rn][$colIndex] = true;
						$spannedRows[] = $_rn;
						$_rn++;
					}
				}
				//colspan
				if(($_span = $this->getCellProperty($rid, $cid, 'colspan'))) {
					$nodeCol->setAttribute('colspan', $_span);
					// помечаем как скрытые перекрываемые ячейки по строке
					$_cn = $colIndex + 1;
					$_ce = $colIndex + $_span - 1;
					while($_cn <= $_ce) {
						$hidedCells[$rowIndex][$_cn] = true;
						// проверяем случай, одновременного объединения строк и столбцов
						foreach($spannedRows as $sprow) {
							$hidedCells[$sprow][$_cn] = true;
						}
						$_cn++;
					}
				}
				// cell class
				if(($_class = $this->getCellProperty($rid, $cid, 'class'))) {
					$nodeCol->setAttribute('class', $_class);
				}
				// cell input
				if($is_input) {
					$nodeCol->setAttribute('input', $this->getCellProperty($rid, $cid, 'input'));
				}
			}
		}
		//if ($this->debug) {
			//pre(htmlspecialchars(htmlspecialchars($this->_xml->saveXML())), '#709f14', 'white');
		//}
	}

	/**
	 *
	 * @param integer|string $row
	 * @param integer|string $col
	 * @param boolean $input
	 * @return string
	 */
	public function getDataFormat($row, $col, $input = false) {
		$value = $this->getData($row, $col);
		$out = trim($value);
		$ctype = $this->getColProperty($col, 'type');
		
		if(!$this->formatting) {
			if(in_array($ctype, self::$_types['numeric']) && !$out) {
				$out = null;
			} else {
				$out = str_replace('«', '"', $out);
				$out = str_replace('»', '"', $out);			
			}
			return $out;
		}

		$delim = $input ? '' : ' ';
		$decdelim = $input ? '.' : ',';
		$prec = $this->_properties['precision'];
		$eyeplus = $this->_properties['eyeplus'];
		
		// пустое значение
		if (!$value) {
			if ($input) {
				$out = '';
			} else {
				$out = '&#xa0;';
			}
		} else if('rate' == $ctype) {
			if(is_numeric($value)) {
				$out = $eyeplus . number_format($value, 4, $decdelim, $delim);
			}
		} else if ('float' == $ctype || 'percent' == $ctype || 'nominal' == $ctype) {
			// значения в пределах 1 не округляем никогда
			if (abs($value) < 1) {
				$prec = 2;
			}

			if (is_numeric($value)) {
				if ($eyeplus) {
					$eyeplus = $value > 0 ? '+' : '';
				}
				$out = $eyeplus . number_format($value, $prec, $decdelim, $delim);
			}
		}
		return $out;
	}

	/**
	 * Сохраняем колонки, строки и данные в переменные сессии
	 *
	 * @return Maestro_Calc_Sheet
	 */
	public function backup() {
		echo '<div class="clearfix"><small class="muted pull-right">backup '.$this->_key . '</small></div>';
		Session::setkey2('Calc', $this->_key, 'props', $this->_properties);
		Session::setkey2('Calc', $this->_key, 'cols', $this->_cols);
		Session::setkey2('Calc', $this->_key, 'rows', $this->_rows);
		Session::setkey2('Calc', $this->_key, 'data', $this->_data);
		Session::setkey2('Calc', $this->_key, 'cell', $this->_cell);
		Session::setkey2('Calc', $this->_key, 'aa2c', $this->_aa2c);
		Session::setkey2('Calc', $this->_key, 'relations', $this->_relations);
		return $this;
	}

	/**
	 * Восстанавливаем колонки, строки и данные из переменных сессии
	 *
	 * @return Maestro_Calc_Sheet
	 */
	public function restore() {
		$this->_properties = Session::getkey2('Calc', $this->_key, 'props', array());
		$this->_cols = Session::getkey2('Calc', $this->_key, 'cols', array());
		$this->_rows = Session::getkey2('Calc', $this->_key, 'rows', array());
		$this->_data = Session::getkey2('Calc', $this->_key, 'data', array());
		$this->_cell = Session::getkey2('Calc', $this->_key, 'cell', array());
		$this->_aa2c = Session::getkey2('Calc', $this->_key, 'aa2c', array());
		$this->_relations = Session::getkey2('Calc', $this->_key, 'relations', array());
		return $this;
	}

	/**
	 * 
	 */
	public function exportToExcel() {
		$this->setProperty('template', 'calc.excel.xsl');
		$this->formatting = false;

		$doc = $this->getDocument(false);
		$doc->formatOutput = true;
		$strdoc = $doc->saveXML();	
		$strdoc = '<?xml version="1.0"?>'.PHP_EOL.'<?mso-application progid="Excel.Sheet"?>' . PHP_EOL . substr($strdoc, 21);
		
		//header('Content-Type: application/vnd.ms-excel; charset=windows-1251');
		//header('Content-Disposition: attachment; filename="report.xml"');
		//header('Content-Length: ' . strlen($strdoc));
		/*if(1 == Session::subject()) {
			$fname = realpath(Maestro_App::getConfig()->Path->cache).'/export.xml';
			file_put_contents($fname, $strdoc);
			pre($fname);
		} else {*/		
		//}
		
		header('Content-Type: text/xml');
		header('Content-Disposition: attachment; filename="report.' . date('YmdHis') . '.xml"');
		$tags = array('<br/>', '<br>');
		$news = array(PHP_EOL, PHP_EOL);
		echo str_replace($tags, $news, $strdoc);
	}

	/**
	 * Очищает отметки о проведении расчетов ячеек
	 *
	 * @return Maestro_Calc_Sheet
	 */
	public function estimateClear() {
		$this->_estimated = array();
		return $this;
	}

	/**
	 *
	 * @return Maestro_Calc_Sheet
	 */
	public function estimateStart() {
		foreach ($this->_rows as $row => $rinfo) {
			foreach ($this->_cols as $col => $cinfo) {
				if ($this->getFormula($row, $col)) {
					$this->estimateCell($row, $col);
				}
			}
		}
		return $this;
	}

	/**
	 *
	 * @param integer|string $row
	 * @param integer|string $col
	 * @return mixed
	 */
	public function estimateCell($row, $col) {
		return $this->_estimateCell($row, $col);
	}

	/**
	 *
	 * @param integer|string $row
	 * @param integer|string $col
	 * @return mixed
	 */
	private function _estimateCell($row, $col) {
		// получаем текущее значение ячейки
		$val_start = $this->getData($row, $col);
		// если ячейка уже расчитана - возвращаем текущее значение
		if (!$this->getFormula($row, $col) || $this->getEstimated($row, $col)) {
			return $val_start;
		}

		// начальные значения результата расчетов
		$val_cell = 0;
		// начальное значение текущего элемента формулы
		$val_each = $val_start;
		// получаем формулу и разбиваем её на составляющие
		$items = explode(";", $this->getFormula($row, $col));
		// по всем элементам формул
		foreach ($items as $i => $item) {
			if (empty($item)) {
				continue;
			}
			$oper = substr($item, 0, 1);
			$val_each = 0;


			if (preg_match("/^(.)PARAM\((.+)\)/", $item, $matches)) {
				$oper = $matches[1];
				$keys = explode(',', $matches[2]);
				$kcount = count($keys);
				if (1 == $kcount) {
					$k1 = $keys[0];
					$val_each = isset($this->_properties[$k1]) ? $this->_properties[$k1] : 0;
				} elseif (2 == $kcount) {
					$k1 = $keys[0];
					$k2 = $keys[1];
					$val_each = isset($this->_properties[$k1][$k2]) ? $this->_properties[$k1][$k2] : 0;
				} elseif (3 == $kcount) {
					$k1 = $keys[0];
					$k2 = $keys[1];
					$k3 = $keys[2];
					$val_each = isset($this->_properties[$k1][$k2][$k3]) ? $this->_properties[$k1][$k2][$k3] : 0;
				} else {
					throw new Maestro_Exception('Слишком много параметров для формулы: ' . $item);
				}
			} else if (preg_match("/^(.)THIS/", $item, $matches)) {
				$oper = $matches[1];
				$val_each = $val_start;
			} else if (preg_match("/^(.)CONST(.+)/", $item, $matches)) {
				$oper = $matches[1];
				$val_each = floatval($matches[2]);
			} else if (preg_match("/^(.)VAR(.+)/", $item, $matches)) {
				$oper = $matches[1];
				$var_name = $matches[2];
				$val_each = isset($this->_vars[$var_name]) ? $this->_vars[$var_name] : 0;
			} else if (preg_match("/^(.)FORMAT(.+)/", $item, $matches)) {
				$oper = $matches[1];
				$val_each = sprintf($matches[2], $val_cell);
				$val_cell = 0;
			} else if (preg_match("/^(.)OVERFLOW([[:digit:]]+),([[:digit:]]+)/", $item, $matches)) {
				$oper = $matches[1];
				if($val_cell > $matches[2]) {
					$val_cell = 0;
					$val_each = $matches[3];
				}
			} else if (preg_match("/^(.)CONCAT(.+)/", $item, $matches)) {
				$oper = $matches[1];
				$val_each = $val_cell.$matches[2];
			} else if (preg_match("/^(.)SUMR\((.+)\)/", $item, $matches)) {
				# получить сумму значений списка строк по текущей колонке: +SUMR(10,aaw,20,21,22);
				$oper = $matches[1];
				$cidx = $col;
				$list = explode(',', $matches[2]);
				$val_each = 0;
				foreach ($list as $ridx) {
					$val_each += $this->_estimateCell(trim($ridx), $cidx);
				}
			} else if (preg_match("/^(.)([A-Z]+)([[:digit:]]+)/", $item, $matches)) {
				# получить значения другой ячейки: +AC16;
				$oper = $matches[1];
				$cidx = $this->byAAA($matches[2]);
				$ridx = $matches[3];
				$val_each = $this->_estimateCell($ridx, $cidx);
			} else if (preg_match("/^(.)([A-Z]+)/", $item, $matches)) {
				# получить значения в этой же строке по указанной колонке: +AA;
				$oper = $matches[1];
				$cidx = $this->byAAA($matches[2]);
				$ridx = $row;
				$val_each = $this->_estimateCell($ridx, $cidx);
			} else if (preg_match("/^(.)(.+)!([A-Z]+)([[:digit:]]+)/", $item, $matches)) {
				# получить значения другой ячейки: +$'sheet'.AC16; - другая страница
				$oper = $matches[1];
				$shee = $matches[2];
				$cidx = $this->getBook()->getSheet($shee)->byAAA($matches[3]);
				$ridx = $matches[4];
				$val_each = $this->getBook()->getSheet($shee)->estimateCell($ridx, $cidx);
			} else if (preg_match("/^(.)(.+)!([A-Z]+)/", $item, $matches)) {
				# получить значения в этой же строке по указанной колонке: +AA; - другая страница
				$oper = $matches[1];
				$shee = $matches[2];
				$cidx = $this->getBook()->getSheet($shee)->byAAA($matches[3]);
				$ridx = $row;
				$val_each = $this->getBook()->getSheet($shee)->estimateCell($ridx, $cidx);
			} else if (preg_match("/^(.)CONST(.+)/", $item, $matches)) {
				$oper = $matches[1];
				$val_each = floatval($matches[2]);
			}

			/* if (isset($matches)) {
			  pre($matches);
			  pre($val_each, 'cyan');
			  } */
			// применяем полученное на этом фрагменте значение к значению ячейки
			switch ($oper) {
				case '+':
					$val_cell += $val_each;
					break;
				case '-':
					$val_cell -= $val_each;
					break;
				case '*':
					$val_cell *= $val_each;
					break;
				case '/':
					if ((int) $val_each) {
						$val_cell = $val_cell / $val_each;
					}
					break;
				case '=':
					$val_cell = $val_each;
					break;
				default:
					throw new Maestro_Exception(sprintf(get_class() . ': Unknown operation `%s` in R%sC%s', $oper, $row, $col));
			}
		}

		// заносим вычисленное значение в ячейку
		$this->setData($row, $col, $val_cell);
		// отмечаем ячейку как вычисленную
		$this->setEstimated($row, $col, true);
		// возвращаем вычисленное значение
		//pre($val_cell, '$eee1');
		return $val_cell;
	}

}

?>
