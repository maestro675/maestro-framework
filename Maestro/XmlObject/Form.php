<?php

/**
 * Description of Maestro_XmlObject_Form
 *
 * @author maestro
 */
class Maestro_XmlObject_Form extends Maestro_Definitions implements Maestro_XmlObject_Interface {
	/*	 * #@+
	 * Константы типов методов
	 */
	const METHOD_POST = 'post';
	const METHOD_GET = 'get';
	/*	 * #@- */

	/*	 * #@+
	 * Константы `enctype`
	 */
	const ENCTYPE_URLENCODED = 'application/x-www-form-urlencoded';
	const ENCTYPE_MULTIPART  = 'multipart/form-data';
	/*	 * #@- */

	/**
	 *
	 * @var array
	 */
	protected $_methods = array('post', 'get');
	/**
	 * Атрибуты и параметры формы
	 *
	 * @var array
	 */
	protected $_attributes = array();
	/**
	 * Значения полей
	 *
	 * @var array
	 */
	protected $_values = array();
	/**
	 * DOM-документ с данными формы для трансформации
	 *
	 * @var Maestro_Dom
	 */
	protected $_xml;
	/**
	 * Экземпляр класса валидатора значений полей
	 *
	 * @var Maestro_Validator
	 */
	protected $_validator;

	/**
	 * Конструктор класса
	 *
	 * @param array $options
	 */
	public function __construct($options = null) {
		
		$this->_init_field['notify']      = '';   // доп.текстовый блок для какой-то хрени
		$this->_init_field['description'] = '';   // еще один текстовый блок с описаниями
		$this->_init_field['visible']     = true; // выводить поле на форму
		$this->_init_field['order']       = 0;    // порядок вывода элемента
		$this->_init_field['group']       = 0;    // группы элементов
		$this->_init_field['super']       = false;
		$this->_init_field['disabled']    = false;
		

		// умолчания
		$this->setMethod(self::METHOD_POST);
		$this->setEnctype(self::ENCTYPE_URLENCODED);
		$this->setAttribute('onsubmit', 'return false;');

		// если опция установлена, то имена полей формы будут вида "formname[field]",
		// иначе - просто по имени поля: "field"
		$this->setAttribute('arrange', true);
		
		// скрывает пустые поля (полезно для readonly-форм)
		$this->setAttribute('hideEmptyFields', false);
		
		// показывать текстовые метки полей
		$this->setAttribute('showLabels', true);
		
		// файл шаблона
		$this->setAttribute('templateFile', false);
		
		//
		$this->setAttribute('labelAlign', 'right');

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
	 * Добавляет поле с солью
	 * 
	 * @return Maestro_XmlObject_Form
	 */
	public function useSalt() {
		if(!array_key_exists('salt', $this->_fields)) {
			$this->addField('salt', array('type' => 'hidden'));
		}
		return $this;
	}

	/**
	 * Генерирует соль и заносит значение в поле
	 * 
	 * @return Maestro_XmlObject_Form
	 */
	public function generateSalt() {
		$this->useSalt();
		$hash = md5(Session::subject() . uniqid(mt_rand(), true)); //sha1(rand(1, 100) . date('dYmiHs') . Session::subject());
		$this->setValue('salt', $hash);
		return $this;
	}

	/**
	 *
	 * @return string
	 */
	public function getType() {
		return $this->getAttribute('type', 'table');
	}

	/**
	 *
	 * @param string $filename
	 * @return Maestro_XmlObject_Form
	 */
	public function setType($type) {
		$this->setAttribute('type', $type);
		return $this;
	}

	/**
	 *
	 * @return string
	 */
	public function getActionType() {
		return $this->getAttribute('actionType');
	}

	/**
	 *
	 * @param string $action
	 * @return Maestro_XmlObject_Form
	 */
	public function setActionType($action) {
		$this->setAttribute('actionType', $action);
		return $this;
	}

	/**
	 * Устанавливает атрибут формы
	 *
	 * @param  string $key
	 * @param  mixed $value
	 * @return Maestro_Form
	 */
	public function setAttribute($key, $value) {
		$key = (string) $key;
		$this->_attributes[$key] = $value;		
		return $this;
	}

	/**
	 * Устанавливает значение ключа атрибута формы
	 *
	 * @param  string $key
	 * @param  string $name
	 * @param  mixed $value
	 * @return Maestro_Form
	 */
	public function setAttributeKey($key, $name, $value) {
		$key = (string) $key;
		$this->_attributes[$key][$name] = $value;
		return $this;
	}

	/**
	 * Добавляем массив атрибутов формы
	 *
	 * @param array $attributes
	 * @return Maestro_Form
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
	 * @return Maestro_Form
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
		if (isset($this->_attributes[$key])) {
			unset($this->_attributes[$key]);
			return true;
		}
		return false;
	}

	/**
	 * Удааляет все атрибуты формы
	 *
	 * @return Maestro_Form
	 */
	public function clearAttributes() {
		$this->_attributes = array();
		return $this;
	}

	/**
	 * Устанавливает параметр `action` формы
	 *
	 * @param string $id
	 * @return Maestro_Form
	 */
	public function setAction($action) {
		return $this->setAttribute('action', $action);
	}

	/**
	 * Устанавливает параметр `method` формы
	 *
	 * @param string $id
	 * @return Maestro_Form
	 */
	public function setMethod($method) {
		if (!in_array($method, $this->_methods)) {
			throw new Maestro_XmlObject_Exception(sprintf('Неизвестный метод `%s`', $method));
		}
		return $this->setAttribute('method', $method);
	}

	/**
	 * Устанавливает параметр `enctype` формы
	 *
	 * @param string $id
	 * @return Maestro_Form
	 */
	public function setEnctype($enctype) {
		return $this->setAttribute('enctype', $enctype);
	}

	/**
	 * Устанавливает имя формы.
	 * Используется для идентификации виджета в `view`.
	 * Дополнительно, как название массива имен полей формы,
	 * т.е. имена полей будут иметь вид: name[field_name]
	 *
	 * @param string $name
	 * @return Maestro_Form
	 */
	public function setName($name) {
		$name = Maestro_Common::filterName($name);
		if ('' == (string) $name) {
			throw new Maestro_XmlObject_Exception('Некорректное имя формы. Должно быть непустым и содержать только корректные символы');
		}

		return $this->setAttribute('name', $name);
	}

	/**
	 * Возвращает имя формы
	 *
	 * @return string
	 */
	public function getName() {
		return $this->getAttribute('name');
	}

	/**
	 * Устанавливает флаг формы `только для чтения`.
	 *
	 * @param  bool $readonly
	 * @return Maestro_form
	 */
	public function setReadonly($readonly = true) {
		if($readonly) {
			$fields = $this->getFields();
			foreach($fields as $name => $finfo) {
				if(self::TYPE_HIDDEN == $finfo[self::F_TYPE]) {
					$this->setFieldProperty($name, 'visible', false);
				} else if(in_array($finfo[self::F_TYPE], array('textarea', 'select', 'longselect', 'link', 'checkboxlist'))) {
					//
				} else {
					$this->setFieldProperty($name, 'type', 'info');
				}
			}
		}
		return $this->setAttribute('readonly', (bool) $readonly);
	}

	/**
	 * Возвращает флаг формы `только для чтения`.
	 *
	 * @return bool
	 */
	public function getReadonly() {
		return $this->getAttribute('readonly');
	}

	/**
	 * Добавляет новое поле, перегружаем родительский метод, добавляя вначале проверку actionType.
	 * Пропускаем поля, в которых указано свойство actions и actionType нет в списке значений этого 
	 * свойства.
	 *
	 * @param  string $name
	 * @param  array $properties
	 * @return Maestro_XmlObject_Form
	 */
	public function addField($name, $properties) {
		$actionType = $this->getActionType();
		if($actionType) {
			if(isset($properties[self::F_ACTIONS])) {
				$actions = $properties[self::F_ACTIONS];
				if(!is_array($actions)) {
					$actions = array($actions);
				}
				if(!in_array($actionType, $actions)) {
					return $this;
				}
			}
		}
		return parent::addField($name, $properties);
	}
			

	/**
	 * Добавляет или перезаписывает обработчик валидности значений $key
	 * для поля $name. Необязательный параметр $params - дополнительные параметры
	 * обработчика.
	 *
	 * @param string $name
	 * @param string $key
	 * @param mixed  $params
	 * @return Maestro_Form
	 */
	public function addCheck($name, $key, $params=null) {
		if ($this->issetField($name)) {
			if (!isset($this->_fields[$name]['check'])) {
				$this->_fields[$name]['check'] = array();
			}

			if (is_null($params)) {
				$this->_fields[$name]['check'][] = $key;
			} else {
				$this->_fields[$name]['check'][$key] = $params;
			}			
		}
		return $this;
	}

	/**
	 * Добавляет или перезаписывает zend-валидатор, заданный $options
	 * для поля $name. 
	 *
	 * @param string $name
	 * @param mixed  $params
	 * @return Maestro_Form
	 */
	public function addValidator($name, $options=null) {
		if ($this->issetField($name)) {
			if (!isset($this->_fields[$name][self::F_VALIDATORS])) {
				$this->_fields[$name][self::F_VALIDATORS] = array();
			}

			$this->_fields[$name][self::F_VALIDATORS][] = $options;
		}
		return $this;
	}

	/**
	 * Устанавливает значение поля. Поле должно быть определено.
	 *
	 * @param  string $name
	 * @param  mixed $value
	 * @return Maestro_form
	 */
	public function setValue($name, $value) {
		if ($this->issetField($name)) {
			$type  = $this->getFieldProperty($name, self::F_TYPE);
			if (in_array($type, array(self::TYPE_DATE, self::TYPE_FLOAT))) {
				$value = str_replace(',', '.', $value);
			}
			$this->_values[$name] = $value;
		}
		return $this;
	}

	/**
	 * Инициализирует элементы значениями из входящего массива.
	 *
	 * @param array $data
	 */
	public function addValues($data = array()) {
		foreach($data as $key => $value) {
			if($this->issetField($key)) {
				$this->setValue($key, $value);
			}
		}
		return $this;
	}

	/**
	 * Устанавливает значения для элементов.
	 * Предыдущие значения очищаются.
	 *
	 * @param array $data
	 */
	public function setValues($data = array(), $clear = true) {
		if($clear) {
			$this->_values = array();
		}
		foreach ($this->getFields() as $key => $field) {
			$super = $this->getFieldProperty($key, 'super');
			if($super) {
				if (is_array($data[$super]) && array_key_exists($key, $data[$super])) {
					$this->setValue($key, $data[$super][$key]);
				} else {
					$this->_values[$key] = $this->getDefaultValue($key);
				}
			} else {
				if (array_key_exists($key, $data)) {
					$this->setValue($key, $data[$key]);
				} else {
					$this->_values[$key] = $this->getDefaultValue($key);
				}
			}
		}
		return $this;
	}

	/**
	 *
	 * @param <type> $name
	 * @return <type>
	 */
	public function getDefaultValue($name) {
		if (!$this->issetField($name)) {
			return null;
		}
		$value = $this->getFieldProperty($name, self::F_DEFAULT);
		if (is_null($value)) {
			$type = $this->getFieldProperty($name, self::F_TYPE);
			if ('datepicker' == $type) {
				$dt = new Maestro_DateTime();
				$value = (string) $dt;
			}
		}
		return $value;
	}

	/**
	 * Возвращает значение поля.
	 * Если значение для поля не найдено во входящем массиве, возвращается значение
	 * по умолчанию, заданное в описании поля.
	 *
	 * @param string $name
	 */
	public function getValue($name) {
		if (!$this->issetField($name)) {
			return null;
		}

		$value = null;
		// если значение не задано - берем "по умолчанию"
		if (!isset($this->_values[$name])) {
			$value =  $this->getFieldProperty($name, self::F_DEFAULT);
		} else {
			$value =  $this->_values[$name];
		}
		return $value;
	}

	/**
	 * Возвращает значения полей. Из списка исключаются поля "только для чтения"
	 *
	 * @param string $super
	 * @return array
	 */
	public function getValues($super = false) {
		$vals = array();
		foreach($this->_fields as $name => $info) {
			$fieldSuper = $this->getFieldProperty($name, 'super');
			if(!$info[self::F_READONLY] && (!$super || ($super == $fieldSuper))) {
				if($fieldSuper) {
					$vals[$fieldSuper][$name] = $this->getValue($name);
				} else {
					$vals[$name] = $this->getValue($name);
				}
			}
		}

		return $vals;
	}

	/**
	 * Применение фильтров к значениям полей формы. Используется внутри процедуры валидации формы.
	 * 
	 * @return Maestro_Form
	 */
	public function applyFilters() {
		foreach($this->_fields as $name => $info) {
			if($info[self::F_READONLY]) {
				continue;
			}
			$value = $this->getValue($name);
			// применяем фильтры поля к значению
			$filters = $this->getFieldProperty($name, self::F_FILTERS);
			if($filters) {
				if(!is_array($filters)) {
					$filters = array($filters);
				}
				foreach($filters as $filter => $params) {
					if(is_numeric($filter)) {
						$filter = $params;
						$params = array();
					}
					$filter_object = new $filter($params);
					if($filter_object instanceof Zend_Filter_Interface) {
						$value = $filter_object->filter($value);
					}
				}
			}
			//
			$this->setValue($name, $value);
		}
		return $this;
	}

	/**
	 * Устанавливает валидатор для проверки полученных значений полей формы
	 *
	 * @param Maestro_Validator $validator
	 * @return Maestro_Form
	 */
	public function setValidator($validator) {
		$this->_validator = $validator;
		return $this;
	}

	/**
	 * Добавляет произвольный элемент. Тип элемента задается
	 * ключом `what` в массиве параметров
	 *
	 * @param string $name
	 * @param array $options
	 * @return Maestro_Form
	 */
	public function addElement($name, $options) {
		$what = isset($options['what']) ? $options['what'] : 'field';
		$method = 'add' . ucfirst($what);
		if (method_exists($this, $method)) {
			$this->$method($name, $options);
		} else {
			throw new Exception(get_class() . 'addElement: unknown element ' . $what);
		}
		return $this;
	}

	/**
	 *
	 * @param array $elements
	 * @param object $config
	 * @return Maestro_Form
	 */
	public function addElements($elements, $config=null) {
		// добавляем элементы в форму
		foreach ($elements as $elementName => $elementNameInfo) {
			if ($config) {
				// для полей проверяем списки и подставляем выше
				// определенные массивы значений
				if (isset($elementNameInfo['check']['keys'])) {
					$keys = $elementNameInfo['check']['keys'];
					if (!is_array($keys)) {
						if ('@' == substr($keys, 0, 1)) {
							$keys = substr($keys, 1);
							if (isset($$keys)) {
								$elementNameInfo['check']['keys'] = $$keys;
							} else {
								$method = 'getKeys_' . ucfirst($keys);
								if (method_exists($config, $method)) {
									$elementNameInfo['check']['keys'] = $config->$method();
								}
							}
						}
					}
				}
				// проверяем доступ к элементу на уровне роли
				if (isset($elementNameInfo['access_role'])) {
					if (!$config->isUserRoleMatch($elementNameInfo['access_role'])) {
						continue;
					}
				}
			}

			// добавляем элемент
			$this->addElement($elementName, $elementNameInfo);
		}
		return $this;
	}

	/**
	 * Добавляет блок произвольного содержания.
	 *
	 * Параметры:
	 *  - title - заголовок блока
	 *  - text  - содержимое
	 *
	 * @param string $name
	 * @param array $options
	 * @return Maestro_Form
	 */
	public function addBlock($name, $options) {
		$name = Maestro_Common::filterName($name);
		
		$actionType = $this->getActionType();
		if($actionType) {
			if(isset($options[self::F_ACTIONS])) {
				$actions = $options[self::F_ACTIONS];
				if(!is_array($actions)) {
					$actions = array($actions);
				}
				if(!in_array($actionType, $actions)) {
					return $this;
				}
			}
		}

		if (!isset($options['type'])) {
			$options['type'] = 'block';
		}

		$this->setAttribute('block:' . $name, $options);
		return $this;
	}

	/**
	 * Добавляет кнопку в атрибуты формы для последующей обработки
	 * Поля для параметров:
	 * 
	 *	- type (string) [button,submit]
	 *	- value (string)
	 *	- visible (boolean)
	 *	- inline (boolean)
	 *	- actions (array)
	 *	- href(string)
	 *
	 * @param string $name
	 * @param array $options
	 * @return Maestro_Form
	 */
	public function addButton($name, $options) {
		$name = Maestro_Common::filterName($name);
		
		$actionType = $this->getActionType();
		if($actionType) {
			if(isset($options[self::F_ACTIONS])) {
				$actions = $options[self::F_ACTIONS];
				if(!is_array($actions)) {
					$actions = array($actions);
				}
				if(!in_array($actionType, $actions)) {
					return $this;
				}
			}
		}

		if (!isset($options['type'])) {
			$options['type'] = 'button';
		}
		if (!isset($options['visible'])) {
			$options['visible'] = true;
		}
		if (!isset($options['inline'])) {
			$options['inline'] = false;
		}
		if (!isset($options['href'])) {
			$options['href'] = 'javascript:void(0);';
		}

		if (isset($options['actions'])) {
			$a = $options['actions'];
			if (is_array($a)) {
				foreach ($a as $key) {
					$actions[$key] = 0;
				}
			}
			$options['actions'] = $actions;
		}


		$this->setAttribute('button:' . $name, $options);
		return $this;
	}

	/**
	 * Добавляет группу для объединения полей-элементов формы.
	 * Параметры:
	 * 
	 *	- title (string)
	 *	- visible (boolean)
	 *	- order (integer)
	 * 
	 * @param string $name
	 * @param array $options
	 * @return \Maestro_XmlObject_Form
	 */
	public function addGroup($name, $options) {
		$name = Maestro_Common::filterName($name);
		$defaults = array(
			'visible' => true,
			'title'   => $name . ' group',
			'order'   => 0,
			'type'    => 'widget',
		);

		$this->setAttribute('group:' . $name, array_merge($defaults, $options));
		return $this;
	}

	/**
	 *
	 * @param string $name 
	 */
	public function deleteButton($name) {
		$name = Maestro_Common::filterName($name);
		$this->removeAttribute('button:' . $name);
	}

	/**
	 * 
	 * @param string $name
	 * @param string $str
	 * @return \Maestro_XmlObject_Form
	 */
	public function setError($name, $str) {
		$this->setFieldProperty($name, self::F_ERRORS, $str);
		return $this;
	}

	/**
	 * Валидация формы.
	 * Проводится по значениям массива $this->_values
	 *
	 * @return bool
	 */
	public function isValid() {
		if (!$this->_validator) {
			throw new Maestro_Exception(get_class() . ': Validator is not assigned');
		}
		
		// вначале применяем фильтры
		$this->applyFilters();

		// после проверяем каждое значение на валидность
		$valid = true;
		foreach ($this->getFields() as $key => $field) {
			$err_str = '';
			$value = $this->getValue($key);
			$field_valid = $this->_validator->check($value, $field[self::F_CHECK]);
			if (!$field_valid) {
				//$err_str = 'Поле `' . $field[self::F_TITLE] . '` ' . $this->_validator->last_message;
				$err_str = $this->_validator->last_message;
				//$this->setFieldProperty($key, self::F_ERRORS, $err_str);
				$this->setError($key, $err_str);
			}

			$valid = $field_valid && $valid;
		}
		return $valid;
	}

	/**
	 * 
	 * @return boolean
	 */
	public function isValidZend() {		
		// вначале применяем фильтры
		$this->applyFilters();
		// после проверяем каждое значение на валидность
		$valid = true;
		$stop_validate = false;
		foreach ($this->getFields() as $key => $field) {
			$value = $this->getValue($key);
			$validators = $field[self::F_VALIDATORS];
			$field_valid = true;
			if(!$validators || !is_array($validators)) {
				$field_valid = true;
			} else {
				foreach ($validators as $vdata) {
					 // для каждого валидатора передаются три параметра: 
					 //  1 - название
					 //  2 - параметры валидатора
					 //  3 - флаг останова
					if(!is_array($vdata)) {
						$vdata = array($vdata);
					}
					$vname = $vdata[0];
					$vargs = isset($vdata[1]) ? $vdata[1] : array();					
					$vbreak = isset($vdata[2]) ? $vdata[2] : false;
					
					$res = $this->_validateFieldZend($key, $vname, $value, $vargs);
					$field_valid = $field_valid && $res;
					if(!$res && $vbreak) {
						$stop_validate = true;
						break;
					}
				}
			}
			if($stop_validate) {
				break;
			}

			$valid = $field_valid && $valid;
		}
		return $valid;
	}
	
	/**
	 * 
	 * @param string $className
	 * @return type
	 * @throws Zend_Validate_Exception
	 */
	protected function _validateFieldZend($fieldName, $className, $value, $args) {
        try {
            $class = new ReflectionClass($className);
            if ($class->implementsInterface('Zend_Validate_Interface')) {
                if ($class->hasMethod('__construct')) {
                    $keys    = array_keys($args);
                    $numeric = false;
                    foreach($keys as $key) {
                        if (is_numeric($key)) {
                            $numeric = true;
                            break;
                        }
                    }

                    if ($numeric) {
                        $object = $class->newInstanceArgs($args);
                    } else {
                        $object = $class->newInstance($args);
                    }
                } else {
                    $object = $class->newInstance();
                }
				//pre($object, 'yellow');
                $res =  $object->isValid($value);
				if(!$res) {
					$this->setFieldProperty($fieldName, self::F_ERRORS, $object->getMessages());
				}
				return $res;
            }
        } catch (Zend_Validate_Exception $ze) {
            // if there is an exception while validating throw it
            throw $ze;
        } catch (Exception $e) {
            // fallthrough and continue for missing validation classes
        }
	}

	/**
	 * 
	 * @return array
	 */
	public function getErrors() {
		$errors = array();
		foreach ($this->getFields() as $key => $field) {
			$strerr = $field[self::F_ERRORS];
			if (false !== $strerr) {
				$errors[$key] = $strerr;
			}
		}
		return $errors;
	}
	
	/**
	 * 
	 * @return string
	 */
	public function getErrorsAsJSON() {
		return json_encode($this->getErrors());
	}
	
	/**
	 * 
	 * @return string
	 */
	public function getErrorsAsString() {
		$text = '';
		foreach ($this->getFields() as $key => $field) {
			$strerr = $field[self::F_ERRORS];
			if (false !== $strerr) {
				$ftitle = $field[self::F_TITLE];
				if(empty($ftitle)) {
					$ftitle = $key;
				}
				$text .= '<strong>' . $ftitle . '</strong>: ' . $strerr . '<br/>';
			}
		}
		return $text;
	}

	/**
	 *
	 * @return Maestro_Form
	 */
	public function create() {
		$this->_xml = new Maestro_Dom('1.0', 'UTF-8');
		$this->_xml->formatOutput = true;
		// форма
		$formNode = $this->_xml->addNode($this->_xml, 'objectForm', NULL, $this->getName());
		$this->_xml->addArray($formNode, $this->getAttributes());
		// действие
		$actionType = $this->getActionType();
		// флаги
		$hideEmptyFields = $this->getAttribute('hideEmptyFields');
		// поля
		foreach ($this->getNames() as $name) {
			$props = $this->getField($name);
			$fieldValue = $this->getValue($name);

			// если тип действия формы задан и заданы типы действий поля
			// и при этом первое не включено во второе, то поле пропускаем
			$actions = $this->getFieldProperty($name, self::F_ACTIONS);
			if ($actionType) {
				if ($actions) {
					if (!is_array($actions)) {
						$actions = array($actions);
					}
					if (!in_array($actionType, $actions)) {
						continue;
					}
				}
			}
			
			if($hideEmptyFields && empty($fieldValue)) {
				continue;
			}

			// properties
			$fieldNode = $this->_xml->addNode($formNode, 'objectDefinition', NULL, $name);
			//$this->_xml->addArray($fieldNode, $this->getField($name));
			// add simple (key -> value) properties
			foreach ($props as $property => $val) {
				if (!is_array($val)) {
					$this->_xml->addNode($fieldNode, $property, $val);
				}
			}
			// add check properties
			$checks = $this->getFieldProperty($name, self::F_CHECK);
			$checkNode = $this->_xml->addNode($fieldNode, self::F_CHECK);
			foreach($checks as $check_name => $check_param) {
				if(is_array($check_param)) {
					$checkNodeItem = $this->_xml->addNode($checkNode, $check_name);
					$this->_xml->addArray($checkNodeItem, $check_param, 'item', $fieldValue, true);
				} else {
					if(is_numeric($check_name)) {
						$checkNodeItem = $this->_xml->addNode($checkNode, $check_param, true);
					} else {
						$checkNodeItem = $this->_xml->addNode($checkNode, $check_name, $check_param);
					}
				}
			}
			// add zend errors
			$errors = $this->getFieldProperty($name, self::F_ERRORS);
			if(is_array($errors)) {
				$errorNode = $this->_xml->addNode($fieldNode, self::F_ERRORS);
				foreach($errors as $ei => $error) {
					$error = str_replace('%label%', '\'' . $props[self::F_TITLE] . '\'', $error);
					$error = str_replace('%title%', '\'' . $props[self::F_TITLE] . '\'', $error);
					$this->_xml->addNode($errorNode, 'item', $error, $ei);
				}
			}
			//$this->_xml->addArray($checkNode, $checks);
			// add keys check array
			// для поля `checkboxlist` - свой метод добавления ключей, с проверкой
			// значений, если значение существует (отмечано галочкой), то в элемент ключа добавляем
			// аттрибут отметки:
			//  <check>
			//      <keys>
			//          .....
			//          <item id="3">труляля</item>
			//          .....
			//
			// value
			if (is_array($fieldValue)) {
				$fieldValue = array_map('htmlspecialchars', $fieldValue);
				$vNode = $this->_xml->addNode($fieldNode, 'value');
				$this->_xml->addArray($vNode, $fieldValue, 'item');
			} else {
				if($this->getFieldProperty($name, self::F_READONLY)) {
					$this->_xml->addNode($fieldNode, 'value', $fieldValue);
				} else {
					$this->_xml->addNode($fieldNode, 'value', $fieldValue);// htmlspecialchars($fieldValue));
				}
			}
		}

		if ($this->getAttribute('debug')) {
			pre(htmlspecialchars(htmlspecialchars($this->_xml->saveXML())), '#709f14', 'white');
		}

		return $this;
	}

	/**
	 *
	 */
	public function getDocument() {
		$stylesheet = new DomDocument();
		$stylesheet->formatOutput = true;
		$stylesheet->preserveWhiteSpace = false;

		$type = $this->getType();
		$tplFileName = $this->getAttribute('templateFile');
		if(!$tplFileName) {
			$tplFileName = Maestro_App::getConfig()->Path->templates . "form.{$type}.xsl";		
		}

		if (file_exists($tplFileName)) {
			$stylesheet->load($tplFileName);
		}
		if ($this->getAttribute('debug')) {
			//pre(htmlspecialchars($stylesheet->saveXML()), '#357e9d', 'white');
		}

		$processor = new XSLTProcessor();
		$processor->importStylesheet($stylesheet);

		$processor->setParameter('', 'currentDate', date('d.m.Y'));

		$ddr = $processor->transformToDoc($this->_xml);
		return $ddr;
	}

}

?>
