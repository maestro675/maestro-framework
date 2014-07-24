<?php
/**
 * Класс для описания произвольной структуры полей.
 * Используется как базовый класс для описания полей класса объектов.
 *
 * @author maestro
 */
class Maestro_Definitions implements Iterator, Countable
{
	/**#@+
	 *  типы свойств полей
	 */
	const F_TYPE     = 'type';      // тип поля
	const F_DEFAULT  = 'default';   // значение по умолчанию
	const F_READONLY = 'readonly';  // только для чтения
	const F_CHECK    = 'check';     // массив параметров валидности значений
	const F_TITLE    = 'title';     // описание поля (текстовая метка)
	const F_REMARK   = 'remark';    // дополнительный текст к описанию поля
	const F_ERRORS   = 'errors';    // ошибки валидации
	const F_HELPER   = 'helper';    // дополнительный блок
	const F_ACTIONS  = 'actions';   // список действий, для которых используется поле (для формы)
	const F_FILTERS  = 'filters';   // список фильтров, применяемых к значению поля
	const F_VALIDATORS  = 'validators';    // массив валидаторов (ZF)
	/**#@-*/

	/**#@+
	 * типы полей
	 */
	const TYPE_INTEGER  = 'integer';  // целое число
	const TYPE_STRING   = 'string';   // строка
	const TYPE_FLOAT    = 'float';    // дробное число
	const TYPE_DATE     = 'date';     // дата (уже не строка)
	const TYPE_DATETIME = 'datetime'; // объект класса DateTime
	const TYPE_TIMESTAMP = 'timestamp'; // объект класса DateTime, для полей БД типа timestamp
	const TYPE_LINK     = 'link';     // ссылка на объект
	const TYPE_ARRAY    = 'array';    // массив
	const TYPE_MIXED    = 'mixed';    // произвольный тип
	const TYPE_HIDDEN   = 'hidden';   // используется только для форм редактирования - скрытое поле
	const TYPE_CHECK    = 'checkbox'; // используется только для форм редактирования
	const TYPE_RADIO    = 'radio';    // используется только для форм редактирования
	/**#@-*/

	/**
	 * Массив, используемый для инициализации описания поля
	 *
	 * @var array
	 */
	protected $_init_field = array (
		self::F_TYPE     => self::TYPE_STRING,
		self::F_DEFAULT  => null,
		self::F_READONLY => false,
		self::F_TITLE    => '',
		self::F_REMARK   => '',
		self::F_CHECK    => array(),
		self::F_ERRORS   => false,
		self::F_HELPER   => false,
        self::F_ACTIONS  => false,
		self::F_FILTERS  => array(),
		self::F_VALIDATORS  => array()
	);

	/**
	 * описания полей
	 *
	 * @var array
	 */
	protected $_fields = array();

	/**
	 * Количество полей
	 *
	 * @var integer
	 */
	protected $_count;

	/**
	 * Индекс итерации
	 *
	 * @var integer
	 */
	protected $_index;

	/**
	 * Конструктор класса
	 *
	 * @param array $fields массив описаний полей
	 */
    public function __construct( $fields )
	{
		$this->setFields( $fields );
	}

	/**
	 * Добавляет новое поле
	 *
	 * @param  string $name
	 * @param  array $properties
	 * @return Maestro_Definitions
	 */
	public function addField($name, $properties)
	{
		if(array_key_exists($name, $this->_fields)) {
			pre($this->_fields, 'yellow');
			throw new Maestro_Exception('Field exists!');
		}
		$field = $this->_init_field;
		// обновляем входящими параметрами
		foreach( $field as $fprop => $fpropvalue )
		{
			if( isset( $properties[$fprop] ) )
			{
				$field[$fprop] = $properties[$fprop];
			}
		}
		// если заголовок поля не задан - берем из имени поля
		if( empty($field[self::F_TITLE] ) )
		{
			$field[self::F_TITLE] = $name;
		}
		$this->_fields[$name] = $field;
		$this->_count = count($this->_fields);
		return $this;
	}
	
	/**
	 * Дополняет массив свойств полей 
	 * 
	 * 
	 * @param array $props
	 * @return Maestro_Definitions
	 */
	public function mergeFields($props) {
		if(!is_array($props)) {
			return;
		}
		//$this->_fields = array_merge_recursive($this->_fields, $props);
		foreach($props as $name => $data) {
			if(!array_key_exists($name, $this->_fields)) {
				$this->_fields[$name] = $this->_init_field;
			}
			if(is_array($data)) {
				$this->_fields[$name] = array_replace_recursive($this->_fields[$name], $data);
			}
			if(empty($this->_fields[$name][self::F_TITLE])){
				$this->_fields[$name][self::F_TITLE] = $name;
			}
		}
		$this->_count = count($this->_fields);
		return $this;
	}

	/**
	 * Проверяет существование поля в списке
	 *
	 * @param string $name
	 * @return bool
	 */
	public function issetField($name)
	{
		return array_key_exists($name, $this->_fields);
	}

	/**
	 * Добавляем список описаний полей из входящего массива.
	 * Если указан фильтр полей - добавляем поля только из списка фильтра,
	 * причем в порядке, указанном этим параметром
	 *
	 * @param  array $fields
	 * @param  array $filter
	 * @return Maestro_Definition
	 */
	public function addFields(array $fields, $filter = null)
	{
		if(is_null($filter))
		{
			foreach($fields as $name => $properties)
			{
				$this->addField($name, $properties);
			}
			return $this;
		}

		if(!is_array($filter))
		{
			$filter = array($filter);
		}

		foreach($filter as $name)
		{
			if(array_key_exists($name, $fields))
			{
				$this->addField($name, $fields[$name]);
			}
		}

		return $this;
	}

	/**
	 * Инициализируем список описаний полей входящим массивом.
	 * Предыдущие данные стираем.
	 * Если указан фильтр полей - добавляем поля только из списка фильтра,
	 * причем в порядке, указанном этим параметром
	 *
	 * @param  array $fields
	 * @param  array $filter
	 * @return Maestro_Definition
	 */
	public function setFields(array $fields, $filter = null)
	{
		$this->clearFields();
		return $this->addFields($fields, $filter);
	}

    /**
     *
     * @param string $name
     * @return Maestro_Definition
     */
    public function deleteField($name)
    {
        unset($this->_fields[$name]);
        return $this;
    }

	/**
	 * Очищает список полей
	 * @return Maestro_Definition
	 */
	public function clearFields()
	{
		$this->_fields = array();
		$this->_count = 0;
		return $this;
	}

	/**
	 * Возвращает значение указанного свойства для заданного поля
	 *
	 * @param  string $name
	 * @param  string $property
	 * @return mixed
	 */
	public function getFieldProperty($name, $property)
	{
		if(!$this->issetField($name))
		{
			return null;
		}
		if(!isset($this->_fields[$name][$property]))
		{
			//throw new Maestro_Exception(sprintf('Неизвестное свойство `%s` поля `%s`', $property, $name));
			return null;
		}
		return $this->_fields[$name][$property];
	}

	/**
	 * Перезаписывает свойство $property существующего поля $alias,
	 * и устанавливает его значение = $value
	 *
	 * @param string $name
	 * @param string $property
	 * @param mixed  $value
	 */
	public function setFieldProperty( $name, $property, $value )
	{
		if(array_key_exists( $name, $this->_fields) )// && !array_key_exists( $property, $this->_init_field))
		{
			if(self::F_CHECK == $property)
			{
				if(is_array($value))
				{
					$this->_fields[$name][$property] = array_merge($this->_fields[$name][$property], $value);
				}
			}
			else
			if(is_array($value) && self::F_ERRORS == $property)
			{
				if(is_array($this->_fields[$name][$property])) {
					$this->_fields[$name][$property] = array_merge($this->_fields[$name][$property], $value);
				} else {
					$this->_fields[$name][$property] = $value;
				}
			}
			else
			{
				$this->_fields[$name][$property] = $value;
			}
		}
		return $this;
	}

	/**
	 * Возвращает параметры поля по имени
	 *
	 * @param string $name
	 * @return array
	 */
	public function getField($name)
	{
		if(array_key_exists($name, $this->_fields))
		{
			return $this->_fields[$name];
		}
		return null;
	}

	/**
	 * Возвращает описание всех полей
	 *
	 * @return array
	 */
	public function getFields()
	{
		return $this->_fields;
	}

	/**
	 * Возвращает массив имен полей
	 */
	public function getNames()
	{
		return array_keys($this->_fields);
	}

	/**
	 * Ищет поле по значению $value его свойства $property.
	 * Возвращает имя поля.
	 *
	 * @param string $property
	 * @param string $value
	 * @return string
	 */
	public function findBy($property, $value)
	{
		// если свойство не определено - выход
		if(!array_key_exists( $property, $this->_init_field))
		{
			return false;
		}
		// поиск
		foreach($this->_fields as $alias => $info)
		{
			if(array_key_exists($property, $info))
			{
				if($value == $info[$property])
				{
					return $alias;
				}
			}
		}

		return false;
	}

	/**
	 * Defined by Iterafor interface
	 *
	 * @return mixed
	 */
	public function count()
	{
		return $this->_count;
	}

	/**
	 * Defined by Iterafor interface
	 *
	 * @return mixed
	 */
	public function rewind()
	{
		reset($this->_fields);
		$this->_index = 0;
	}

	/**
	 * Defined by Iterafor interface
	 *
	 * @return mixed
	 */
	public function current()
	{
		return current($this->_fields);
	}

	/**
	 * Defined by Iterafor interface
	 *
	 * @return mixed
	 */
	public function key()
	{
		return key($this->_fields);
	}

	/**
	 * Defined by Iterafor interface
	 *
	 * @return mixed
	 */
	public function next()
	{
		next($this->_fields);
		$this->_index++;
	}

	/**
	 * Defined by Iterafor interface
	 *
	 * @return mixed
	 */
	public function valid()
	{
		return $this->_index < $this->_count;
	}

}
?>
