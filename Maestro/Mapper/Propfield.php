<?php

/**
 * Description of Propfield
 *
 * @author maestro
 */
class Maestro_Mapper_Propfield extends Maestro_Mapper_Abstract {

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
	 *
	 * @var array
	 */
	protected $_proptypes;
	
	protected $_initTable = array('PRF' => 'PROPFIELDS');
	protected $_initFields = array(
		'id' => array(
			'title' => 'Идентификатор',
			'truename' => 'FIELDID',
			'type' => 'integer',
			'primary' => true
		),
		'class' => array(
			'title' => 'Класс',
			'truename' => 'CLASSID',
			'type' => 'integer',
			'check' => array('integer')
		),
		'name' => array(
			'title' => 'Имя поля',
			'truename' => 'FIELDNAME',
			'type' => 'string',
			'check' => array('length' => 40, 'noempty')
		),
		'table' => array(
			'title' => 'Таблица',
			'truename' => 'PRTABLE',
			'type' => 'string',
			'check' => array('length' => 40)
		),
		'field' => array(
			'title' => 'Поле',
			'truename' => 'PRNAME',
			'type' => 'string',
			'check' => array('length' => 40)
		),
		'type' => array(
			'title' => 'Тип',
			'truename' => 'TYPEID',
			'type' => 'integer'/*,
			'check' => array('keys' => array())*/
		),
		'description' => array(
			'title' => 'Описание',
			'truename' => 'DESCRIPT',
			'type' => 'string',
			'check' => array('length' => 255)
		),
		'width' => array(
			'title' => 'Width',
			'truename' => 'WIDTH',
			'default' => 0,
			'type' => 'integer'
		),
		'scale' => array(
			'title' => 'Scale',
			'truename' => 'SCALE',
			'default' => 0,
			'type' => 'integer'
		),
		'flag' => array(
			'title' => 'Flag',
			'truename' => 'FLAG',
			'default' => 0,
			'type' => 'integer'
		),
		'enabled' => array(
			'title' => 'Активно',
			'truename' => 'ENABLED',
			'type' => 'integer',
			'check' => array('in' => array(0, 1))
		),
		'created' => array(
			'truename' => 'CREATED',
			'type' => 'datetime'
		),
		'creator' => array(
			'truename' => 'CREATORID',
			'type' => 'integer'
		),
		'changed' => array(
			'truename' => 'CHANGED',
			'type' => 'datetime'
		),
		'changer' => array(
			'truename' => 'CHANGERID',
			'type' => 'integer'
		)
	);

	/**
	 * Возвращает новое уникальное значение идентификатора пользователя
	 *
	 * @return integer
	 */
	protected function _new_id() {
		return $this->db->selectCell("select gen_id(GEN_PROPFIELD_ID,1) as NEW_ID from rdb\$database");
	}

	/**
	 *
	 * @param Maestro_Unit $u
	 */
	protected function before_insert($u) {
		$this->filterStoreUnit($u);
		
		$u->name = strtoupper($u->name);
		$u->table = strtoupper($u->table);
		$u->origin = strtoupper($u->origin);
		
		$u->creator = Session::subject();
	}

	/**
	 *
	 * @param Maestro_Unit $u
	 */
	protected function before_update($u) {
		$this->filterStoreUnit($u);
		
		$u->changer = Session::subject();
		$u->changed = time();//date('d.m.Y H:i:s');
		
		$u->remove('creator');
		$u->remove('created');
	}

	/**
	 * Приводит поля в порядок перед сохранением в БД
	 * 
	 * 
	 * @param Maestro_Unit $u 
	 */
	protected function filterStoreUnit($u) {
		$u->enabled = $u->enabled ? 1 : 0;
		$u->width = $u->width ? $u->width : 0;
		$u->scale = $u->scale ? $u->scale : 0;
		$u->flag  = $u->flag  ? $u->flag  : 0;
		
		$this->getTypes();
		$utype = $this->_proptypes[$u->type];
		
		if(!$u->table) {
			$u->table = 'PROPVAL_'.$utype->code;
			//$u->field = 'VAL';
		}
		
		if(!$u->field) {
			$u->field = $u->name;
		}
		
		$u->name = strtoupper($u->name);
		$u->table = strtoupper($u->table);
		$u->field = strtoupper($u->field);
	}
	
	/**
	 *
	 * @return array
	 */
	public function getTypes() {
		if(!$this->_proptypes) {
			$this->_proptypes = array();
			$rows = $this->db->select('select TYPEID, TYPECODE, TYPENAME from PROPTYPES');
			if($rows) {
				foreach($rows as $row) {
					$id = $row['TYPEID'];
					$this->_proptypes[$id] = new Maestro_Unit($id, array(
						'code' => $row['TYPECODE'],
						'name' => $row['TYPENAME']
					), true);
				}
			}
		}
		return $this->_proptypes;
	}
	
	/**
	 *
	 * @return array
	 */
	public function getTypesAsKeys() {
		$keys = array();
		$this->getTypes();
		foreach($this->_proptypes as $type) {
			$keys[$type->id] = $type->code . ' - ' . $type->name;
		}
		return $keys;
	}
	
	/**
	 * Возвращает описания полей указанного класса объектов
	 * 
	 */
	public function getProperties($class_id) {
		$affected = array();
		$fields = $this->get()
				->columns('*')
				->join(array('p' => 'tree_parents(?d, 0, null)'), 'p.obid = #class', array('level' => 'lvl'))
				->joinLeft(array('t' => 'proptypes'), 't.typeid = #type', array('type_code' => 'typecode'))
				->param($class_id)
				->where('bin_and(#flag, 8) <> 8')
				//->where('#enabled = 1')
				->order('#id')
				->fetch();
		foreach($fields as $field) {
			$field->name = strtolower($field->name);
			if(in_array($field->name, $affected)) {
				$field->overload = true;
			} else {
				$affected[] = $field->name;
			}
			
			if($field->flag & self::FLAG_PRIMARY_KEY) {
				$field->primary = true;
			}
		}
		return $fields;
	}
}

?>
