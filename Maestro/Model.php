<?php

/**
 * Базовый класс модели
 *
 * @author maestro
 */
class Maestro_Model implements ArrayAccess {

	/**
	 * Атрибуты и параметры модели
	 *
	 * @var array
	 */
	protected $_attributes = array();

	/**
	 * Если включен параметр - запрещаются изменения в атрибутах модели
	 *
	 * @var boolean
	 */
	protected $_locked = false;

	/**
	 * Конструктор
	 *
	 * @param array $options
	 */
	public function __construct($options = null) {
		// опции
		if (is_array($options)) {
			$this->addAttributes($options);
		}

		// расширения
		$this->init();
	}

	/**
	 * Расширения для наследуемых классов
	 *
	 * @return void
	 */
	protected function init() {
		
	}

	/**
	 *
	 * @param boolean $locked
	 * @return Maestro_Model
	 */
	public function setLocked($locked = true) {
		$this->locked = ($locked);
		return $this;
	}

	/**
	 *
	 * @return boolean
	 */
	public function isLocked() {
		return $this->_locked;
	}

	/**
	 * Устанавливает атрибут формы
	 *
	 * @param  string $key
	 * @param  mixed $value
	 * @return Maestro_Model
	 */
	public function setAttribute($key, $value) {
		if ($this->isLocked())
			return $this;

		$key = (string) $key;
		$this->_attributes[$key] = $value;
		return $this;
	}

	/**
	 * Устанавливает атрибут формы
	 *
	 * @param string $cat
	 * @param  string $key
	 * @param  mixed $value
	 * @return Maestro_Model
	 */
	public function setAttributeKey($cat, $key, $value) {
		if ($this->isLocked())
			return $this;

		$cat = (string) $cat;
		$key = (string) $key;
		$this->_attributes[$cat][$key] = $value;
		return $this;
	}

	/**
	 * Добавляем массив атрибутов формы
	 *
	 * @param array $attributes
	 * @return Maestro_Model
	 */
	public function addAttributes($attributes) {
		if (is_array($attributes)) {
			foreach ($attributes as $key => $value) {
				$this->setAttribute($key, $value);
			}
		}
		return $this;
	}

	/**
	 * Устанавливает массив атрибутов формы.
	 * Предыдущие данные удаляются.
	 *
	 * @param array $attributes
	 * @return Maestro_Model
	 */
	public function setAttributes($attributes) {
		$this->clearAttributes();
		return $this->addAttributes($attributes);
	}

	/**
	 * Возвращает значение атрибута
	 *
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function getAttribute($key, $default = null) {
		$key = (string) $key;
		if (!isset($this->_attributes[$key])) {
			return $default;
		}
		return $this->_attributes[$key];
	}

	/**
	 *
	 * @param string $cat
	 * @param string $key
	 * @param mixed $default
	 * @return mixed
	 */
	public function getAttributeKey($cat, $key, $default = null) {
		$cat = (string) $cat;
		$key = (string) $key;
		if (!isset($this->_attributes[$cat][$key])) {
			return $default;
		}
		return $this->_attributes[$cat][$key];
	}

	/**
	 *
	 * @param string $cat
	 * @param string $key1
	 * @param string $key2
	 * @param mixed $default
	 * @return mixed
	 */
	public function getAttributeKey2($cat, $key1, $key2, $default = null) {
		$cat = (string) $cat;
		$key1 = (string) $key1;
		$key2 = (string) $key2;
		if (!isset($this->_attributes[$cat][$key1][$key2])) {
			return $default;
		}
		return $this->_attributes[$cat][$key1][$key2];
	}

	/**
	 *
	 * @param string $cat
	 * @param string $key1
	 * @param string $key2
	 * @param string $key3
	 * @param mixed $default
	 * @return mixed
	 */
	public function getAttributeKey3($cat, $key1, $key2, $key3, $default = null) {
		$cat = (string) $cat;
		$key1 = (string) $key1;
		$key2 = (string) $key2;
		$key3 = (string) $key3;
		if (!isset($this->_attributes[$cat][$key1][$key2][$key3])) {
			return $default;
		}
		return $this->_attributes[$cat][$key1][$key2][$key3];
	}

	/**
	 * Возвращает все атрибуты и параметры
	 *
	 * return array
	 */
	public function getAttributes() {
		return $this->_attributes;
	}

	/**
	 * Удаляет атрибут
	 *
	 * @param string $key
	 * @return bool
	 */
	public function removeAttribute($key) {
		if ($this->isLocked())
			return $this;

		if (isset($this->_attributes[$key])) {
			unset($this->_attributes[$key]);
			return true;
		}
		return false;
	}

	/**
	 * Удааляет все атрибуты формы
	 *
	 * @return Maestro_Model
	 */
	public function clearAttributes() {
		if ($this->isLocked())
			return $this;

		$this->_attributes = array();
		return $this;
	}

	function offsetExists($offset) {
		return isset($this->_attributes[$offset]);
	}

	function offsetGet($offset) {
		return $this->getAttribute($offset);
	}

	function offsetSet($offset, $value) {
		$this->setAttribute($offset, $value);
	}

	function offsetUnset($offset) {
		$this->removeAttribute($offset);
	}

}

?>
