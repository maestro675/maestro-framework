<?php

/**
 * 
 */
class ValidateException extends Exception {
	
}

/**
 * Description of Abstract
 *
 * @author maestro
 */
abstract class Maestro_Mapper_Abstract {

	const NEWID = -1;
	
	const FETCH_MODE_DEFAULT = 'default';
	const FETCH_MODE_SINGLE  = 'single';
	const FETCH_MODE_KEYVAL  = 'keyvalue';
	const FETCH_MODE_ARRAYS  = 'arrays';

	/**
	 * Включает режим отладки
	 *
	 * @var bool
	 */
	public $debug = false;

	/**
	 * В объектах, созданных из результатов запроса,
	 * преобразует значения полей типа "date" в строку формата 'd.m.Y'
	 *
	 * @var bool
	 */
	public $eyeDate = false;

	/**
	 * Объект базы данных
	 *
	 * @var DBSimple object
	 */
	public $db;

	/**
	 * Объект описаний полей и таблицы БД
	 *
	 * @var Maestro_Mapper_Defines
	 */
	protected $_defines;

	/**
	 * Объект валидации элементов
	 *
	 * @var Maestro_Validator
	 */
	protected $_validator;

	/**
	 * Алиас или объект базы данных
	 *
	 * @var string|object
	 */
	protected $_initDatabase;

	/**
	 * Массив описаний полей элементов.
	 * Используется при инициализации объекта `_defines`
	 *
	 * @var array
	 */
	protected $_initFields;

	/**
	 * Имя и алиас основной таблицы объектов.
	 * Используется при инициализации объекта `_defines`
	 *
	 * @var string|array
	 */
	protected $_initTable;

	/**
	 * Экземпляр класса-построителя SQL-запроса
	 * 
	 * @var Maestro_Mapper_Select
	 */
	protected $_builder;

	/**
	 * Массив из двух элементов ID, PARENTID для построения дерева
	 *
	 * @var string
	 */
	protected $_tree_fields = null;

	/**
	 * Параметры обработки метода $this->get().
	 * Перекрываются параметрами, передаваемыми в метод  $this->fetch()
	 *
	 * mode => null
	 *  возвращать массив объектов
	 * mode => single
	 *  возвращать один объект
	 * mode => keyvalues
	 * field => <name>
	 *  возвращать массив с идентификаторами в виде ключей и значениями поля <name>
	 *  в виде значений
	 *
	 * @var Maestro_Unit
	 */
	protected $_fetchOptions = null;
	
	private $_fetchOptionsInit = array(
		'mode' => 'all',
		'field' => null,
		'require_pk' => true, // добавлять в выборку поле первичного ключа при отсутствии
		'firstEmpty' => false,
		'firstEmptyKey' => null
	);

	/**
	 * Вспомагательный список полей.
	 * Используется, например, при `update` для отметки полей из доп.(связанных) таблиц.
	 *
	 * @var array
	 */
	protected $_affected_fields = array();

	/**
	 * Параметры выполняемого запроса
	 * 
	 * @var array
	 */
	protected $_parameters = array();

	/**
	 * Конструктор класса
	 *
	 * @param object|string $db
	 */
	public function __construct($db = null) {
		if (is_null($db)) {
			$this->db = $this->_initDatabase;
		}
		$this->db = is_object($db) ? $db : DatabaseFactory::connect($db);
		$this->_set_defines();
		$this->_set_validator();
		$this->init();
	}

	/**
	 * Процедура для доп.действий в констукторе
	 */
	public function init() {
		
	}

	/**
	 * 
	 * @return Maestro_Mapper_Defines
	 */
	public function getDefines() {
		return $this->_defines;
	}
	
	/**
	 *
	 * @return Maestro_Select
	 */
	public function getBuilder() {
		return $this->_builder;
	}
	
	/**
	 * Возвращаем текущий массив параметров запроса
	 * 
	 * @return array
	 */
	public function getParams() {
		return $this->_parameters;
	}

	/**
	 *
	 * @param string $id_field
	 * @param string $pd_field
	 * @param bool   $expand
	 */
	public function forest($id_field, $pd_field, $expand = false) {
		$this->_tree_fields = array();
		$a = $this->_defines->getField($id_field);
		$p = $this->_defines->getField($pd_field);
		if (!is_null($a) && !is_null($p)) {
			$this->_tree_fields = array(
				$a[Maestro_Mapper_Defines::F_TRUENAME],
				$p[Maestro_Mapper_Defines::F_TRUENAME],
				$expand
					//$this->_defines->fields($id_field, Maestro_Mapper_Defines::F_TRUENAME),
					//$this->_defines->fields($pd_field, Maestro_Mapper_Defines::F_TRUENAME)
			);
		} else
		if ($this->debug) {
			pre('One or both fields for <b>forest</b> not found: `' . $id_field . '`, `' . $pd_field . '`');
		}
		return $this;
	}

	/**
	 * Создает новый элемент.
	 * Варианты использования:
	 *
	 * <pre>
	 * # Элемент создается пустым, присутствует только идентификатор, являющийся первичным ключом.
	 * Model->createFromArray();
	 * </pre>
	 *
	 * <pre>
	 * # Идентификатор нового элемента задан первым параметром. Второй параметр - массив значений,
	 * # с алиасами полей в качестве ключей.
	 * Model->createFromArray( $id, $data );
	 * # Можно использовать, например, для создания элемента из результатов HTML-формы
	 * Model->createFromArray( -1, $_POST );
	 * </pre>
	 *
	 * @param integer $id
	 * @param array $values
	 * @return Maestro_Unit object
	 */
	public function createFromArray($id = NULL, $values = array()) {
		// пустой объект
		if (NULL == $id && !$values) {
			$primainfo = $this->_defines->getPrimaryInfo();
			return $this->_unit_create(array(
				$primainfo[Maestro_Mapper_Defines::F_TRUENAME] => null//self::NEWID
			));
		} else {
			if (($values instanceof Maestro_Unit) || ($values instanceof Maestro_Parameters)) {
				$values = $values->vars();
			}
			return $this->_array_to_unit($id, $values);
		}
	}

	/**
	 * Удаляет элемент из таблицы БД
	 *
	 * @param integer|Maestro_Unit $u
	 */
	public function delete($u) {
		if ($u instanceof Maestro_Unit) {
			$this->_sql_delete($u->id);
		} else
		if (is_numeric($u)) {
			$this->_sql_delete($u);
		}
	}

	/**
	 * Устанавиливает или дополняет параметры запроса
	 * массивом входящих параметров
	 *
	 * @param array $options
	 * @return this
	 */
	public function options($options) {
		if (!($this->_fetchOptions instanceof Maestro_Unit) || is_null($options)) {
			$this->_fetchOptions = new Maestro_Unit(NULL, $this->_fetchOptionsInit);
		}

		if (is_array($options)) {
			$this->_fetchOptions->merge($options);
		}

		return $this;
	}

	/**
	 * Инициализация построителя SQL-запроса типа `select` (выборка данных).
	 * Возвращает указатель на себя. Благодаря `magic` методу `__call` позволяет строить
	 * chaining вызовы методов построителя.
	 * Непосредственно выборка осуществляется в методе $this->fetch().
	 *
	 * Пример 1. Выборка одной записи по первичному ключу:
	 *
	 * <pre>
	 * # получаем один элемент с id=45
	 * $Mapper->get( 45 )->fetch();
	 * # получаем массив элементов с ограничениями по id
	 * $Mapper->get( array( 1, 2, 5) )->fetch();
	 * </pre>
	 *
	 * Пример 2. Выборки массива элементов с использованием методов построителя запроса
	 *
	 * <pre>
	 * $Mapper->get()
	 *   ->where( "NAME like 'abc%'" )
	 *   ->order( 'AGE' )
	 *   ->fetch();
	 * </pre>
	 *
	 *  Подробнее по методам построителя см.класс Maestro_Mapper_Select
	 *
	 *
	 * @return mixed
	 */
	public function get(/* ... */) {
		$this->_parameters = array();
		// получаем аргументы функции
		$args = func_get_args();
		$num_args = count($args);

		// параметры запроса.
		$this->options(array());
		if (NULL == $this->_fetchOptions->mode) {
			$this->_fetchOptions->mode = self::FETCH_MODE_DEFAULT;
		}

		// инициализируем построитель запроса
		$this->_set_builder_select();

		// есть по первичному ключу
		if (1 == $num_args) {
			$pkey = $args[0];
			if (is_null($pkey) || '' == $pkey) {
				$pkey = -1;
			}
			// первичный ключ - из массива значений
			if (is_array($pkey)) {
				$this->_builder->where(
						sprintf("%s.%s in ( %s )", $this->_defines->tableAlias, $this->_defines->primaryName, implode(',', $pkey)
						)
				);
			}
			// соответствие первичному ключу
			else {
				$this->_fetchOptions->mode = self::FETCH_MODE_SINGLE;
				$this->_builder->where(
						sprintf("%s.%s = %s", $this->_defines->tableAlias, $this->_defines->primaryName, $this->_defines->getPlaceholder($this->_defines->primaryAlias)
						)
				);
				$this->param($pkey);
			}
		}
		return $this;
	}

	/**
	 * Задается список алиасов полей для запроса.
	 * Можно задавать как неименованный массив полей, так и список имен полей,
	 * разделенных запятыми (без пробелов).
	 * При указании символа '*' будут выбраны все известные поля (Maestro_Defines::names)
	 *
	 * @param string|array $cols
	 */
	public function columns($cols) {
		$_columns = array();
		// добавляем массив имен полей основной таблицы
		if ('*' == $cols) {
			$_columns = $this->_defines->getNames();
		} else {
			if (!is_array($cols)) {
				$cols = explode(',', $cols);
			}
			foreach ($cols as $alias => $name) {
				$name = trim($name);
				if (!is_numeric($alias)) {
					$alias = 'JOINED__' . $alias;
					$_columns[$alias] = $name;
					continue;
				}
				// добавляем поля из если поле из имен основной таблицы
				if ($this->_defines->issetField($name)) {
					$_columns[] = $name;
				}
			}
		}
		$this->_builder->columns($_columns, $this->_defines->tableAlias);
		return $this;
	}

	/**
	 *
	 * @param mixed $value
	 */
	public function param($value) {
		$this->_parameters[] = $value;
		return $this;
	}

	/**
	 *
	 * @param string $condition
	 * @param mixed $value
	 */
	public function where($condition, $value = null) {
		if (is_array($value)) {
			foreach ($value as $v) {
				$this->_parameters[] = $v;
			}
		} else
		if (!is_null($value)) {
			$this->_parameters[] = $value;
		}
		$this->_builder->where($condition, $value);
		return $this;
	}

	/**
	 *
	 * @param string $condition
	 * @param mixed $value
	 */
	public function orwhere($condition, $value = null) {
		if (null != $value) {
			$this->_parameters[] = $value;
		}
		$this->_builder->orwhere($condition, $value);
		return $this;
	}

	/**
	 * Выполняет запрос типа `select` и возвращает один или массив элементов
	 * Пример:
	 * <pre>
	 * $mapper->get(45)->fetch();      // один объект с ПК=45
	 * $mapper->get()->fetch();        // массив объектов
	 * </pre>
	 *
	 * @return mixed
	 */
	public function fetch() {
		//$this->_fetchOptions->merge();
		return $this->_fetch();
	}

	/**
	 * Выполняет запрос типа `select` и возвращает только первый элемент
	 * Пример:
	 * <pre>
	 * $mapper->get()->fetchOne(); // только первый объект
	 * </pre>
	 *
	 * @return mixed
	 */
	public function fetchOne() {
		$this->_fetchOptions->mode = self::FETCH_MODE_SINGLE;
		return $this->_fetch();
	}

	/**
	 * Выполняет запрос типа `select` и возвращает результат в виде массива,
	 * где ключи - идентификаторы объектов, значения - поле $field (по умолчанию - `name`)
	 *
	 * @param string $namefield
	 * @return array
	 */
	public function fetchKeyValues($namefield = 'name') {
		$this->_fetchOptions->mode = self::FETCH_MODE_KEYVAL;
		$this->_fetchOptions->field = $namefield;

		return $this->_fetch();
	}

	/**
	 * Выполняет запрос типа `select` и возвращает результат в виде массива,
	 * где ключи - идентификаторы объектов, элементы - ассоциативные массивы полей
	 *
	 * @param string $namefield
	 * @return array
	 */
	public function fetchArrays() {
		$this->_fetchOptions->mode = self::FETCH_MODE_ARRAYS;
		return $this->_fetch();
	}

	/**
	 * Записывает элемент в таблицу. В зависимости от id, производится или
	 * вставка записи, или обновление
	 *
	 * @param Maestro_Unit $u
	 * @return boolean
	 */
	public function save($u) {
		if (!$u) {
			throw new Exception(get_class($this) . '.save: unknown Unit or Unit not exists');
		}

		//if( self::NEWID == $u->id || is_null( $u->id ) )
		if ($this->_sql_exists($u)) {
			return $this->update($u);
		} else {
			return $this->insert($u);
		}
	}

	/**
	 *
	 * @param Maestro_Unit $u
	 * @return boolean
	 */
	public function insert($u) {
		/*try {*/
			// получаем новый ID (если используется генератор)
			$id = $this->_new_id();
			// если новый ID получен, задаем его для объекта
			if (!is_null($id)) {
				$u->setid($id);
			}
			$this->_invoke('before_insert', $u);
			$this->_sql_insert($u);
			$this->_invoke('after_insert', $u);
			return true;
		/*} catch(ValidateException $e) {
			$text = $e->getMessage();
			if('development' == APPLICATION_ENV || Maestro_App::getRouter()->isAdmin) {
				$text .= '<hr/><blockquote>' . str_replace(PHP_EOL,'<br/>',print_r($u->toArray(), true)).'</blockquote>';
			}
			Common::alert(
				'Mapper validate error', 
				 $text, 
				'error', 
				false, 
				true
			);
			return false;
		} catch (Exception $e) {
			throw new Maestro_Exception($e);
			//$this->_exception( $e );
			//return false;
		}*/
	}

	/**
	 * Обновление записи таблицы
	 *
	 * @param Maestro_Unit $u
	 * @return boolean
	 */
	public function update($u) {
		/*try {*/
			$this->_invoke('before_update', $u);
			$this->_sql_update($u);
			$this->_invoke('after_update', $u);
			return true;
		/*} catch (Exception $e) {
			$this->_exception($e);
			return false;
		}*/
	}

	/**
	 * Валидация элемента
	 *
	 * @param Maestro_Unit $u
	 * @return boolean
	 */
	public function validate($u) {
		return $this->_unit_validate($u);
	}

	public function transaction() {
		$this->db->transaction();
	}

	public function commit() {
		$this->db->commit();
	}

	public function rollback() {
		$this->db->rollback();
	}

	/**
	 * заменяем в условиях конструкции типа #.field на реальные имена полей БД,
	 * если поле из списка столбцов таблицы
	 * 
	 * @param string $query
	 */
	protected function normalizeExpression($query) {
		$fields = $this->_defines->getFields();
		foreach ($fields as $alias => $finfo) {
			$search = '#' . $alias;
			$replace = $this->_defines->tableAlias . '.' . $finfo['truename'];
			$query = str_replace($search, $replace, $query);
		}
		return $query;
	}

	/**
	 * Выполняет запрос типа `select` и возвращает одну или несколько элементов
	 * mode:
	 *  - single    - возвращает одну запись
	 *  - keyvalue  - возвращает одномерный массив, где ключи - идентификаторы,
	 *                значения - названия (для списков форм интерфейса)
	 *
	 * @return mixed
	 */
	protected function _fetch() {
		// если не задан список полей - выбираем все
		$cols = $this->_builder->parts(Maestro_Mapper_Select::COLUMNS);
		if (!count($cols)) {
			$this->_builder->columns($this->_defines->getNames(), $this->_defines->tableAlias);
		}
		// иначе - проверяем наличие поля первичного ключа
		else if($this->_fetchOptions->require_pk) {			
			$_pk_found = false;
			foreach ($cols as $col) {
				if ($this->_defines->primaryAlias == $col) {
					$_pk_found = true;
				}
			}
			if (!$_pk_found) {
				$this->_builder->columns($this->_defines->primaryAlias, $this->_defines->tableAlias);
			}
		}


		$arguments = $this->_parameters;
		$query = $this->normalizeExpression($this->_builder->query());
		array_unshift($arguments, $query);
		if ($this->debug) {
			pre($arguments);
		}

		// выполняем запрос
		$rows = call_user_func_array(array($this->db, 'select'), $arguments);

		// в одиночном режиме возвращаем один элемент из первой строки результата
		if (self::FETCH_MODE_SINGLE == $this->_fetchOptions->mode) {
			// сбрасываем опции
			$this->options(NULL);

			return empty($rows) ? false : $this->_unit_create($rows[0]);
		}
		// формируем объекты из результата запроса. если заданы поля для дерева,
		// то вначале преобразовываем результаты запроса в лес
		else {
			if (empty($rows))
				return array();
			$result = array();
			$_is_tree = false;

			if (is_array($this->_tree_fields)) {
				$_is_tree = true;
				$_id = $this->_tree_fields[0];
				$_parent = $this->_tree_fields[1];
				$_expand = $this->_tree_fields[2];
				$rows = $this->_transformResultToForest($rows, $_id, $_parent);
				if ($_expand) {
					$expandedRows = array();
					$this->_expandForest2SortedArray($expandedRows, $rows, 0);
					$rows = $expandedRows;
					unset($expandedRows);
				}
			}

			// массив ключей-значений (названий)
			if (self::FETCH_MODE_KEYVAL == $this->_fetchOptions->mode) {
				if (NULL == $this->_fetchOptions->field) {
					$this->_fetchOptions->field = 'name';
				}
				$fieldinfo = $this->_defines->getField($this->_fetchOptions->field);
				if (!$fieldinfo) {
					throw new Maestro_Exception('_fetch(): Value field not found');
				}
				$name = $fieldinfo[Maestro_Mapper_Defines::F_TRUENAME];

				// префикс перед именем (для списков с подчиненными элементами)
				$_prefix = $this->_fetchOptions->prefix;
				$_offset = $this->_fetchOptions->offset;
				if (!$_offset) {
					$_offset = 0;
				}

				// если разрешено - добавим вначале пустое значение
				if (false !== $this->_fetchOptions->firstEmpty) {
					$result[$this->_fetchOptions->firstEmptyKey] = $this->_fetchOptions->firstEmpty;
				}

				foreach ($rows as $row) {
					$key = $row[$this->_defines->primaryName];
					$origin_key = $key;
					$value = $row[$name];
					if ($_is_tree && $_prefix) {
						$value = str_repeat($_prefix, $row['nodeLevel'] + $_offset) . $value;
					}
					//
					$result[$key] = $value;
				}
			}
			// массив объектов
			else {
				foreach ($rows as $row) {
					$u = $this->_unit_create($row);
					// если ключ массива (индек объекта) уже существует, создаем с помощью суффикса
					// уникальный ключ
					$key = $u->id;
					$i = 1;
					while (array_key_exists($key, $result)) {
						$key = $u->id . '_' . $i;
						$i++;
					}
					// итоговый массив объектов
					if(self::FETCH_MODE_ARRAYS == $this->_fetchOptions->mode) {
						$result[$key] = $u->toArray();
					} else {
						$result[$key] = $u;
					}
				}
			}
			// сбрасываем опции
			$this->options(NULL);
			return $result;
		}
	}

	/**
	 * Создает элемент из входящего массива данных, которые могут быть результатом запроса к БД
	 * или быть не заданы (во втором случае поля элемента инициализируются значениями по умолчанию)
	 * Идентификатором создаваемого элемента служит значение поля первичного ключа.
	 *
	 * @param array $data массив исходных данных
	 * @return Maestro_Unit object
	 */
	protected function _unit_create($data = false) {
		if (!is_array($data)) {
			$data = array($data);
		}

		$id = null;
		$values = array();

		//$pkinfo  = $this->_defines->getPrimaryInfo();
		//$pkfield = $pkinfo[Maestro_Mapper_Defines::F_TRUENAME];
		$pkfield = $this->_defines->primaryName;

		// получаем идентификатор и значения
		$names = $this->_defines->getNames();
		foreach ($names as $name) {
			$fieldinfo = $this->_defines->getField($name);
			$_from_field = $fieldinfo[Maestro_Mapper_Defines::F_TRUENAME];
			// первичный ключ
			if ($_from_field == $pkfield) {
				$id = isset($data[$_from_field]) ? $data[$_from_field] : null;
				continue;
			}
			// только поля, которые есть во входящих данных
			if (array_key_exists($_from_field, $data)) {
				$val = $data[$_from_field];

				//проверка по типу
				/* $method = '_check_type_'.$fieldinfo['type'];
				  if( method_exists( $this, $method ) )
				  {
				  $val = $this->$method( $field, $val );
				  } */
				$ftype = $fieldinfo[Maestro_Definitions::F_TYPE];
				if (Maestro_Definitions::TYPE_DATETIME == $ftype || Maestro_Definitions::TYPE_TIMESTAMP == $ftype) {
					if (ctype_digit($val)) {
						$val = date('d.m.Y H:i:s', $val); //'@'.$val;
					}
					if (!is_null($val)) {
						$val = new Maestro_DateTime($val);
					}
				}

				$values[$name] = $val;
			}
		}

		// пройдемся по результатам выборки в поисках "особенных полей" типа JOINED___
		foreach ($data as $dataField => $dataValue) {
			$field = strtolower($dataField);
			// если найдено поле присоединенной (join) таблицы
			if (preg_match("/^JOINED__(.+)/", $dataField, $matches)) {
				$field = strtolower($matches[1]);
				// только если поле не перекрывает существующие поля
				if (!array_key_exists($field, $values)) {
					$values[$field] = $dataValue;
				}
			}
		}


		//создаем элемент и инициируем полученными значениями
		$u = new Maestro_Unit($id, $values);

		if (isset($data['childNodes'])) {
			$childs = array();
			foreach ($data['childNodes'] as $nid => $ndata) {
				$nu = $this->_unit_create($ndata);
				$childs[$nu->id] = $nu;
			}
			$u->childNodes = $childs;
		}

		if (isset($data['nodeLevel'])) {
			$u->nodeLevel = $data['nodeLevel'];
		}

		if (isset($data['childsCount'])) {
			$u->childsCount = $data['childsCount'];
		}

		$this->_invoke('after_create', $u);

		if ($this->debug) {
			pre($u);
		}
		return $u;
	}

	/**
	 * Создает элемент из входящего массива данных, в котором имена полей
	 * совпадают с именами алиасов элемента.
	 * Реализовано для получения элемента из входящих массивов POST, для
	 * получения данных заполненой пользователем формы, например.
	 *
	 * @param integer $id идентификатор объекта
	 * @param array $data массив исходных данных
	 * @return Maestro_Unit object
	 */
	private function _array_to_unit($id, $data) {
		if (!is_array($data)) {
			$data = array($data);
		}

		$u = new Maestro_Unit($id);

		// получаем идентификатор и значения
		$names = $this->_defines->getNames();
		foreach ($names as $name) {
			$fieldinfo = $this->_defines->getField($name);
			// только поля, которые есть во входящих данных
			if (array_key_exists($name, $data)) {
				$value = $data[$name];
				// если значение пусто - берем значение "по умолчанию"
				// для данного алиаса
				if (is_null($value)) {
					$value = $fieldinfo[Maestro_Mapper_Defines::F_DEFAULT];
				}
				$u->$name = $value;
			}
			// пропускаем первичный ключ
			elseif ($fieldinfo[Maestro_Mapper_Defines::F_PRIMARY]) {
				continue;
			}
			// если поле не найдено, но является обязательным - добавляем и
			// присваиваем значение по умолчанию
			elseif (in_array('require', $fieldinfo[Maestro_Mapper_Defines::F_CHECK], true)) {
				$u->$name = $fieldinfo[Maestro_Mapper_Defines::F_DEFAULT];
			}
		}
		if ($this->debug) {
			pre($u);
		}
		return $u;
	}

	/**
	 *
	 * @param Maestro_Unit $u
	 */
	protected function _unit_validate($u) {
		if (!is_object($this->_validator)) {
			return false;
		}
		$values = $u->vars();
		$result_check = true;
		$names = $this->_defines->getNames();
		foreach ($names as $name) {
			$info = $this->_defines->getField($name);
			// пропускаем поля, не представленные в объекте
			if (!$u->present($name)) {
				continue;
			}

			$check_params = $info['check'];
			if (!$check_params) {
				continue;
			}
			// вызываем исключение для значения поля, не прошедшего валидацию
			if (!$this->_validator->check($u->$name, $check_params)) {
				throw new ValidateException(
						"Поле `<b>{$info['title']}</b>`: " .
						$this->_validator->last_message
				);
			} elseif ($this->debug) {
				echo 'Field <b>' . $name . '</b> is valid.<br/>';
			}
		}
		return true;
	}

	/**
	 * Возвращает новое уникальное значение идентификатора пользователя
	 *
	 * @return integer
	 */
	protected function _new_id() {
		return null;
	}

	/**
	 * Проверяет существование записи в таблице БД по первичному ключу
	 *
	 * @param Maestro_Unit $u
	 */
	protected function _sql_exists(Maestro_Unit $u) {
		if (is_null($u->id) || '' == $u->id) {
			return false;
		}

		$query = sprintf(
				"select %s from %s where %s=%s", 
				$this->_defines->primaryName, 
				$this->_defines->tableName, 
				$this->_defines->primaryName, 
				$this->_defines->getPlaceholder($this->_defines->primaryAlias)
		);
		$res = $this->db->selectCell($query, $u->id);
		if (!$res) {
			return false;
		}
		return true;
	}

	/**
	 * Удаляеть из БД запись
	 *
	 * @param integer $id
	 */
	protected function _sql_delete($id) {
		$_params[0] = sprintf("delete from %s where %s=?d", $this->_defines->tableName, $this->_defines->primaryName
		);
		$_params[1] = $id;

		if ($this->debug) {
			pre($_params);
		} else {
			call_user_func_array(array($this->db, 'query'), $_params);
		}
	}

	/**
	 * Добавляет в БД запись, соответствующую входящему элементу
	 *
	 * @param Maestro_Unit $u
	 */
	protected function _sql_insert(Maestro_Unit $u) {		
		$this->_unit_validate($u);

		$_ins_fields = array();
		$_ins_places = array();
		$_ins_params = array();

		$this->_affected_fields = array();

		// добавляем первичный ключ
		$_ins_fields[] = $this->_defines->primaryName;
		$_ins_places[] = $this->_defines->getPlaceholder($this->_defines->primaryAlias);
		$_ins_params[] = $u->id;

		foreach ($u->vars() as $field => $value) {
			$field_def = $this->_defines->getField($field);
			// пропускаем первичный ключ
			if ($field_def['primary']) {
				continue;
			}
			// пропускаем неизвестные поля
			if (is_null($field_def)) {
				continue;
			}
			// пропускаем поля из связных таблиц, занося их во вспомагательный список
			if ($this->_defines->tableName <> $field_def['ttable']) {
				$this->_affected_fields[] = $field;
				continue;
			}
			// описания поля
			//$fieldinfo = $field_def[$field];
			// пропускаем поля только для чтения
			if ($field_def['readonly']) {
				continue;
			}

			$_ins_fields[] = '"' . $field_def[Maestro_mapper_Defines::F_TRUENAME] . '"';
			$_ins_places[] = $this->_defines->getPlaceholder($field);
			$_ins_params[] = $this->validFieldValueOnStore($field, $value);
		}

		$query = "insert into {$this->_defines->tableName}";
		$query .= " (\n  " . implode(",\n  ", $_ins_fields);
		$query .= "\n) values ( " . implode(", ", $_ins_places) . " )";

		array_unshift($_ins_params, $query);

		if ($this->debug) {
			pre($_ins_params);
		} else {
			call_user_func_array(array($this->db, 'query'), $_ins_params);
		}
	}

	/**
	 * Обновляет поля записи в БД, соответствующей входящему элементу
	 *
	 * @param Maestro_Unit $u
	 */
	protected function _sql_update(Maestro_Unit $u) {
		$this->_unit_validate($u);

		$query = "update {$this->_defines->tableName} set\n  ";
		$updates = array();
		$values = array();

		$this->_affected_fields = array();

		foreach ($u->vars() as $field => $value) {
			// описания поля
			$field_def = $this->_defines->getField($field);
			// пропускаем неизвестные поля
			if (is_null($field_def)) {
				if ($this->debug)
					echo 'not in fields: ' . $field . '<br/>';
				continue;
			}
			// пропускаем поля из связных таблиц, занося их во вспомагательный список
			if ($this->_defines->tableName <> $field_def['ttable']) {
				if ($this->debug)
					echo 'not in main table: ' . $field . ' (' . $this->_defines->tableName . '<>' . $field_def['ttable'] . ')<br/>';
				$this->_affected_fields[] = $field;
				continue;
			}
			// пропускаем поля только для чтения и первичный ключ
			if ($field_def['readonly'] || $field_def['primary']) {
				continue;
			}

			$pholder = $this->_defines->getPlaceholder($field);
			$updates[] = '"' . $field_def[Maestro_Mapper_Defines::F_TRUENAME] . '" =' . $pholder;
			$values[] = $this->validFieldValueOnStore($field, $value);
		}

		// если нет обновляемых полей - выход
		if (!count($updates)) {
			if ($this->debug) {
				pre('nothing update in main table', 'darkred', 'white');
			}
			return;
		}


		$query .= implode(",\n  ", $updates) . "\nwhere ";

		$query .= $this->_defines->primaryName . ' = ' . $this->_defines->getPlaceholder($this->_defines->primaryAlias);
		$values[] = $u->id;
		array_unshift($values, $query);

		if ($this->debug) {
			pre($values);
		} else {
			call_user_func_array(array($this->db, 'query'), $values);
		}
	}

	/**
	 * Формирует sql-запрос типа `select` и выполяет его.
	 *
	 * @param array $opt
	 * @return mixed Результат выполнения sql-запроса
	 */
	protected function _sql_select($opt) {
		$arguments = array();

		// построитель запроса
		unset($this->_builder);
		$this->_builder = new Maestro_Mapper_Select($this->_defines);

		// from, таблица-источник
		$this->_builder->from(array($this->_defines->tableAlias => $this->_defines->tableName));

		// смещение
		if (!isset($opt['limit']))
			$opt['limit'] = 0;
		if (!isset($opt['offset']))
			$opt['offset'] = 0;
		$this->_builder->limit($opt['limit'], $opt['offset']);

		// заданный перечень столбцов
		$cols = isset($opt['columns']) ? $opt['columns'] : false;
		if ($cols) {
			if (!is_array($cols)) {
				$cols = array($cols);
			}
			$columns = array();
			foreach ($cols as $col) {
				$def = $this->_defines->getField($col);
				if (!is_null($def)) {
					$columns[$col] = $def[Maestro_Mapper_Defines::F_TRUENAME];
				}
			}
			// первичный ключ должен быть обязательно
			array_unshift($columns, array($this->_defines->primaryAlias => $this->_defines->primaryName));
			$cols = $columns;
		}
		// полный перечень столбцов
		else {
			$cols = array();
			foreach ($this->_defines->getFields() as $falias => $finfo) {
				$cols[$falias] = $finfo[Maestro_Mapper_Defines::F_TRUENAME];
			}
		}
		$this->_builder->columns($cols);

		// where, условие по первичному ключу
		if (isset($opt['where_pk'])) {
			$match = $opt['where_pk'];
			if (is_array($match)) {
				$this->_builder->where(sprintf("where %s in ( %s )", $this->_defines->primaryName, implode(',', $match)));
			} else {
				$condition = sprintf("%s.%s = %s", $this->_defines->tableAlias, $this->_defines->primaryName, $this->_defines->getPlaceholder($this->_defines->primaryAlias)
				);
				$this->_builder->where($condition);
				$arguments[] = $opt['where_pk'];
			}
		}
		// where, дополнительные условия
		elseif (isset($opt['where'])) {
			if (is_array($opt['where'])) {
				$this->_builder->where(call_user_func_array(array($this, '_condition'), $opt['where']));
			} else {
				$this->_builder->where($opt['where']);
			}
		}

		$query = $this->_builder->query();
		array_unshift($arguments, $query);
		if ($this->debug) {
			pre($arguments);
		}
		return call_user_func_array(array($this->db, 'select'), $arguments);
	}

	/**
	 *
	 * @return string
	 */
	protected function _condition(/**/) {
		$args = func_get_args();
		$tmpl = &$args[0];
		$tmpl = str_replace('%', '%%', $tmpl);
		$tmpl = str_replace('?', "'%s'", $tmpl);
		$tmpl = str_replace('?d', '%d', $tmpl);
		$tmpl = str_replace('?f', '%f', $tmpl);

		$args_out = array();
		array_push($args_out, $args[0]);
		array_shift($args);
		foreach ($args as $i => $v) {
			$v = htmlspecialchars($v);
			$v = addslashes($v);
			array_push($args_out, $v);
		}

		return call_user_func_array('sprintf', $args_out);
	}

	/**
	 *
	 */
	protected function _invoke(/* ... */) {
		$args = func_get_args();
		$num_args = count($args);

		if ($num_args <= 0) {
			return;
		}

		$method = array_shift($args);

		if (method_exists($this, $method)) {
			call_user_func_array(array($this, $method), $args);
		}
	}

	/**
	 * Обработки исключений.
	 * Перехватывает и выводит исключения валидации элемента.
	 *
	 * @param Exception $e
	 */
	protected function _exception(Exception $e) {
		if ($e instanceof ValidateException) {
			echo $e->getMessage();
		} else {
			throw new Maestro_Exception($e->getMessage());
		}
	}

	/**
	 *
	 * @return this
	 */
	public function __call($method, $args) {
		if ($this->_builder && method_exists($this->_builder, $method)) {
			call_user_func_array(array($this->_builder, $method), $args);
		}
		return $this;
	}

	/**
	 * Converts rowset to the forest.
	 *
	 * @param array $rows       Two-dimensional array of resulting rows.
	 * @param string $idName    Name of ID field.
	 * @param string $pidName   Name of PARENT_ID field.
	 * @return array            Transformed array (tree).
	 */
	function _transformResultToForest($rows, $idName, $pidName) {
		$children = array(); // children of each ID
		$ids = array();
		// Collect who are children of whom.
		foreach ($rows as $i => $r) {
			$row = & $rows[$i];
			$id = $row[$idName];
			if ($id === null) {
				// Rows without an ID are totally invalid and makes the result tree to
				// be empty (because PARENT_ID = null means "a root of the tree"). So
				// skip them totally.
				continue;
			}
			$pid = $row[$pidName];
			if ($id == $pid)
				$pid = null;
			$children[$pid][$id] = & $row;
			if (!isset($children[$id]))
				$children[$id] = array();
			$row['childNodes'] = & $children[$id];
			$ids[$id] = true;
		}
		// Root elements are elements with non-found PIDs.
		$forest = array();
		foreach ($rows as $i => $r) {
			$row = & $rows[$i];
			$id = $row[$idName];
			$pid = $row[$pidName];
			if ($pid == $id)
				$pid = null;
			if (!isset($ids[$pid])) {
				$forest[$row[$idName]] = & $row;
			}
			//unset($row[$idName]);
			//unset($row[$pidName]);
		}
		return $forest;
	}

	/**
	 * Convert forest to two-dimensional array and sort elements from owner to childs.
	 * Addding property `level` to all items
	 *
	 * @param array $res
	 * @param array $items forest array
	 * @param integer $level
	 * @return Maestro_Mapper_Abstract
	 */
	public function _expandForest2SortedArray(&$res, $items, $level) {
		foreach ($items as $id => $item) {
			$item['nodeLevel'] = $level;
			$childs = $item['childNodes'];
			$item['childsCount'] = count($childs);
			unset($item['childNodes']);
			$res[$id] = $item;

			if (count($childs)) {
				$this->_expandForest2SortedArray($res, $childs, $level + 1);
			}
		}
		return $this;
	}

	/**
	 * Инициализируем объек описания полей
	 */
	protected function _set_defines() {
		$this->_defines = new Maestro_Mapper_Defines($this->_initTable, $this->_initFields);
	}

	/**
	 * Инициализируем валидатор
	 */
	protected function _set_validator() {
		$this->_validator = new Maestro_Validator();
	}

	/**
	 * Инициализирует построитель запроса
	 */
	protected function _set_builder_select() {
		$this->_builder = new Maestro_Mapper_Select($this->_defines);
		$this->_builder->from(array($this->_defines->tableAlias => $this->_defines->tableName));
	}

	/**
	 * Перобразует значение поля при необходимости,
	 * например, преобразования даты в строку и обратно
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	protected function validFieldValueOnStore($name, $value) {
		$type = $this->_defines->getFieldProperty($name, Maestro_Definitions::F_TYPE);
		if (Maestro_Definitions::TYPE_TIMESTAMP == $type && $value) {
			if ($value instanceof Maestro_DateTime) {
				$value = $value->format('d.m.Y H:i:s');
			}
		} else if (Maestro_Definitions::TYPE_DATETIME == $type && $value) {
			if ($value instanceof Maestro_DateTime) {
				$value = $value->format('U'); // С версии 5.3 использовать как: $value->getTimestamp();
			} elseif (!is_numeric($value)) {
				$value = floor(strtotime($value));
			}
		} else if ($this->eyeDate && Maestro_Definitions::TYPE_DATE == $type && $value) {
			$value = floor(strtotime($value));
		} else if (Maestro_Definitions::TYPE_FLOAT == $type && $value) {
			$value = str_replace(',', '.', $value);
		}
		return $value;
	}

	/**
	 *
	 * @return string
	 */
	public function queryString() {
		return $this->_builder->assemble();
	}
}

?>
