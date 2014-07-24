<?php
/**
 * Класс Maestro_Yaml - обертка для класса Yaml.php из внешней библиотеки Symfony\Yaml
 *
 * @author maestro
 */
use Symfony\Component\Yaml\Yaml;

class Maestro_Yaml {
	
	/**
	 * Преобразовывает YAML в массив PHP
	 * 
	 * 
	 * @param string $input путь к файлу YAML или текст, содержащий YAML
	 * @return array
	 */
	public static function parse($input) {
		return Yaml::parse($input);
	}
	
	/**
	 * Выгружает массив PHP в текст YAML
	 * 
	 * 
	 * @param array $array массив PHP
	 * @param integer $inline уровень, на котором переключается на YAML в строку
	 * @param integer $indent количество пробелов для индента
	 * @return string YAML представление оригинального массива PHP
	 */
	public static function dump($array, $inline = 2, $indent = 4) {
		return Yaml::dump($array, $inline, $indent);
	}
}

?>
