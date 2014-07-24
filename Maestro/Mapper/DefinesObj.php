<?php
/**
 * Описание полей классифицированных объектов (PROPFIELDS)
 *
 * @author maestro
 */
class Maestro_Mapper_DefinesObj extends Maestro_Mapper_Defines
{

	/**
	 * Идентификатор класса описания классов
	 * Все БД-классы должны быть элементами этого фиксированного, уникального класса
	 *  
	 */
	const CLASSES = -10;

	/**
	 * Объект базы данных
	 *
	 * @var DBSimple object
	 */
	protected $_db;

	/**
	 * дополнительные типы полей
	 */
	//const TYPE_PTR = 'ptr';

	/**
	 * 
	 * @var Maestro_Unit
	 */
	private $_class_info;

	/**
	 * флаги полей БД
	 */
	const FLAG_HIDE          =  1;
	const FLAG_ALLOW_NONE    =  2;
	const FLAG_HIDE_GRID     =  4;
	const FLAG_CLASS_DEF     =  8;
	const FLAG_PRIMARY_KEY   = 16;
	const FLAG_SECONDARY_KEY = 32;
	const FLAG_SHOW_PTR_NAME = 64;

	/**
	 * Дополнительные типы полей
	 */


	/**
	 * Соответствие таблиц доп.свойств типам полей
	 *
	 * @var array
	 */
	private $_types = array(
		'INT'       => self::TYPE_INTEGER,
		'DBL'       => self::TYPE_FLOAT,
		'STR'       => self::TYPE_STRING,
		'TXT'       => self::TYPE_STRING,
		'PTR'       => self::TYPE_LINK,
		'FILE'      => self::TYPE_STRING,
		'DATE'      => self::TYPE_DATETIME,
		'USER'      => self::TYPE_INTEGER,
		'ITEMS'     => self::TYPE_INTEGER,
		'TIME'      => self::TYPE_DATE,
		'TIMESTAMP' => self::TYPE_TIMESTAMP,
		'ACTIONS'   => self::TYPE_INTEGER,
		'GROUPS'    => self::TYPE_INTEGER
	);

	/**
	 *
	 * @param DbSimple $_base object
	 * @param integer|string $_db_class
	 */
	public function __construct( $_base, $_db_class )
	{
		$this->_db = $_base;

		//$this->tableAlias = 'OBJ';
		//$this->tableName  = 'OBJECTS';
		parent::__construct(array('OBJ' => 'OBJECTS'), array());

		$this->_class( $_db_class );
	}

	/**
	 * Возвращает индекс "корневого" элемента класса
	 *
	 * @return integer
	 */
	public function class_root()
	{
		$id = $this->_db->selectCell(
			sprintf(
				"select OBID, NAME from %s where CLASSID=%d and CODE='ROOT'",
				$this->tableName,
				$this->_class_info['OBID']
			)
		);
		if( !$id )
		{
			throw new Exception( 'Root failed in class '.$this->_class_info['OBID'].' `'.$this->_class_info['NAME'].'`' );
		}
		return $id;
	}
	
	/**
	 *
	 * @param string $name
	 * @return string
	 */
	public static function filterCode($name) {
		return 'dbclass_'.str_replace(array('.','-',':'), '_', $name);
	}

	/**
	 * Получает свойства класса (по идентификатору или коду), список свойств полей класса
	 * Кэширование включаем.
	 *
	 * @param integer|string $dbo_class
	 */
	private function _class( $_db_class )
	{
		$cache = Maestro_App::getCache();
		$id = $this->filterCode($_db_class);
		if(!($data = $cache->load($id))) {			
			$query  = 'select obj.*, t.val as descript from OBJECTS obj left join PROPVAL_TXT t on t.obid=obj.obid where ';
			$query .= is_numeric( $_db_class ) ? 'obj.obid='.$_db_class : "obj.code='{$_db_class}'";
			$row = $this->_db->selectRow( $query );
			if($row) {
				$this->_class_info = new Maestro_Unit($row['OBID'], array(
					'class' => $row['CLASSID'],
					'parent' => $row['PARENTID'],
					'code' => $row['CODE'],
					'name' => $row['NAME'],
					'group' => $row['PROPID'],
					'descript' => Maestro_Yaml::parse($row['DESCRIPT']),
					'changed' => new Maestro_DateTime(date('d.m.Y', $row['CHANGE_DATE']))
				), true);
				$this->_define_fields_by_class( $row['OBID'] );
				$tags = array('dbci'.str_replace('-', '_',$this->_class_info->id), 'dbclass');
				$cache->save(array(
							'info' => $this->_class_info->toArray(),
							'fields' => $this->_fields,
							'names' => $this->_trueNames,
							'palias' => $this->primaryAlias,
							'pname' => $this->primaryName
					), $id, $tags
				);
			}
			else {
				throw new Exception( "Class `{$_db_class}` not found in object repository." );
			}
		} else {
			$this->_class_info = new Maestro_Unit($data['info']['id'], $data['info']);
			$this->_fields = $data['fields'];
			$this->_trueNames = $data['names'];
			$this->primaryAlias = $data['palias'];
			$this->primaryName = $data['pname'];
		}
	}
	
	/**
	 * Свойства объекта
	 *	- id
	 *	- class
	 *	- parent
	 *	- code
	 *	- name
	 *	- changed
	 *
	 * @return Maestro_Unit
	 */
	public function classInfo() {
		return $this->_class_info;
	}

	/**
	 *
	 * @return type 
	 */
	public function getClassId() {
		//return isset($this->_class_info['OBID']) ? $this->_class_info['OBID'] : null;
		return $this->_class_info->id;
	}

	/**
	 * Инициализируем список описаний полей входящим массивом
	 *
	 * @param array $data
	 * @param  array $filter
	 */
	public function setFields(array $data, $filter = null)
	{
		//
	}
	
	/**
	 * удаляем кэш описаний класса
	 * 
	 * @param boolean $refresh_fields перечитывать инфо полей после очистки кэша
	 */
	public function clearCache($refresh_fields = false) {
		$cache = Maestro_App::getCache();
		$tag = 'dbci'.str_replace('-', '_',$this->_class_info->id);
		$cache->clean(
			Zend_Cache::CLEANING_MODE_MATCHING_TAG,
			array($tag)
		);
		if($refresh_fields) {
			$this->_class($this->_class_info->code);			
		}
	}

	/**
	 * Инициализируем список описаний полей данными из таблицы описания полей класса БД
	 *
	 * @param integer $_class_id
	 */
	protected function _define_fields_by_class( $_class_id )
	{
		$this->_fields = array();
		$query = "
select
 f.fieldname as ARRAY_KEY,
 f.fieldid,
 p.obid as ownerid,
 p.lvl as parentlevel,
 f.fieldname,
 coalesce(f.prtable, 'PROPVAL_'||t.typecode) as prtable,
 coalesce(f.prname, f.fieldname) as prname,
 t.typeid,
 t.typecode,
 f.descript,
 f.width,
 f.scale,
 --f.format,
 f.flag
from tree_parents( ?d, 0, null ) p
left join propfields f on f.classid = p.obid and f.enabled = 1
left join proptypes t on t.typeid = f.typeid
where bin_and(f.flag,8)<>8
order by p.lvl desc, f.fieldid
		";

		$rows = $this->_db->select( $query, $_class_id );
		//pre( $rows );
		$_added_prfields = array();

		if( $rows)
		foreach( $rows as $fname => $row )
		{
			// имя-ключ поля
			$fname = strtolower( $fname );

			// имя поля из БД
			$prname = $row['PRNAME'];

			// удаляем описания полей, взятых из родительских классов
			if( array_key_exists( $prname, $_added_prfields ) )
			{
				$f = $_added_prfields[$prname];
				unset($this->_fields[$f]);
			}
			$_added_prfields[$prname] = $fname;

			// инициализируем поле
			$field = $this->_init_field;//self::$_initField;

			// пропускаем поля класса
			if( $row['FLAG'] & self::FLAG_CLASS_DEF ) continue;

			// пропускаем повторения описания полей (для корректного наследования классов)
			if( isset( $this->_fields[$fname] ) ) continue;

			// реальное имя поля
            $prname = $row['PRNAME'];
			$field[self::F_TRUENAME] = $row['PRNAME'];

            if(isset($this->_trueNames[$prname]))
            {
                $prev_fname = $this->_trueNames[$prname];
                //if(isset($this->_fields[$prev_fname]))
                        $this->deleteField($prev_fname);
            }

			// индекс поля
			$field[self::F_INDEX] = $row['FIELDID'];

			// имя таблицы-владельца данного поля
			$field[self::F_TABLE] = $row['PRTABLE'];

			// имя таблицы-владельца данного поля
			$field[self::F_TITLE] = empty($row['DESCRIPT']) ? $fname : $row['DESCRIPT'];

			// тип поля
			$t = $row['TYPECODE'];
			if( array_key_exists( $t, $this->_types ) )
			$field[self::F_TYPE] = $this->_types[$t];

			// первичный ключ
			if( $row['FLAG'] & self::FLAG_PRIMARY_KEY )
			{
				$field[self::F_PRIMARY]  = true;
				$field[self::F_READONLY] = true;
				$this->primaryAlias = $fname = 'id';
				$this->primaryName = $field[self::F_TRUENAME];

				//$this->tableAlias = $field[self::F_TABLE];
				//$this->tableName = $field[self::F_TABLE];
			}

			// определяем тип поля
			switch( $row['TYPECODE'] )
			{
				case 'PTR':
					//$field[self::F_TYPE]   = self::TYPE_LINK;
					$field[self::F_CHECK]['link'] = $row['WIDTH'];
					break;
				case 'INT':
					$field[self::F_DEFAULT] = intval($row['WIDTH']);
					break;
				case 'DATE':
					//$field[self::F_TYPE] = self::TYPE_DATETIME;
					break;
				case 'ITEMS':
					//$field[self::F_TYPE] = self::TYPE_INTEGER;
					break;
				case 'STR':
					//$field[self::F_TYPE] = self::TYPE_STRING;
					if( $row['WIDTH'] )
					{
						$field[self::F_CHECK]['length'] = $row['WIDTH'];
					}
					break;
			}
            // если формат присутствует для полей дата/время, то добавим его в валидаторы
            // !!! отключено пока до проверки совместимости новой фичи, устанавливаем пока вручную
            // в созданных объектах класса
            /*if(($field[self::F_TYPE] = self::TYPE_DATE) || ($field[self::F_TYPE] == self::TYPE_DATETIME))
            {
                if(!empty($row['FORMAT']))
                {
                    $field[self::F_CHECK]['format'] = $row['FORMAT'];
                }
            }*/
			
			$field['level'] = $row['PARENTLEVEL'];

			$this->_fields[$fname] = $field;

			$truename = $this->getFieldProperty($fname, self::F_TRUENAME);
			$this->_trueNames[$truename] = $fname;
		}
	}
}
?>
