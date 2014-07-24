<?php
/**
 * Description of Maestro_XmlObject_Interface
 *
 * @author maestro
 */
interface Maestro_XmlObject_Interface
{
	/**
	 * Устанавливает имя объекта
	 * Используется для обработки во `view` конструкций вида <mf:object name="anyname"/>
	 *
	 * @param  $name
	 * @return this
	 */
	public function setName($name);

	/**
	 * Возвращает имя объекта.
	 * Используется для обработки во `view` конструкций вида <mf:object name="anyname"/>
	 *
	 * @return string
	 */
    public function getName();

	/**
	 * Возвращает сформированный DOM-документ для вставки
	 * в результирующий документ
	 *
	 * @return DomDocument
	 */
	public function getDocument();
}
?>
