<?php

/**
 * Description of Unit
 *
 * @author maestro
 */
class Maestro_Unit {

	/**
	 * ассоциативный массив значений типа `имя`=>`значение`
	 *
	 * @var array
	 */
	protected $_vars;

	/**
	 * Флаг, запрещающий изменение массива $_vars  объекта
	 *
	 * @var boolean
	 */
	private $_readonly = false;

	/**
	 * уникальный идентификатор экземпляра класса.
	 * инициализируется в конструкторе и в дальнейшем доступен только для чтения
	 * обращение извне класса производится по имени id,
	 * например:
	 *   $t = new Item(256);
	 * 	 echo $t->id; // 256
	 *
	 * @var string|integer
	 */
	protected $_identificator;
	
	/**
	 * Список полей измененных или добавленных после последней операции "merge"
	 * 
	 * @var array
	 */
	protected $_affected_fields = array();
	
	/**
	 * Если установлено, то при инициализации имена полей приводятся к нижнему регистру
	 * 
	 * @var boolean
	 */
	protected $_key_lower = false;

	/**
	 * конструктор класса, инициализация массивом значений
	 * имена ключей переводятся в нижний регистр
	 *
	 * @param integer $id
	 * @param array $data
	 * @param bool $readonly
	 * @param bool $keylowercase приводить имена полей к нижнему регистру
	 */
	public function __construct($id, $data = null, $readonly = false, $keylowercase = false) {
		$this->_key_lower = $keylowercase;
		$this->_vars = array();
		$this->_identificator = $id;

		$this->merge($data);
		$this->_readonly = $readonly;
	}
	
	/**
	 * 
	 */
	public function setReadonly() {
		$this->_readonly = true;
	}
	
	/**
	 *
	 * @return boolean
	 */
	public function isReadonly() {
		return $this->_readonly;
	}

	/**
	 *
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function get($key, $default = null) {
		return isset($this->_vars[$key]) ? $this->_vars[$key] : $default;
	}

	/**
	 * Инициализирует объект массивом значений `values`, перекрывая текущие значения
	 *
	 * @param array $values
	 */
	public function merge($values = null) {
		if (!is_array($values)) {
			return;
		}
		
		$this->_affected_fields = array();
		foreach ($values as $name => $value) {
			if($this->_key_lower) {
				$name = strtolower($name);
			}
			// если поле новое или устанавливается новое значение - отметим
			if('id' == $name) {
				if($this->_identificator != $value) {
					$this->_affected_fields[$name] = true;
					$this->_identificator = $value;
				}
			} else if(!isset($this->_vars[$name]) || ($this->_vars[$name] !== $value)) {
				$this->_affected_fields[$name] = true;
				$this->_vars[$name] = $value;
			}
		}
	}

	/**
	 *
	 * @param integer|string $id
	 */
	public function setid($id) {
		$this->_identificator = $id;
	}

	/**
	 *
	 * @return array
	 */
	public function vars() {
		return $this->toArray();
	}

	/**
	 * Возвращает значения полей в виде ассоциативного массива
	 * 
	 * @return array
	 */
	public function toArray() {
		return array_merge(array('id' => $this->_identificator) + $this->_vars);
	}
	
	/**
	 * Возвращает значения измененных полей в виде ассоциативного массива
	 * 
	 * @return array
	 */
	public function toArrayAffected() {
		$res = array();
		foreach($this->_affected_fields as $name => $z) {
			$res[$name] = $this->$name;
		}
		return $res;
	}

	/**
	 * Проверяет существование значения по имени
	 *
	 * @param string $field
	 * @return bool
	 */
	public function present($field) {
		return isset($this->_vars[$field]);
	}

	/**
	 * Удаляет из объекта ключ, возвращает его значение.
	 * 
	 * 
	 * @param string $name
	 */
	public function remove($name) {
		$result = isset($this->_vars[$name]) ? $this->_vars[$name] : null;
		unset($this->_vars[$name]);
		return $result;
	}

	/**
	 * магический метод установки значения поля
	 */
	public function __set($name, $value) {
		if($this->_key_lower) {
			$name = strtolower($name);
		}
		if ($this->_readonly) {
			throw new Exception('Unit is locked: [' . $this->_identificator . ']');
		} else {
			$this->_vars[$name] = $value;
		}
	}

	/**
	 * магический метод получения значения поля
	 */
	public function __get($name) {
		if ($name == 'id') {
			return $this->_identificator;
		} else
		if (isset($this->_vars[$name])) {
			return $this->_vars[$name];
		} else {
			return null;
		}
	}

	/**
	 *
	 * @param <type> $m
	 * @param <type> $a
	 */
	public function __call($name, $args) {
		if (preg_match('/^(get|set)(\w+)/', strtolower($name), $match)) {
			$var = $match[2];
			if (isset($this->_vars[$var])) {
				if ('get' == $match[1]) {
					return $this->_vars[$var];
				} else {
					if($this->_key_lower) {
						$var = strtolower($var);
					}
					$this->_vars[$var] = $args[0];
				}
			}
		}
	}

}

?>
