<?php
/**
 * Класс для описания полей таблицы элементов
 *
 * @author maestro
 */
class Maestro_Mapper_Defines extends Maestro_Definitions
{
	/**
	 * дополнительные типы свойств полей
	 */
	const F_TRUENAME = 'truename';  // реальное имя поля таблицы БД
	const F_PRIMARY  = 'primary';   // признак первичного ключа
	const F_FLAGS    = 'flags';     // дополнительные флаги
	// используется в наследуемом классе описания классифицированных объектов
	const F_TABLE    = 'ttable';    // имя таблицы, содержащей поле
	const F_INDEX    = 'index';     // числовой индентификатор поля из PROPFIELDS
	//const F_LINKID   = 'linkid';    // идентификатор типа объекта, на который ссылается данное поле

	/**
	 * Имя поля, являющегося первичным ключом
	 * Определяется при выполении метода `setFields`
	 *
	 * @var string
	 */
	public $primaryAlias = 'id';
	public $primaryName;

	/**
	 * название основной таблицы
	 *
	 * @var string
	 */
	public $tableName;
	public $tableAlias = 'M';

	/**
	 * описания полей
	 *
	 * @var array
	 */
	//public $fields = array();

	/**
	 * Списки полей присоединяемых объектов, сответствующие полям (типа `link`),
	 * для которых разрешено линкование объектов.
	 * Структура [alias]=>[linked_columns]
	 *
	 * @var array
	 */
	public $linked = array();

	/**
	 * Список полей для поиска алиаса поля по имени поля из таблицы
	 * Ключ: имя поля таблицы БД self::F_TRUENAME
	 * Значение: имя поля
	 *
	 * @var array
	 */
	protected $_trueNames = array();

	/**
	 * Конструктор класса
	 *
	 * @param array|string $table
	 * @param array $fields массив описаний полей
	 */
	public function __construct( $table, $fields )
	{
		$this->_init_field[self::F_TRUENAME] = '';
		$this->_init_field[self::F_PRIMARY] = false;
		$this->_init_field[self::F_FLAGS] = 0;
		$this->_init_field[self::F_TABLE] = '';
		$this->_init_field[self::F_INDEX] = -1;

		if( is_array( $table ) )
		{
			list( $this->tableAlias, $this->tableName ) = each( $table );
		}
		else
		{
			$this->tableAlias = 'M';
			$this->tableName = $table;
		}
		parent::__construct($fields);
	}

	/**
	 * Инициализируем список описаний полей входящим массивом
	 *
	 * @param array $data
	 * @param  array $filter
	 */
	public function setFields(array $data, $filter = null)
	{
		$this->clearFields();
		$this->_trueNames = array();

		foreach( $data as $fname => $finfo )
		{
			$properties = $this->_init_field;//self::$_initField;
			foreach( $properties as $fprop => $fpropvalue )
			{
				if( isset( $finfo[$fprop] ) )
				{
					$properties[$fprop] = $finfo[$fprop];
				}

				// определяем первичный ключ
				if( $properties[self::F_PRIMARY] )
				{
					$this->primaryAlias = $fname;
					$this->primaryName = $properties[self::F_TRUENAME];
				}

			}

			// если не задано описание поля - берем имя
			if( empty($properties[self::F_TITLE] ) )
			{
				$properties[self::F_TITLE] = $fname;
			}

			// если не задана таблица, которой принадлежит поле - берем имя главной таблицы
			if( empty($properties[self::F_TABLE] ) )
			{
				$properties[self::F_TABLE] = $this->tableName;
			}


			//$this->_fields[$fname] = $properties;
			$this->addField($fname, $properties);

			$truename = $this->getFieldProperty($fname, self::F_TRUENAME);
			$this->_trueNames[$truename] = $fname;

		}
		return $this;
	}

	/**
	 * Возвращает имя поля по имени реального поля из БД
	 *
	 * @param  string $truename
	 * @return string
	 */
	public function getFieldByTrueName($truename)
	{
		if(!array_key_exists($truename, $this->_trueNames))
		{
			return null;
		}
		return  $this->_trueNames[$truename];
	}

	/**
	 * Возвращает placeholder для заданного поля, определяемый
	 * типом поля
	 *
	 * @param string $name
	 * @return string
	 */
	public function getPlaceholder( $name )
	{
		if(null === ($field = $this->getField($name)))
		{
			return null;
		}

		switch( $field[self::F_TYPE] )
		{
			case self::TYPE_DATE:
			case self::TYPE_INTEGER:
			case self::TYPE_DATETIME:
					$ph = '?d';
					break;
			case self::TYPE_FLOAT:
					$ph = '?f';
					break;
			default:
					$ph = '?';
		}
		return $ph;
	}

	/**
	 * Возвращает свойства поля первичного ключа
	 *
	 * @return array
	 */
	public function getPrimaryInfo()
	{
		return $this->getField($this->primaryAlias);
	}
}
?>
