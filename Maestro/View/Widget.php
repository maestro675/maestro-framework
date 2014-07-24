<?php

/**
 * Класс генерации фрагмента html-кода страницы из xml-документа.
 * Результирующий документ не выводится, а заносится в конфигурацию Maestro_Config, откуда
 * уже извлекается родительским объектом view.
 *
 *
 * @author Sergey Lysiansky, s.lysiansky@ucb-ua.com
 * @version    1.0
 */
class Maestro_View_Widget extends Maestro_View_Base {

	/**
	 * Возвращает путь к файлу враппера
	 *
	 * @return string
	 */
	protected function wrapperfile() {
		return Maestro_App::getConfig()->Path->templates . 'wrapper.query.xsl';
	}

	/**
	 * Возвращает путь к файла слоя относительно файла враппера
	 *
	 * @return string
	 */
	protected function basefile() {
		return false;
	}

	/**
	 *
	 * @param DomDocument $xsl
	 */
	protected function prepare() {
		
	}

	/**
	 *
	 */
	protected function render() {
		//Zend_Registry::set('Widgets.current', $this->document);
		if ('xml' == $this->controller->getActionTag('debug')) {
			pre(str_replace("\n", "<br>", htmlspecialchars($this->controller->getXml()->saveXML())), '#75427b', 'white');
			//Zend_Registry::set('Widgets.debug', str_replace("\n", "<br>", htmlspecialchars($this->controller->getXml()->saveXML())));
		}
		echo htmlspecialchars_decode($this->document->saveHTML());
	}

}
