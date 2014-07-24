<?php
/**
 * DateCommon: класс - набор общих функций для преобразований дат
 *
 * Класс реализован по шаблону singleton
 *
 * @author Sergey Lysiansky, s.lysiansky@ucb-ua.com
 * @version    1.0
 */
class Maestro_DateCommon {
	
	private static $instance;
	private static $monthes = array(
		'ru' => array(
			1 => "январ",
			2 => "феврал",
			3 => "март",
			4 => "апрел",
			5 => "ма",
			6 => "июн",
			7 => "июл",
			8 => "август",
			9 => "сентябр",
			10 => "октябр",
			11 => "ноябр",
			12 => "декабр"
		),
		'ua' => array(
			1 => "сiчня",
			2 => "лютого",
			3 => "березня",
			4 => "квiтня",
			5 => "травня",
			6 => "червня",
			7 => "липня",
			8 => "серпня",
			9 => "вересня",
			10 => "жовтня",
			11 => "листопада",
			12 => "грудня"
		)
	);


	private function __construct () {}
	private function __clone () {}
		
	/**
	* Return the single instance of object
	* 
	* @return object
	*/
	public static function &__instance() {
		if(!isset(self::$instance))
			self::$instance = new self;
		return self::$instance;
	}


	/**
	 * fdate
	 * преобразовывает строку в дату по одному из cледующих шаблонов:
	 * YYYY-MM-DD,  DD.MM.YYYY,  YYYYMMDD
	 */
	public static function fdate($var) {
		//$instance = self::__instance();
		if(empty($var))
			return 0;
		else
		if(substr($var,4,1)=='-')
		{
			$dd=substr($var,8,2);
			$mm=substr($var,5,2);
			$yy=substr($var,0,4);
		}
		else
		if(substr($var,2,1)=='.')
		{
			$dd=substr($var,0,2);
			$mm=substr($var,3,2);
			$yy=substr($var,6,4);
		}
		else
		{
			$dd=substr($var,6,2);
			$mm=substr($var,4,2);
			$yy=substr($var,0,4);
		}
		return mktime(0, 0, 0, $mm, $dd, $yy);
	}

	/**
	 *
	 * @param <type> $var
	 * @return <type>
	 */
	public static function fdate_dbase( $var ) {
		$tmp = trim($var);
		if( '19700101' == $var || empty( $tmp ) || '        ' == $var )
		{
			return null;
		}
		return self::fdate( $var );
	}

	
	/**
	 * преобразует строку DDMMYYHHIISS в формат DD.MM.YY HH:II
	 */
	public static function nbu2str($var) {
		return preg_replace("/(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/", "\\1.\\2.\\3 \\4:\\5", $var);
	}

	/**
	 * Преобразует строку DDMMYYYY в объект DateTime
     * Исходный формат DDMMYYYY используется, например, в файлах статочетности
     *
     * @return Maestro_DateTime object
	 */
	public static function createFromSharp($var) {
		return new Maestro_DateTime(preg_replace("/(\d{2})(\d{2})(\d{4})/", "\\3-\\2-\\1", $var));
	}

	/**
	 * преобразует номер месяца в текстовое представление
	 *
	 * @param integer $m
	 * @param string $case одно из значений `date`|`alone`
	 * @return <type>
	 */
	public static function month2text($m, $case = 'date', $lang = 'ru') {
		$m = (integer)$m;

		if(!isset(self::$monthes[$lang][$m])) {
			return 'unknown';
		}

		$mname = self::$monthes[$lang][$m];
		$msuffix = '';
		if('ru' == $lang)
		{
			if($case=='date')
			{
				$msuffix = ($m==3 || $m==8) ? "а" : "я";
			}
			else
			{
				$msuffix = ($m==3 || $m==8) ? "" : (($m==5) ? "й" : "ь");
			}
		}
		return $mname.$msuffix;
	}

	/**
	 * 
	 * @param type $timestamp
	 * @return type
	 */
	public static function date2text( $timestamp ) {
		$instance = self::__instance();

		$t = date( 'd # Y', $timestamp );
		$t = str_replace( '#', self::month2text( date( 'm', $timestamp ) ), $t );
		return $t;
	}

}