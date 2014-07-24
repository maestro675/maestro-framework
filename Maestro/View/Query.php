<?php

/**
 * Класс генерации фрагмента html-кода страницы из xml-документа.
 * Используется для ajax-запросов.
 *
 *
 * @author Sergey Lysiansky, s.lysiansky@ucb-ua.com
 * @version    1.0
 */
class Maestro_View_Query extends Maestro_View_Base {

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
	protected function prepare() {
		$out = array();
		$out['target'] = isset($_POST['target']) ? $_POST['target'] : 'none';
		$out['qtime'] = number_format(microtime(true) - Maestro_App::$startTime, 4);
		$out['qmem'] = number_format(memory_get_peak_usage(true) / 1024 / 1024, 2, ',', ' ') . 'Mb';
		$out['onokfunc'] = $this->controller->getActionTag('onokfunc');

		foreach ($this->params as $key => $value) {
			$out[$key] = $value;
		}

		//header('X-JSON: ('.json_encode($out).')');
		header('X-JSON: ' . json_encode($out));
	}

}
