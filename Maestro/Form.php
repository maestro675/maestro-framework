<?php
/**
 * Description of Form
 *
 * @author maestro
 */
class Maestro_Form {
	
	/**
	 * Создает и возвращает объект класса Maestro_XmlObject_Form
	 * 
	 * @return \Maestro_XmlObject_Form
	 */
	public static function form($name = NULL) {
		$instance = new Maestro_XmlObject_Form();
		if($name) {
			$instance->setName($name);
		}
		return $instance;
	}
}

?>
