<?php
/**
 * Класс генерации html-кода страницы из xml-документа
 *
 * Используется для создания страницы полностью, включая заголовки, мета данные,
 * тело документа и т.д.
 * Дополнительно обрабатываются конструкции вида <widget uri="/...">
 *
 * @author Sergey Lysiansky, s.lysiansky@ucb-ua.com
 * @version    1.0
 */

class Maestro_View_Page extends Maestro_View_Base
{
	/**
	 *
	 */
	protected function prepare()
	{
		$this->setParam('_scriptTime',  number_format(microtime(true) - Maestro_App::$startTime, 4));
		$this->setParam('_scriptMem',   number_format(memory_get_peak_usage(true) / 1024 / 1024, 2, ',', ' ').'Mb');
		$this->setParam('_userId',      Session::subject());
	}
}
