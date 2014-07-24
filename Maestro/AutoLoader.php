<?php

/**
 * Определения автоматически подгружаемых классов
 * AutoLoader: класс, реализующий подключение файлов подгружаемых классов.
 *
 * @author Sergey Lysiansky, s.lysiansky@ucb-ua.com
 * @version    1.0
 */
class Maestro_AutoLoader {

	/**
	 *
	 * @param string $class
	 * @return void
	 */
	public static function loadClass($class) {
		if (class_exists($class, false) || interface_exists($class, false)) {
			return;
		}

		$file = str_replace('_', DIRECTORY_SEPARATOR, $class) . '.php';
		include_once $file;
	}

	/**
	 * получает имя класса и пробует загрузить соответствующий файл
	 *
	 * @param string $className имя класса
	 */
	public static function autoload($className) {
		try {
			self::loadClass($className);
			return true;
		} catch (Exception $e) {
			//pre('-------'.$e->getMessage());
			return false;
		}
	}

}

spl_autoload_register(array('Maestro_AutoLoader', 'autoload'));
