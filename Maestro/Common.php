<?php

/**
 * Common: класс - набор общих функций
 *
 * Класс реализован по шаблону singleton
 *
 * @author Sergey Lysiansky, s.lysiansky@ucb-ua.com
 * @version    1.0
 */
class Maestro_Common {
	
	const PROXY = 'Proxy';

	private static $instance;

	private function __construct() {
		
	}

	private function __clone() {
		
	}

	/**
	 * Return the single instance of object
	 *
	 * @return object
	 */
	public static function &__instance() {
		if (!isset(self::$instance))
			self::$instance = new self;
		return self::$instance;
	}

	/**
	 * Проверяет переменную на пустоту. Если пусто - возвращает новое значение.
	 * Иначе - исходное значение с обрезанными пробелами
	 *
	 * @param mixed $var проверяемая переменная
	 * @param string $new_value новое значение
	 */
	public static function _empty($var, $new_value = '&#xa0;') {
		$var = trim($var);
		return empty($var) ? $new_value : $var;
	}

	/**
	 * Определяет и возвращает версию браузера
	 *
	 * @return string
	 */
	public static function check_browser() {
		$browsers = array('Opera', 'MSIE 10.0', 'MSIE 9.0', 'MSIE 8.0', 'MSIE 7.0', 'MSIE 6.0', 'MSIE 10.0', 'MSIE 11', 'Chrome', 'Mozilla/5.0');

		$user_agent = Maestro_App::getRequest()->getUserAgent();
		if (!$user_agent || stristr($user_agent, 'uy ego znaet')) {
			return self::PROXY;
		}
		
		if(stristr($user_agent, 'Trident/7.0')) {
			return 'MSIE 11.0';
		}

		foreach ($browsers as $v) {
			if (stristr($user_agent, $v)) {
				if('MSIE 7.0' == $v && stristr($user_agent, 'Trident/5.0')) {
					return 'MSIE 9.0 quirks mode';
				} else 
				if('MSIE 7.0' == $v && stristr($user_agent, 'Trident/4.0')) {
					return 'MSIE 8.0 quirks mode';
				} else 
				if('MSIE 7.0' == $v && stristr($user_agent, 'Trident/6.0')) {
					return 'MSIE 10.0 quirks mode';
				}
				
				return $v;
			}
		}
		return empty($user_agent) ? 'unknown' : substr($user_agent, 200);
	}

	/**
	 *
	 * @param string $str
	 * @param mixed a few params....
	 * @return string
	 */
	public static function sql_placeholder() {
		$args = func_get_args();
		$tmpl = &$args[0];
		$tmpl = str_replace('%', '%%', $tmpl);
		$tmpl = str_replace('?d', '%d', $tmpl);
		$tmpl = str_replace('?', '%s', $tmpl);

		$args_out = array();
		array_push($args_out, $args[0]);

		if (is_array($args[1])) {
			foreach ($args[1] as $v) {
				if (!is_int($v)) {
					$v = "'" . self::sql_addslashes($v) . "'";
				}
				array_push($args_out, $v);
			}
		}
		else {
			foreach ($args as $i => $v) {
				if (!$i) {
					continue;
				}
				if (!is_int($v)) {
					$v = "'" . self::sql_addslashes($v) . "'";
				}
				array_push($args_out, $v);
			}
		}
		for ($i = $c = count($args_out) - 1; $i < $c + 2; $i++) {
			array_push($args_out, 'UNKNOWN_PLACEHOLDER_' . $i);
		}
		return call_user_func_array('sprintf', $args_out);
	}
	
	/**
	 * 
	 * @param string $str
	 * @return string
	 */
	public static function sql_addslashes($str){
		if (is_array($str)) {
			foreach($str AS $id => $value) {
				$str[$id] = addslashes_mssql($value);
			}
		} else {
			$str = str_replace("'", "''", $str);    
		}

		return $str;
	}	
	
	/**
	 *
	 * @param array|string $fields
	 * @param array|string $variants
	 * @return type 
	 */
	public static function sql_search_query($fields, $variants) {
		if(!is_array($fields)) {
			$fields = array($fields);
		}
		
		if(!is_array($variants)) {
			$variants = explode(' ', $variants);
			$variants = array_map('trim', $variants);
		}		
		$where = array();
		foreach($fields as $field) {
			$w = false;
			foreach ($variants as $var) {
				$w[] = self::sql_placeholder("lower({$field}) like lower('%' || ? ||'%')", $var);
			}
			if($w) {
				$where[] = '(' . implode(' and ', $w) . ')';
			}
		}
		if(!count($where)) {
			return false;
		}
		return PHP_EOL . "\t" . implode(' or ' . PHP_EOL . "\t", $where) . PHP_EOL . '  ';
	}
	
	/**
	 * Выделяет в тексте "text" указанные фрагменты из массива "terms".
	 * Фрагменты обрамляются тэгом [span class="search-highlight"] [/span]
	 * 
	 * @param string $text
	 * @param array|string $terms
	 * @return string
	 */
	public static function search_highlight($text, $terms) {
		if(!is_array($terms)) {
			$terms = array($terms);
		}		
		foreach($terms as $term) {
			$term = preg_quote($term, '/');
			$text = preg_replace("@(?<!<|</)($term)@iu", "<span class='search-highlight'>$1</span>", $text);
		}
		return $text;
	}
	
	/**
	 * Выводит блок сообщения.
	 * Типы:
	 *	- /null/
	 *	- error, danger
	 *	- success
	 *	- info
	 * 
	 * @param string $subject
	 * @param string $message
	 * @param string $type 
	 * @param boolean $closable
	 * @param boolean $heading
	 */
	public static function alert($subject, $message, $type = null, $closable = false, $heading = false) {
		$class = ($type) ? ' alert-'.$type : '';
		echo '<div class="alert'.$class.'">';
		if($closable) {
			echo '<button class="close" onclick="$(this).up().remove();">×</button>';
		}
		if($heading) {
			echo '<h3 class="alert-heading">'.$subject.'</h3> <p>'.$message.'</p></div>';
		} else {
			echo '<strong>'.$subject.'</strong> '.$message.'</div>';
		}
	}

	/**
	 *
	 * @param Exception $e
	 */
	public static function showexcept(Exception $e) {
		/*if(Maestro_App::getConfig()->phpSettings->display_errors) {
			echo "\n<pre style=\"background: #51555c; color: #d1d1d1; padding: 6px;\"><div class='box-iconed'><img src='/icon/48/script-error.png'/>";
			//self::_trace_format($e->getTrace());
			echo '<h5 style="margin: 1px 0; color: white">' . $e->getMessage() . '</h5><code style="color: white">' . $e->getFile() . ' (' . $e->getLine() . ')';
			//echo '<span style="color: orange">'.$e->getMessage() . '</span><br/>' . $e->getFile() . ' (' . $e->getLine() . ')';
			echo '<br/><br/>';
			echo $e->getTraceAsString();
			echo "\n</code></div></pre>\n";
		}*/		
//		if(Maestro_App::getConfig()->phpSettings->display_errors) {
		echo '<div class="alert alert-error alert-block"><div class="box box48"><img class="box-icon" src="/icon/48/software-update-urgent.png"/>';
		if($e instanceof Maestro_Exception) {
			echo '<h4 class="alert-heading">' . $e->getHeader() . '</h4> ';
		} 
		echo '<p>' . $e->getMessage() . '</p>';
		if('production' != APPLICATION_ENV) {
			echo '<div><a href="javascript::void(0);" onclick="$(this).next().toggle();"><i class="icon-chevron-down icon-gray"></i></a>';
			echo '<div style="display: none;"><code>'.str_replace(PHP_EOL, '<br/>', $e->getTraceAsString()).'</code></div>';
			echo '</div>';
		} else {
			
		}
		echo '</div></div>';
	}

	/**
	 *
	 * @param integer $step_id
	 * @param
	 */
	public static function backtrace($istep = 0, $return = false) {
		$backtrace = debug_backtrace();
		array_shift($backtrace);
		if($return) {
			return self::_trace_format($backtrace, $istep);
		} else {
			echo self::_trace_format($backtrace, $istep);
			return false;
		}
	}

	/**
	 *
	 * @param array $trace
	 * @param integer $istep
	 */
	private static function _trace_format($trace, $istep = 0) {
		$s = '';
		foreach ($trace as $i => $step) {
			//if(!$i) continue;
			if (!$istep || $i <= $istep) {
				$class = isset($step['class']) ? $step['class'] . '::' : '';
				$file = isset($step['file']) ? $step['file'] : '';
				$line = isset($step['line']) ? $step['line'] : '';
				$func = $step['function'];
				$args = '';
				if ('include' == $func || 'require' == $func) {
					$args = implode('|', $step['args']);
				}
				$s .= sprintf(
					"<div>#%3d %s<b>%s</b> : %s (%s) <b>%s</b></div>", 
					$i, $class, $func, $file, $line, $args
				);
			}
		}
		return $s;
	}

	/**
	 * получаем массив записей из DBF файла
	 *
	 * @param <type> $fname
	 * @param integer $limit
	 * @return <type>
	 */
	public static function dbase_load($fname, $limit = -1) {
		$rows = array();
		$idb = dbase_open($fname, 0);
		if ($idb) {
			$recnum = dbase_numrecords($idb);
			if ($limit <= 0 || $limit > $recnum) {
				$limit = $recnum;
			}
			for ($i = 1; $i <= $limit; $i++) {
				$rows[$i] = dbase_get_record_with_names($idb, $i);
			}
			dbase_close($idb);
			return $rows;
		} else {
			throw new Exception("Ошибка открытия файла `{$fname}`");
			dbase_close($idb);
			return false;
		}
	}

	/**
	 * Возвращает значение первого найденного по имени тега из описания класса.
	 *
	 * @param string $str комментарий класса, свойства или метода
	 * @param string $tag имя тега для поиска
	 * @param string|integer $default значение по умолчанию
	 */
	public static function getDocTag($str, $tag, $default = null) {
		$matches = array();
		preg_match("/" . $tag . "\s(.*)(\\r\\n|\\r|\\n)/U", $str, $matches);
		if (isset($matches[1])) {
			return trim($matches[1]);
		}
		return $default;
	}

	/**
	 * Вытаскивает из строки $str список тэгов, заменяя значения в списке $defaults
	 *
	 * @param string $str    Комментарий класса, свойства или метода
	 * @param string $filter Массив разрешенных тэгов со значениями по умолчанию
	 * @return array
	 */
	public static function getDocTags($str, $defaults) {
		$res = $defaults;
		$matches = array();
		preg_match_all("#([a-zA-Z].*)\s(.+)(\\r\\n|\\r|\\n)#isU", $str, $matches);
		foreach ($matches[1] as $i => $code) {
			if (array_key_exists($code, $defaults)) {
				$result = trim($matches[2][$i]);
				if ('false' == $result) {
					$result = false;
				} elseif ('true' == $result) {
					$result = true;
				}
				$res[$code] = $result;
			}
		}
		return $res;
	}

	/**
	 * Фильтрация имени по разрешенным символам
	 *
	 * @param  string $value
	 * @return string
	 */
	public static function filterName($value) {
		return preg_replace('/[^a-zA-Z0-9_\x7f-\xff]/', '', (string) $value);
	}

	/**
	 * Удаляет в строке $msg атрибуты у тега $tag. Если указан массив тегов $attr - удаляются
	 * только атрибуты из этого списка.
	 *
	 * @param string $msg
	 * @param string $tag
	 * @param array $attr
	 * @param string $suffix
	 * @return string
	 */
	function strip_attributes($msg, $tag, $attr, $suffix = "") {
		$lengthfirst = 0;
		while (strstr(substr($msg, $lengthfirst), "<$tag ") != "") {
			$tag_start = $lengthfirst + strpos(substr($msg, $lengthfirst), "<$tag ");
			$partafterwith = substr($msg, $tag_start);
			$img = substr($partafterwith, 0, strpos($partafterwith, ">") + 1);
			$img = str_replace(" =", "=", $img);
			$out = "<$tag";
			for ($i = 0; $i < count($attr); $i++) {
				if (empty($attr[$i])) {
					continue;
				}
				$long_val = (strpos($img, " ", strpos($img, $attr[$i] . "=")) === FALSE) ? strpos($img, ">", strpos($img, $attr[$i] . "=")) - (strpos($img, $attr[$i] . "=") + strlen($attr[$i]) + 1) : strpos($img, " ", strpos($img, $attr[$i] . "=")) - (strpos($img, $attr[$i] . "=") + strlen($attr[$i]) + 1);
				$val = substr($img, strpos($img, $attr[$i] . "=") + strlen($attr[$i]) + 1, $long_val);
				if (!empty($val)) {
					$out .= " " . $attr[$i] . "=" . $val;
				}
			}
			if (!empty($suffix)) {
				$out .= " " . $suffix;
			}
			$out .= ">";
			$partafter = substr($partafterwith, strpos($partafterwith, ">") + 1);
			$msg = substr($msg, 0, $tag_start) . $out . $partafter;
			$lengthfirst = $tag_start + 3;
		}
		return $msg;
	}

	public static function get_directory($root, $directories = false, $recursive = false) {
		$files = array();
		if (!is_dir($root)) {
			throw new Maestro_Exception('It\'s not a directory: ' . $root);
			return array();
		}
		if (!$dh = opendir($root)) {
			throw new Maestro_Exception('Could not open directory at: ' . $root);
			return array();
		} else {
			while (($file = readdir($dh)) !== false) {
				$path = $root . DIRECTORY_SEPARATOR . $file;
				if (is_dir($path)) {
					if (strcmp($file, ".") == 0 || strcmp($file, "..") == 0) {
						continue;
					}
					if ($directories) {
						if ($recursive) {
							$files[$file] = self::get_directory($path);
						} else {
							$files[$file] = array();
						}
					}
				} else {
					$files[] = $file;
				}
			}
			closedir($dh);
		}
		return $files;
	}
}