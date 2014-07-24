<?php
/**
 * CacheView: класс генерации документа из xml-документа путем XSLT-преобразований.
 * Полученный документ сохраняется во временный файл. Пользователю возвращается ссылка на него.
 *
 * Используется для выгрузки данных в файлы MS Excel (xml-формат).
 * Реализует шаблон singleton.
 *
 * @author Sergey Lysiansky, s.lysiansky@ucb-ua.com
 * @version    1.0
 */
	
class Maestro_View_File extends Maestro_View_Base
{
	
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
	protected function prepare(DomDocument $xsl)
	{
		
	}
		
	/**
	 *
	 */
	protected function render()
	{
		ob_end_flush();
	}
}
