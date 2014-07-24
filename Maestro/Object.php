<?php

/**
 * Класс маппера "новых объектов" таблицы OBJECTS.
 * Основные отличия - в алиасах полей таблицы
 * 
 * При наследовании этого класса нужно переопределить защищенное свойство $CLASSNAME.
 * 
 *
 * @author maestro
 */
class Maestro_Object extends Maestro_Mapper_Object {

	/**
	 * Конструктор класса
	 *
	 */
	public function __construct($dbClass = null, $db = 'engine') {
		/*if ($dbClass) {
			$this->CLASSNAME = $dbClass;
		}*/
		parent::__construct($db, $dbClass);
	}

	/**
	 *
	 */
	public function init() {
		$this->_defines->setFieldProperty('created', 'type', 'timestamp');
		$this->_defines->setFieldProperty('changed', 'type', 'timestamp');
	}

	/**
	 * 
	 * @param Maestro_Unit $options
	 */
	protected function _beforeReadCustom($options) {
		$this->joinLeft(array('m1' => 'members'), 'm1.id = #creator', array('creator_name' => 'name'));
		$this->joinLeft(array('m2' => 'members'), 'm2.id = #changer', array('changer_name' => 'name'));
	}
	
	/**
	 * 
	 * @param Maestro_Unit $object
	 * @param Maestro_Unit $options
	 */
	protected function _beforeReadEachCustom($object, $options) {
		if($object->created) {
			$object->create_info = $object->created . ' <span class="remark"> • ' . $object->creator_name . '</span>';
			$object->created = $object->created->humanity();
		}
		if ($object->changer) {
			$object->change_info = $object->changed . ' <span class="remark"> • ' . $object->changer_name . '</span>';
			$object->changed = $object->changed->humanity();
		}
	}
	
	/**
	 * Включаем выборку прав доступа к каждому объекту. Если указан первый параметр,
	 * то выбираются объекты с правами, соответсвующими указнанной маске.
	 * Вторым параметром задается идентификатор субъекта (группа или пользователь), отличные
	 * от идентификатора текущего юзера, для которого производится выборка и проверка прав доступа..
	 * 
	 * @param integer $match_mask
	 * @param integer $subject_id
	 * @return Object 
	 */
	public function acl($match_mask = 0, $subject_id = null, $division_id = null, $add_conditions = false) {
		if(is_null($subject_id)) {
			$subject_id = Session::subject();
		}
		if(is_null($division_id)) {
			$division_id = Session::get('userBranch');
		}
		$this->joinLeft(
				array('acl' => 'get_rights_acl(#id, '.intval($subject_id).')'), 
				'1=1', 
				array('acl_mask' => 'mask')
		);
		if($match_mask > 0) {	
			$conditions = Common::sql_placeholder('bin_and(acl.mask, ?d) = ?d', $match_mask, $match_mask);
			if($add_conditions) {
				$conditions .= ' ' . $add_conditions;
			}
			$this->where($conditions);
		}
		return $this;
	}

	/**
	 *
	 * @param Maestro_Unit $u
	 */
	protected function before_insert($u) {
		$u->class = $this->_defines->classInfo()->id;
		$u->creator = Session::subject();
		$u->created = date('d.m.Y H:i:s');
		$u->changer = $u->creator;
		$u->changed = date('d.m.Y H:i:s');
	}

	/**
	 *
	 * @param Maestro_Unit $u
	 */
	protected function before_update($u) {
		$u->changer = Session::subject();
		$u->changed = date('d.m.Y H:i:s');
		
		$u->remove('creator');
		$u->remove('created');
	}
}
