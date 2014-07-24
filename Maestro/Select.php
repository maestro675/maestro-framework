<?php

/**
 * Класс для генерации SQL-запросов типа `SELECT`
 * Построенный SQL-запрос возвращается методом `query()`.
 *
 * @author Sergey Lysiansky, s.lysiansky@ucb-ua.com
 * @version    1.0
 */
class Maestro_Select {
	const LIMIT_COUNT = 'first';
	const LIMIT_OFFSET = 'skip';
	const DISTINCT = 'distinct';
	const COLUMNS = 'columns';
	const FROM = 'from';
	const WHERE = 'where';
	const GROUP = 'group';
	const HAVING = 'having';
	const ORDER = 'order';
	const START_WITH = 'startwith';
	const CONNECT_BY = 'connectby';

	const INNER_JOIN = 'inner join';
	const LEFT_JOIN = 'left join';
	const RIGHT_JOIN = 'join';
	const FULL_JOIN = 'full join';
	const RIGHT_REAL_JOIN = 'right join';

	const SQL_WILDCARD = '*';
	const SQL_SELECT = 'select';
	const SQL_UNION = 'union';
	const SQL_UNION_ALL = 'union all';
	const SQL_FROM = 'from';
	const SQL_WHERE = 'where';
	const SQL_DISTINCT = 'distinct';
	const SQL_GROUP_BY = 'group by';
	const SQL_ORDER_BY = 'order by';
	const SQL_HAVING = 'having';
	const SQL_START_WITH = 'start with';
	const SQL_CONN_PRIOR = 'connect by prior';
	const SQL_FOR_UPDATE = 'for update';
	const SQL_AND = 'and';
	const SQL_AS = 'as';
	const SQL_OR = 'or';
	const SQL_ON = 'on';
	const SQL_ASC = 'asc';
	const SQL_DESC = 'desc';

	/**
	 * Строка непосредственно SQL-запроса
	 *
	 * @var string
	 */
	protected $_query = false;
	/**
	 * Список параметров, передаваемых с условием WHERE
	 *
	 * @var array
	 */
	protected $_params = array();
	/**
	 *
	 * @var array
	 */
	protected $_aliases = array();
	/**
	 * The component parts of a SELECT statement.
	 * Initialized to the $_partsInit array in the constructor.
	 *
	 * @var array
	 */
	protected static $_partsInit = array(
		self::LIMIT_COUNT => null,
		self::LIMIT_OFFSET => null,
		self::DISTINCT => false,
		self::COLUMNS => array(),
		self::FROM => array(),
		self::WHERE => array(),
		self::GROUP => array(),
		self::HAVING => array(),
		self::ORDER => array(),
		self::CONNECT_BY => array(),
		self::START_WITH => array()
	);
	/**
	 *
	 * @var array
	 */
	protected $_parts;
	/**
	 * Specify legal join types.
	 *
	 * @var array
	 */
	protected static $_joinTypes = array(
		self::INNER_JOIN,
		self::LEFT_JOIN,
		self::RIGHT_JOIN,
		self::FULL_JOIN,
		self::RIGHT_REAL_JOIN
	);
	/**
	 * columns who being select from each table and join
	 *
	 * @param string $name описание
	 */
	protected $_columns = array();

	/**
	 * constructor
	 *
	 */
	public function __construct() {
		$this->reset(); //$this->_parts = self::$_partsInit;
	}

	/**
	 * Makes the query SELECT DISTINCT.
	 *
	 * @param bool $flag Whether or not the SELECT is DISTINCT (default true).
	 * @return Maestro_Select Этот объект.
	 */
	public function distinct($flag = true) {
		$this->_parts[self::DISTINCT] = (bool) $flag;
		return $this;
	}

	/**
	 * Описание функции
	 *
	 * @param string $count Ограничение на количество записей.
	 * @return Maestro_Select Этот объект.
	 */
	public function limit($first, $skip=0) {
		if (is_numeric($first) && $first)
			$this->_parts[self::LIMIT_COUNT] = $first;

		if (is_numeric($skip) && $skip)
			$this->_parts[self::LIMIT_OFFSET] = $skip;

		return $this;
	}

	/**
	 * Описание функции
	 *
	 * @param array|string $name Имя таблицы или ассоциативный массив `алиас`=>`имя`
	 * @param array|string $cols Столбцы для выборки `алиас`=>`имя`
	 * @return Maestro_Select Этот объект.
	 */
	public function from($name, $cols=null) {
		return $this->joinInner($name, null, $cols);
	}

	/**
	 * Описание функции
	 *
	 * @param string $name описание
	 * @return Maestro_Select Этот объект.
	 *
	 * Для стоббцов, являющиехся выражениями, используется след.вызов
	 *   columns("EXPR <column expressions>", '<alias>')
	 * Тогда в запросе это будет выглядеть как
	 *   <column expressions> AS <alias>
	 *
	 */
	public function columns($cols, $aliasName=null) {
		if ($aliasName === null && count($this->_parts[self::FROM])) {
			$aliasName = current(array_keys($this->_parts[self::FROM]));
		}
		$this->_columns($aliasName, $cols);
		return $this;
	}

	/**
	 * добавляет условие WHERE через AND
	 *
	 * @param string   $cond  условие WHERE.
	 * @param string   $value OPTIONAL A single value to quote into the condition.
	 * @param constant $type  OPTIONAL The type of the given value
	 * @return Maestro_Select Этот объект.
	 */
	public function where($cond, $value = null, $type = null) {
		$this->_parts[self::WHERE][] = $this->_where($cond, $value, $type, true);

		return $this;
	}

	/**
	 * добавляет условие WHERE через OR
	 *
	 * @param string   $cond  условие WHERE.
	 * @param string   $value OPTIONAL A single value to quote into the condition.
	 * @param constant $type  OPTIONAL The type of the given value
	 * @return Maestro_Select Этот объект.
	 */
	public function orwhere($cond, $value = null, $type = null) {
		$this->_parts[self::WHERE][] = $this->_where($cond, $value, $type, false);

		return $this;
	}

	/**
	 * Описание функции
	 *
	 * @param  array|string $name Имя таблицы.
	 * @param  string $cond Условие объединения.
	 * @param  array|string $cols Столбцы для выборки из текущей таблицы.
	 * @return Maestro_Select Этот объект.
	 */
	public function join($name, $cond, $cols=self::SQL_WILDCARD) {
		return $this->_join(self::RIGHT_JOIN, $name, $cond, $cols);
	}

	/**
	 * Описание функции
	 *
	 * @param  array|string $name Имя таблицы.
	 * @param  string $cond Условие объединения.
	 * @param  array|string $cols Столбцы для выборки из текущей таблицы.
	 * @return Maestro_Select Этот объект.
	 */
	public function joinLeft($name, $cond, $cols=self::SQL_WILDCARD) {
		return $this->_join(self::LEFT_JOIN, $name, $cond, $cols);
	}

	/**
	 * Описание функции
	 *
	 * @param  array|string $name Имя таблицы.
	 * @param  string $cond Условие объединения.
	 * @param  array|string $cols Столбцы для выборки из текущей таблицы.
	 * @return Maestro_Select Этот объект.
	 */
	public function joinRight($name, $cond, $cols=self::SQL_WILDCARD) {
		return $this->_join(self::RIGHT_REAL_JOIN, $name, $cond, $cols);
	}

	/**
	 * Описание функции
	 *
	 * @param  array|string $name Имя таблицы.
	 * @param  string $cond Условие объединения.
	 * @param  array|string $cols Столбцы для выборки из текущей таблицы.
	 * @return Maestro_Select Этот объект.
	 */
	public function joinInner($name, $cond, $cols=self::SQL_WILDCARD) {
		return $this->_join(self::INNER_JOIN, $name, $cond, $cols);
	}

	/**
	 * Описание функции
	 *
	 * @param array|string $spec Столбцы для группировки
	 * @return Maestro_Select Этот объект.
	 */
	public function group($spec) {
		if (!is_array($spec))
			$spec = array($spec);

		foreach ($spec as $val)
			$this->_parts[self::GROUP][] = $val;

		return $this;
	}

	/**
	 * Описание функции
	 *
	 * @param string $cond условие HAVING
	 * @return Maestro_Select Этот объект.
	 */
	public function having($cond) {
		if ($this->_parts[self::HAVING])
			$this->_parts[self::HAVING][] = self::SQL_AND . " {$cond}";
		else
			$this->_parts[self::HAVING][] = $cond;

		return $this;
	}

	/**
	 * Описание функции
	 *
	 * @param string $cond условие HAVING
	 * @return Maestro_Select Этот объект.
	 */
	public function orhaving($cond) {
		if ($this->_parts[self::HAVING])
			$this->_parts[self::HAVING][] = self::SQL_OR . " {$cond}";
		else
			$this->_parts[self::HAVING][] = $cond;

		return $this;
	}

	/**
	 * Описание функции
	 *
	 * @param string $spec список столбцов для сортировки ORDER BY
	 * @return Maestro_Select Этот объект.
	 */
	public function order($spec) {
		if (!is_array($spec))
			$spec = array($spec);

		foreach ($spec as $val) {
			if (empty($val))
				continue;
			$direction = self::SQL_ASC;
			if (preg_match('/(.*\W)(' . self::SQL_ASC . '|' . self::SQL_DESC . ')\b/si', $val, $m)) {
				$val = trim($m[1]);
				$direction = $m[2];
			}
			$this->_parts[self::ORDER][] = array($val, $direction);
		}

		return $this;
	}

	/**
	 * Для вложенных запросов к БД Oracle. Определяет, с каких элементов начинать строить дерево
	 *
	 * @param string $cond
	 * @return Maestro_Select Этот объект.
	 */
	public function startWith($cond) {
		$this->_parts[self::START_WITH] = $cond;
		return $this;
	}

	/**
	 * Для вложенных запросов к БД Oracle. Определяет связь полей "индекс - родитель"
	 *
	 * @param string $left_field
	 * @param string $right_field
	 * @return Maestro_Select Этот объект.
	 */
	public function connectByPrior($left_field, $right_field) {
		$this->_parts[self::CONNECT_BY] = array($left_field => $right_field);
		return $this;
	}

	/**
	 * Возвращает часть объекта запроса или целый объект
	 *
	 * @param string $part 
	 * @return mixed
	 */
	public function parts($part=null) {
		if (is_null($part))
			return $this->_parts;
		else
		if (array_key_exists($part, $this->_parts))
			return $this->_parts[$part];
	}

	/**
	 * Сбрасывает значения объекта запроса
	 *
	 * @return object Этот Db_Select объект
	 */
	public function reset($part=null) {
		if (is_null($part))
			$this->_parts = self::$_partsInit;
		else
		if (array_key_exists($part, $this->_parts))
			$this->_parts[$part] = self::$_partsInit[$part];

		return $this;
	}

	/**
	 * Генерирует и возвращает SQL-код
	 *
	 * @return string Сгенерированный SQL-код
	 */
	public function query($mode='all') {
		try {
			return $this->assemble();
		} catch (Exception $e) {
			pre($e->getMessage());
			throw new Exception('Assemble select error.');
		}
	}

	/**
	 * Генерирует SQL код и возвращает в виде массива,
	 * где первый элемент - запрос, последующие элементы - параметры
	 *
	 * @return array
	 */
	public function asArray() {
		return array_merge(array($this->query()), $this->_params);
	}

	/**
	 * Генерирует SQL код и возвращает в виде массива,
	 * где первый элемент - запрос, последующие элементы - параметры
	 *
	 * @return array
	 */
	public function toArray() {
		return $this->asArray();
	}

	/**
	 *
	 * @param mixed $value
	 * @return Maestro_Select
	 */
	public function param($value) {
		$this->_params[] = $value;
		return $this;
	}

	/**
	 * Описание функции
	 *
	 * @param string $name описание
	 */
	public function assemble() {
		$this->_query = self::SQL_SELECT;
		foreach (array_keys($this->_parts) as $part) {
			$method = '_render' . ucfirst($part);
			if (method_exists($this, $method)) {
				$this->$method();
			}
		}
		return $this->normalizeExpression($this->_query);
	}

	/**
	 * Описание функции
	 *
	 * @param string $name Тип объединения.
	 * @param string $name Имя таблицы.
	 * @param string $cond Условие WHERE.
	 * @param  array|string $cols Столбцы для выборки из текущей таблицы.
	 */
	protected function _join($type, $name, $cond, $cols) {
		if (!in_array($type, self::$_joinTypes)) {
			throw new Exception("Invalid join type '$type'");
		}

		$tableName = '';
		$aliasName = '';

		if (is_array($name)) {
			list($aliasName, $tableName) = each($name);
		} else
		if (preg_match('/^(.+)\s+AS\s+(.+)$/i', $name, $m)) {
			$tableName = $m[1];
			$aliasName = $m[2];
		} else {
			$tableName = $name;
			$aliasName = $this->_uniqueAlias($tableName);
		}

		if (!empty($aliasName)) {
			if (array_key_exists($aliasName, $this->_parts[self::FROM])) {
				throw new Exception("You cannot define a alias name '$aliasName' more than once");
			}
			$this->_parts[self::FROM][$aliasName] = array(
				'joinType' => $type,
				'tableName' => $tableName,
				'joinCondition' => $cond
			);
		}
		$this->_columns($aliasName, $cols);
		return $this;
	}

	/**
	 * Описание функции
	 *
	 * @param string $aliasName Алиас, применяемый к столбцам
	 * @param array|string $cols Массив столбцов
	 */
	protected function _columns($aliasName, $cols) {
		if (!is_array($cols))
			$cols = array($cols);

		if ($aliasName == null)
			$aliasName = '';

		foreach (array_filter($cols) as $alias => $col) {
			$currentAliasName = $aliasName;
			if (preg_match('/^EXPR(.+)$/i', $col, $m)) {
				//$alias = is_numeric($alias) ? $aliasName : $alias;
				$alias = is_numeric($alias) ? null : $alias;
				$currentAliasName = null;
				$col = trim($m[1]);
			} else
			if (is_string($col)) {
				// Check for a column matching "<column> AS <alias>" and extract the alias name
				if (preg_match('/^(.+)\s+AS\s+(.+)$/i', $col, $m)) {
					$col = $m[1];
					$alias = $m[2];
				}
				if (preg_match('/(.+)\.(.+)/', $col, $m)) {
					$currentAliasName = $m[1];
					$col = $m[2];
				}
			}
			$this->_parts[self::COLUMNS][] = array(
				'talias' => $currentAliasName,
				'name' => $col,
				'alias' => is_string($alias) ? $alias : null
			);
		}
	}

	/**
	 * Внутренняя фукнция для создания условия WHERE
	 *
	 * @param string   $condition
	 * @param string   $value  optional
	 * @param string   $type   optional
	 * @param boolean  $bool  true = AND, false = OR
	 * @return string  clause
	 */
	protected function _where($condition, $value = null, $type = null, $bool = true) {
		if ($value !== null) {
			$this->_params[] = $value;
			//$condition = $this->_adapter->quoteInto($condition, $value, $type);
		}

		$cond = "";
		if ($this->_parts[self::WHERE]) {
			if ($bool === true) {
				$cond = "\n  " . self::SQL_AND . ' ';
			} else {
				$cond = "\n   " . self::SQL_OR . ' ';
			}
		}

		$this->_parts[self::WHERE][] = $cond . "($condition)";
	}

	/**
	 * Описание функции
	 *
	 * @param string $name описание
	 */
	protected function _renderDistinct() {
		if ($this->_parts[self::DISTINCT])
			$this->_query .= "\n  " . self::SQL_DISTINCT;
	}

	/**
	 * Описание функции
	 *
	 * @param string $name описание
	 */
	protected function _renderFirst() {
		$n = $this->_parts[self::LIMIT_COUNT];
		if (!empty($n) && is_numeric($n))
			$this->_query .= " " . self::LIMIT_COUNT . " " . $n;
	}

	/**
	 * Описание функции
	 *
	 * @param string $name описание
	 */
	protected function _renderSkip() {
		$n = $this->_parts[self::LIMIT_OFFSET];
		if (!empty($n) && is_numeric($n))
			$this->_query .= " " . self::LIMIT_OFFSET . " " . $n;
	}

	/**
	 * Описание функции
	 *
	 * @param string $name описание
	 */
	protected function _renderFrom() {
		$from = '';
		foreach ($this->_parts[self::FROM] as $aliasTable => $table) {
			if (empty($from)) {
				$from .= PHP_EOL . self::SQL_FROM . ' ' . $table['tableName'] . '  ' . $aliasTable;
				//$from .= PHP_EOL . self::SQL_FROM . ' ' . $table['tableName'] . ' ' . self::SQL_AS . ' ' . $aliasTable;
			} else {
				$from .= PHP_EOL . $table['joinType'] . ' ' . $table['tableName'] . ' ' . $aliasTable;
				//$from .= PHP_EOL . $table['joinType'] . ' ' . $table['tableName'] . ' ' . self::SQL_AS . ' ' . $aliasTable;
			}

			if (!empty($from) && !empty($table['joinCondition'])) {
				$from .= " " . self::SQL_ON . " " . $table['joinCondition'];
			}
		}
		$this->_query .= $from;
	}

	/**
	 * Описание функции
	 *
	 * @param string $name описание
	 */
	protected function _renderColumns() {
		$this->_query .= PHP_EOL.' ';
		$i = 0;

		foreach ($this->_parts[self::COLUMNS] as $_colid => $coldata) {
			$delim = ($i) ? ', '.PHP_EOL.'  ' : '';

			if (is_null($coldata['talias'])) {
				$this->_query .= $delim . $coldata['name'];
			} else {
				$this->_query .= $delim . $coldata['talias'] . '.' . $coldata['name'];
			}

			if (!is_null($coldata['alias'])) {
				$this->_query .= " " . self::SQL_AS . " \"" . strtoupper($coldata['alias']) . "\"";
			}

			$i++;
		}
	}

	/**
	 * Описание функции
	 *
	 * @param string $name описание
	 */
	protected function _renderWhere() {
		if ($this->_parts[self::FROM] && $this->_parts[self::WHERE]) {
			$this->_query .= "\n" . self::SQL_WHERE . " " . implode(" ", $this->_parts[self::WHERE]);
		}
	}

	/**
	 * Generate a unique alias name
	 *
	 * @param string|array $name A qualified identifier.
	 * @return string A unique alias name.
	 */
	protected function _uniqueAlias($name) {
		if (is_array($name)) {
			$c = end($name);
		} else {
			// Extract just the last name of a qualified table name
			$dot = strrpos($name, '.');
			$c = ($dot === false) ? $name : substr($name, $dot + 1);
		}
		for ($i = 2; array_key_exists($c, $this->_parts[self::FROM]); ++$i) {
			$c = $name . '_' . (string) $i;
		}
		return $c;
	}

	/**
	 * Описание функции
	 *
	 * @param string $name описание
	 */
	protected function _renderGroup() {
		if ($this->_parts[self::FROM] && $this->_parts[self::GROUP]) {
			$this->_query .= "\n" . self::SQL_GROUP_BY . "\n  " . implode(",\n  ", $this->_parts[self::GROUP]);
		}
	}

	/**
	 * Описание функции
	 *
	 */
	protected function _renderHaving() {
		if ($this->_parts[self::FROM] && $this->_parts[self::HAVING]) {
			$this->_query .= "\n" . self::SQL_HAVING . "\n  " . implode(",\n  ", $this->_parts[self::HAVING]);
		}
	}

	/**
	 * Описание функции
	 *
	 */
	protected function _renderStartwith() {
		if ($this->_parts[self::START_WITH]) {
			$this->_query .= "\n" . self::SQL_START_WITH . ' ' . $this->_parts[self::START_WITH];
		}
	}

	/**
	 * Описание функции
	 *
	 */
	protected function _renderConnectby() {
		if ($this->_parts[self::CONNECT_BY]) {
			foreach($this->_parts[self::CONNECT_BY] as $lf => $rf) {
				$this->_query .= "\n" . self::SQL_CONN_PRIOR . ' ' . $lf . ' = ' . $rf;
			}
		}
	}

	/**
	 * Описание функции
	 *
	 * @param string $name описание
	 */
	protected function _renderOrder() {
		if ($this->_parts[self::FROM] && $this->_parts[self::ORDER]) {
			$order = array();
			foreach ($this->_parts[self::ORDER] as $term) {
				if (is_array($term))
					$order[] = $term[0] . ' ' . $term[1];
				else
					$order[] = $term;
			}
			$this->_query .= "\n" . self::SQL_ORDER_BY . "\n  " . implode(",\n  ", $order);
		}
	}

	/**
	 * Встроенный magic метод
	 *
	 * @return string Сгенерированный SQL-код
	 */
	public function __toString() {
		try {
			$this->assemble();
			return $this->_query;
		} catch (Exception $e) {
			pre($e->getMessage());
			throw new Exception('Assemble select error.');
		}
	}

	/**
	 * заменяем в условиях конструкции типа #.field на реальные имена полей БД,
	 * если поле из списка столбцов таблицы
	 */
	protected function normalizeExpression($str) {
		foreach ($this->_aliases as $alias => $replace) {
			$search = '#' . $alias;
			//$replace = $info['talias'].'.'.$info['name'];

			$str = str_replace($search, $replace, $str);
		}
		return $str;
	}

}
