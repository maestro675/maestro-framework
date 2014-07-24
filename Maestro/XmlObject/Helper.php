<?php
/**
 * Используется для создания хэлпера, если он заполняется в виде dom-документа
 *
 * @author maestro
 */
class Maestro_XmlObject_Helper extends Maestro_Dom implements Maestro_XmlObject_Interface
{
	protected $_name = 'document';
	
	/**
	 *
	 * @var Maestro_Unit
	 */
	protected $params;
	
	/**
	 *
	 * @var string
	 */
	protected $module;

	/**
	 * Устанавливает имя объекта.
	 * Используется для идентификации виджета в `view`.
	 *
	 * @param string $name
	 * @return Maestro_XmlObject_Helper
	 */
	public function setName($name)
	{
		$this->_name = Maestro_Common::filterName($name);
		return $this;
	}

	/**
	 * Возвращает имя объекта
	 *
	 * @return string
	 */
	public function getName()
	{
		return $this->_name;
	}

	/**
	 *
	 * @return Maestro_XmlObject_Helper
	 */
	public function getDocument()
	{
		return $this;
	}
	
	/**
	 *
	 * @param array $params
	 */
	public function setParameters($params) {
		if(!is_array($params)) {
			$params = array($params);
		}
		$this->params = new Maestro_Unit(-1, $params);
	}
	
	/**
	 *
	 * @param string $filename 
	 */
	public function setModuleName($filename) {
		$this->module = $filename;
	}
}
?>
