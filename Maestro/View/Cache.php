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

class Maestro_View_Cache extends Maestro_View_Base
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
	 */
	protected function prepare()
	{

	}

	/**
	 *
	 */
	protected function render()
	{
		// формируем имя выходного файла
		$filename = Maestro_App::getConfig()->Path->cache.date("YmdHis").".".strtolower($_SESSION['user_login'].".xml");
		// результат AJAX-запроса будет перенаправлен на созданный файл
		$this->setParam('onokfunc', "location.href='/cache/".basename($filename)."'");

		$this->setParam('action',     $this->controller->getFront()->getAction() );
		$this->setParam('include',    $this->origin_inc_file);

		$this->setStylesheet();
		$this->setProcessor();
		//$this->setUserFoto();

		$out = array();
		foreach($this->params as $key=>$value)
			$out[$key] = $value;
		$out['target'] = isset($_POST['target']) ? $_POST['target'] : 'none';
		$out['qtime'] = number_format( microtime(true) - Maestro_App::$startTime, 4 );
		$content = ob_get_contents();
		ob_end_clean();

		// формируем выходной документ
		$cache = htmlspecialchars_decode($this->proc->transformToXML($this->controller->getXml()));
		//header("X-JSON: (".json_encode($out).")");
		header("X-JSON: ".json_encode($out));
		echo $content;
		// создаем выходной файл
		$handle = fopen($filename, 'w');
		fwrite($handle, $cache);
		fclose($handle);
	}
}
