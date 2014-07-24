<?php
/**
 * Description of Table
 *
 * @author maestro
 */
class Maestro_Mapper_Table extends Maestro_Mapper_Abstract {
	
	/**
	 * Возвращает один объект или несколько объектов (массив) 
	 * 
	 * 
	 * Допустимые ключи параметра [options]:
	 *	- columns(string|array) - по умолчанию '*'
	 * 	- one (integer)
	 *  - single (boolean) - возвращает только первый объект
	 *  - first (integer)
	 * 	- skip (integer)
	 * 	- paginator (object)
	 *  - debug (boolean)
	 *	- order (string)
	 *	- fetchMode (string)
	 *	- fetchField (string)
	 * 
	 * Триггеры:
	 *	- _beforeReadOptions [options]
	 *	- _beforeRead [options]
	 *	- _afterReadEach [object, options]
	 *	- _afterRead [options]
	 * 
	 * @param array $options
	 * @return array или объект Maestro_Unit
	 */
	public function read($options = null) {
		$options = new Maestro_Unit(NULL, $options);
		$this->_invoke('_beforeReadOptions', $options);
		
		// проверяем, выбирается один объект или несколько
		$getOne = false;
		if($options->present('one')) {
			$getOne = true;
			// проверяем на валидность идентификатор объекта
			if(!is_numeric($options->one)) {
				$options->one = -1;
			}
		}
		// определяем выбираемые столбцы и указываем смещение
		$this->get()
				->columns($options->get('columns', '*'))
				->limit($options->first, $options->skip);
		
		$this->_beforeReadCustom($options);
		
		// только один элемент
		if($getOne) {
			$this->where('#id = ?d', $options->one);
		}
		// разбивка на страницы
		if ($options->paginator instanceof Maestro_XmlObject_Paginator) {
			if ($options->paginator->current > 0) {
				$this->limit($options->paginator->countPerPage, $options->paginator->countPerPage * ($options->paginator->current - 1));
			}
		}		
		// сортировка
		if($options->order) {
			$this->order($options->order);
		}
		// режим выборки
		if($options->fetchMode) {
			$this->_fetchOptions->mode = $options->fetchMode;
			if($options->fetchField) {
				$this->_fetchOptions->field = $options->fetchField;
			}
		}
			
		$this->_invoke('_beforeRead', $options);
		// выбираем
		$objects = $this->_fetch();
		if($options->debug) {
			pre($options, '#555555', '#ccc');
			pre($this->queryString(), '#bad967');
			pre($this->getParams(), '#bad967');		
		}
		//
		foreach($objects as $object) {
			$this->_afterReadEachCustom($object, $options);
			$this->_invoke('_afterReadEach', $object, $options);
		}
		$this->_invoke('_afterRead', $options);
		if($options->debug) {
			pre($objects, '#d0d9ba');
		}
		return ($getOne || $options->single) ? array_shift($objects) : $objects;
	}
	
	protected function _beforeReadCustom($options) {}
	protected function _beforeReadEachCustom($object, $options) {}
	

	/**
	 * Создает и возвращает новый объект.
	 * 
	 * Триггеры:
	 *	- _afterCreate [options]
	 * 
	 * @param array $values 
	 * @return Maestro_Unit $object 
	 */
	protected function create($values) {		
		$object = $this->createFromArray(NULL, $values);
		$this->_invoke('_afterCreate', $object);
		return $object;
	}

	/**
	 * Сохраняет в объект данные, переданные в массиве $values. Причем если в нем присутсвует
	 * ключ `id` - происходит обновление объекта, иначе - создание нового. В случае удачного 
	 * завершения возвращается обновленный (созданный объект).
	 * 
	 * Триггеры:
	 *	- _beforeWrite [object, creating]
	 *	- _afterWrite [object, creating]
	 * 
	 * @param array $values
	 * @return Maestro_Unit
	 */
	public function write($values) {
		$id = isset($values['id']) ? $values['id'] : null;
		try {
			$this->transaction();
			// создаем объект из входящих данных
			// или получаем объект из БД со всеми полями
			$creating = false;
			if($id) {
				$object = $this->read(array('one' => $id));
				if(!$object) {
					throw new Maestro_Exception('Requested object is not accessed, or no exists? or has been deleted.');
				}
				$object->merge($values);
			} else {
				$creating = true;
				$object = $this->create($values);
			}
			$this->_invoke('_beforeWrite', $object, $creating);
			// проверяем валидность объекта
			if (!$this->validate($object)) {
				throw new Maestro_Exception('Object is not valid');
			}
			// сохраняем документ
			$this->save($object);
			// триггер с постобработкой
			$this->_invoke('_afterWrite', $object, $creating);
			$this->commit();
			return $object;
		} catch (Exception $e) {
			$this->rollback();
			//throw new Maestro_Exception($e);
			return false;
		}
	}
	
	/**
	 * Удаляет объект из БД
	 * 
	 * Триггеры:
	 *	- _beforeRemove [object]
	 *	- _afterRemove [object]
	 * 
	 * @param integer $id
	 * @return boolean
	 * @throws Maestro_Exception
	 */
	public function remove($id) {
		$object = $this->read(array('one' => $id));
		if (!$object) {
			return false;
		}
		try {
			$this->transaction();
			$this->_invoke('_beforeRemove', $object);
			$this->delete($object);
			$this->_invoke('_afterRemove', $object);
			$this->commit();
			return $object;
		} catch (Exception $e) {
			$this->rollback();
			throw new Maestro_Exception($e);
			return FALSE;
		}
	}
}

?>
