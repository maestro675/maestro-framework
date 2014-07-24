<?php

/**
 * Description of Object
 *
 * @author maestro
 */
class Maestro_Mapper_Object extends Maestro_Mapper_Table {
	
	/**
	 * Код класса
	 *
	 * @var string
	 */
	protected $CLASSNAME;

	/**
	 *
	 * @var boolean
	 */
	public $tree_objects = false;

	/**
	 * Конструктор класса
	 *
	 */
	public function __construct($dbName, $dbClass = null) {
		if ($dbClass) {
			$this->CLASSNAME = $dbClass;
		}
		parent::__construct($dbName);
	}

	/**
	 * 
	 * @return Maestro_Mapper_DefinesObj
	 */
	public function getDefines() {
		return $this->_defines;
	}
	
	/**
	 * Выбирает объекты класса по цепочке от указанного до самого верхнего уровня в
	 * обратном порядке. Т.о. объект, идентификатор которого передается, будет первым в
	 * возвращаемом массиве. Дополнительная сортировка сбрасывается этим методом.
	 * 
	 * @param integer $id 
	 * @param boolean $strong
	 * @return Maestro_Mapper_Object
	 */
	public function thread($id, $strong = true) {
		/*$this->_builder->joinInner(array(
			'tree' => sprintf('tree_parents(%d,0,%d)', $id, $this->_defines->classInfo()->id)
		), 'tree.obid = #id', array('lvl'));
		$this->_builder->order('lvl desc');
		$this->debug = true;
		return $this;*/
		$classid = ($strong) ? $this->_defines->classInfo()->id : null;
		$this->_builder->joinRight(
				array('tree' => sprintf('get_parents(%d, %d)', $id, $classid)), 
				'tree.id = #id', 
				array('node_level')
		);
		$this->_builder->reset(Maestro_Select::ORDER);
		//$this->_builder->reset(Maestro_Select::WHERE);
		//$this->where($this->_defines->tableAlias . '.CLASSID=' . $this->_defines->classInfo()->id);
		//$this->where($this->_defines->tableAlias . '.DELETED=0 or ' . $this->_defines->tableAlias . '.DELETED IS NULL');
		return $this;
	}
	
	/**
	 * Выбирает все дочерние объекты класса, отсортированные в нужно порядке в виде дерева.
	 * Причем первым элементом выберется объект, указанный передваемым идентификатором.
	 * Дополнительную сортировку задавать не нужно.
	 * 
	 * @param integer $id 
	 * @return Maestro_Mapper_Object
	 */
	public function map($id) {
		$this->_builder->joinInner(array(
			'tree' => sprintf('tree_objects(%d,0,%d)', $id, $this->_defines->classInfo()->id)
		), 'tree.obid = #id', array('lvl'));
		return $this;
	}

	/**
	 *
	 * @param string $name имя поля
	 * @param array $checks массив валидаторов
	 * @return Maestro_Mapper_Object
	 */
	public function addFieldCheck($name, $checks) {
		if (!is_array($checks)) {
			$checks = array($checks);
		}
		$this->getDefines()->setFieldProperty($name, Maestro_Mapper_Defines::F_CHECK, $checks);
		return $this;
	}

	/**
	 * Присоединяет к запросу поля объекта, на который ссылается
	 * указанное поле типа PTR
	 *
	 * @param string $field
	 * @param array $cols
	 * @return Maestro_Mapper_Object
	 */
	public function linkto($field, $cols = null) {
		$field = strtolower($field);

		$finfo = $this->_defines->getField($field);
		if (is_null($finfo)) {
			return $this;
		}
		if (Maestro_Mapper_Defines::TYPE_LINK != $finfo[Maestro_Mapper_Defines::F_TYPE]) {
			return $this;
		}

		if (is_null($cols)) {
			$cols = array('code', 'name');
		}

		if (!is_array($cols)) {
			$cols = explode(',', $cols);
		}

		$this->_defines->linked[$field] = $cols;
		$this->columns($field);

		return $this;
	}

	/**
	 *
	 */
	protected function _set_defines() {
		$this->_defines = new Maestro_Mapper_DefinesObj($this->db, $this->CLASSNAME);
	}

	/**
	 *
	 */
	protected function _set_builder_select() {
		$this->_builder = new Maestro_Mapper_Select($this->_defines);
		/*if ($this->tree_objects) {
			$rootid = $this->_defines->class_root();
			$this->_builder->joinInner(array(
				'TREE' => "TREE_OBJECTS({$rootid}, 0, {$this->_defines->classInfo()->id})"), null, array('lvl')
			);
			$this->_builder->joinInner(array(
				$this->_defines->tableAlias => $this->_defines->tableName
			), $this->_defines->tableAlias . '.OBID=TREE.OBID', null);
		} else {*/
			$this->_builder->joinInner(array(
				$this->_defines->tableAlias => $this->_defines->tableName
			), null, null);
		//}
		$this->where($this->_defines->tableAlias . '.CLASSID=' . $this->_defines->classInfo()->id);
		$this->where($this->_defines->tableAlias . '.DELETED=0');
	}

	/**
	 * Переопределяем метод создания элемента.
	 * Список полей расширяется полями, не включенными в описание полей класса объектов.
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

		//$linkValues = array();
		//$pkName = $this->_defines->primaryName;

		foreach ($data as $dataField => $dataValue) {
			$name = $this->_defines->getFieldByTrueName($dataField);
			// если поле найдено в описаниях
			if (null !== $name) {
				$fieldInfo = $this->_defines->getField($name);
				if ($name == $this->_defines->primaryAlias) {
					$id = $dataValue;
					continue;
				}

				$ftype = $this->_defines->getFieldProperty($name, Maestro_Definitions::F_TYPE);
				//if($this->_defines->getClassId() == -10)pre($name.':'.$ftype, 'yellow');
				// преобразуем дату
				if (Maestro_Definitions::TYPE_DATE == $ftype) {
					$format = isset($fieldInfo[Maestro_Definitions::F_CHECK]['format']) ? $fieldInfo[Maestro_Definitions::F_CHECK]['format'] : false;
					if (($this->eyeDate || $format) && $dataValue && is_numeric($dataValue)) {
						if (!$format) {
							$format = Maestro_DateTime::DMY;
						}
						$dataValue = date($format, $dataValue);
					}
				}
				if (Maestro_Definitions::TYPE_DATETIME == $ftype || Maestro_Definitions::TYPE_TIMESTAMP == $ftype) {
					if($dataValue) {
						if (ctype_digit($dataValue)) {
							$dataValue = date('d.m.Y H:i:s', $dataValue); //'@'.$val;
						}
						$dataValue = new Maestro_DateTime($dataValue);
					}
				}


				if (!isset($values[$name])) {
					$values[$name] = $dataValue;
				}
			} else
			// если найдено поле линкованого объекта
			if (preg_match("/^LINK__(.+)__(.+)/", $dataField, $matches)) {
				$ownField = $matches[1];
				$field = strtolower($ownField);
				$linkField = strtolower($matches[2]);
				// если поле, по которому прилинкован объект, определено
				if ($this->_defines->issetField($field)) {
					$ftn = $this->_defines->getFieldProperty($field, Maestro_Mapper_Defines::F_TRUENAME);

					// если линк-объект не существует, то создаем его
					if (!isset($values[$field]) || !($values[$field] instanceof Maestro_Unit)) {
						$lu = new Maestro_Unit($data[$ftn]); //new Maestro_Unit($data[$ownField]);
						$values[$field] = $lu;
					}
					$lu = $values[$field];
					$lu->$linkField = $dataValue;

					//$linkValues[$field]['id'] = $data[$ownField];
					//$linkValues[$field][$linkField] = $dataValue;
				}
			} else
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

		if ($this->debug)
			pre($u);
		return $u;
	}

	/**
	 * Обновим поля дополнительных свойств
	 *
	 * @param Maestro_Unit $u object
	 */
	protected function after_update($u) {
		foreach ($this->_affected_fields as $field) {
			$this->_property_iu($u->id, $field, $u->$field);
		}
	}

	/**
	 *
	 * @param Maestro_Unit $u 
	 */
	protected function before_insert($u) {
		$u->classid = $this->_defines->classInfo()->id;
		$u->creatorid = Session::subject();
	}

	/**
	 *
	 * @param Maestro_Unit $u
	 */
	protected function before_update($u) {
		$u->changerid = Session::subject();
	}

	/**
	 * Обновим поля дополнительных свойств
	 *
	 * @param Maestro_Unit $u object
	 */
	protected function after_insert($u) {
		foreach ($this->_affected_fields as $field) {
			$this->_property_iu($u->id, $field, $u->$field);
		}
	}

	/**
	 * Устанавливает значение доп.поля объекта
	 *
	 * @param integer $obid идентификатор объекта
	 * @param string $field имя алиаса поля объекта
	 * @param mixed $value значение
	 */
	protected function _property_iu($obid, $field, $value) {
		$_field_info = $this->_defines->getField($field);

		switch ($_field_info['type']) {
			case Maestro_Mapper_Defines::TYPE_STRING:
			case Maestro_Mapper_Defines::TYPE_TIMESTAMP:
				$_fph = '?';
				break;
			case Maestro_Mapper_Defines::TYPE_DATE:
			case Maestro_Mapper_Defines::TYPE_INTEGER:
			case Maestro_Mapper_Defines::TYPE_LINK:
				$_fph = '?d';
				break;
			case Maestro_Mapper_Defines::TYPE_FLOAT:
				$_fph = '?f';
				break;
			default:
				$_fph = '?d';
				break;
		}

		$value = $this->validFieldValueOnStore($field, $value);

		$_params = array(
			"select * from SET_{$_field_info['ttable']}( ?d, ?d, {$_fph}, ?d)",
			$obid,
			$_field_info['index'],
			$value,
			Session::subject() // ID пользователя
		);

		if ($this->debug) {
			echo $field . ':';
			pre($_params);
		} else {
			call_user_func_array(array($this->db, 'query'), $_params);
		}
	}

	/**
	 * Удаляет из БД запись
	 *
	 * @param integer $id
	 */
	protected function _sql_delete($id) {
		$_params[0] = sprintf("update %s set DELETED=1 where CLASSID=?d and %s=?d", $this->_defines->tableName, $this->_defines->primaryName
		);
		$_params[1] = $this->_defines->classInfo()->id;
		$_params[2] = $id;

		if ($this->debug) {
			pre($_params);
		} else {
			call_user_func_array(array($this->db, 'query'), $_params);
		}
	}

	/**
	 * Возвращает новое уникальное значение идентификатора пользователя
	 *
	 * @return integer
	 */
	protected function _new_id() {
		return $this->db->selectCell("select gen_id(GEN_OBJECTS_ID, 1) from rdb\$database");
	}
}

?>
