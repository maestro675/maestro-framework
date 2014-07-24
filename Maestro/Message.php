<?php
/**
 * Message: класс - набо функций для преобразования текста с bbCode
 *
 * Класс реализован по шаблону singleton
 *
 * @author Sergey Lysiansky, s.lysiansky@ucb-ua.com
 * @version    1.0
 */
class Maestro_Message
{
	private static $instance;
	private function __construct () {}
	private function __clone () {}
		
	/**
	* Return the single instance of object
	* 
	* @return object
	*/
	public static function &__instance()
	{
		if(!isset(self::$instance))
			self::$instance = new self;
		return self::$instance;
	}

	/**
	 * обработка и преобразование bbCode
	 *
	 * @param string $message текст
	 */
	public function decode($message)
	{
		$instance = self::__instance();
		
		function split_text($matches) 
		{
			return wordwrap($matches[1], 35, ' ',1);
		}
		
		// Разрезаем слишком длинные слова
		$message = preg_replace_callback("|([a-zа-я\d!]{35,})|i", "split_text", $message);
					
		// Тэги - [code], [php]
		preg_match_all( "#\[php\](.+)\[\/php\]#isU", $message, $matches );
		$cnt = count( $matches[0] );
		for ( $i = 0; $i < $cnt; $i++ ) {
			$phpBlocks[] = '<div class="codePHP">'.highlight_string( $matches[1][$i], true ).'</div>';
			$uniqidPHP = '[php_'.uniqid('').']';
			$uniqidsPHP[] = $uniqidPHP;
			$message = str_replace( $matches[0][$i], $uniqidPHP, $message ); 
		}
	
		$spaces = array( ' ', "\t" );
		$entities = array( '&nbsp;', '&nbsp;&nbsp;&nbsp;&nbsp;' );
		
		preg_match_all( "#\[code\](.+)\[\/code\]#isU", $message, $matches );
		$cnt = count( $matches[0] );
	
		for ( $i = 0; $i < $cnt; $i++ ) {
			$codeBlocks[] = '<div class="code">'.nl2br( str_replace( $spaces, $entities, htmlspecialchars( $matches[1][$i] ) ) ).'</div>';
			$uniqidCode = '[code_'.uniqid('').']';
			$uniqidsCode[] = $uniqidCode;
			$message = str_replace( $matches[0][$i], $uniqidCode, $message ); 
		}
		
		//$message = htmlspecialchars( $message );
		$message = preg_replace("#\[b\](.+)\[\/b\]#isU", '<b>\\1</b>', $message);
		$message = preg_replace("#\[i\](.+)\[\/i\]#isU", '<i>\\1</i>', $message);
		$message = preg_replace("#\[u\](.+)\[\/u\]#isU", '<u>\\1</u>', $message);
		$message = preg_replace("#\[quote\](.+)\[\/quote\]#isU",'<div class="quoteHead">Цитата</div><div class="quoteContent">\\1</div>',$message);
		$message = preg_replace("#\[quote=&quot;(.+)&quot;\](.+)\[\/quote\]#isU", '<div class="quoteHead">\\1 пишет:</div><div class="quoteContent">\\2</div>', $message);
//		$message = preg_replace("#\[quote=&quot;([- 0-9a-zа-яА-Я]{1,30})&quot;\](.+)\[\/quote\]#isU", '<div class="quoteHead">\\1 пишет:</div><div class="quoteContent">\\2</div>', $message);
		$message = preg_replace("#\[url\][\s]*([\S]+)[\s]*\[\/url\]#isU",'<a href="\\1" target="_blank">\\1</a>',$message);
		$message = preg_replace("#\[url[\s]*=[\s]*([\S]+)[\s]*\][\s]*([^\[]*)\[/url\]#isU",
								'<a href="\\1" target="_blank">\\2</a>',$message);
		$message = preg_replace("#\[img\][\s]*([\S]+)[\s]*\[\/img\]#isU",'<img src="\\1" alt="" />',$message);
		$message = preg_replace("#\[color=red\](.+)\[\/color\]#isU",'<span style="color:#FF0000">\\1</span>',$message);
		$message = preg_replace("#\[color=green\](.+)\[\/color\]#isU",'<span style="color:#008000">\\1</span>',$message);
		$message = preg_replace("#\[color=blue\](.+)\[\/color\]#isU",'<span style="color:#0000FF">\\1</span>',$message);
		$message = preg_replace_callback("#\[list\]\s*((?:\[\*\].+)+)\[\/list\]#siU",'getUnorderedList',$message);
		$message = preg_replace_callback("#\[list=([a|1])\]\s*((?:\[\*\].+)+)\[\/list\]#siU", 'getOrderedList',$message);
		
		$message = nl2br( $message);
		
		if ( isset( $uniqidCode ) ) $message = str_replace( $uniqidsCode, $codeBlocks, $message );
		if ( isset( $uniqidPHP ) ) $message = str_replace( $uniqidsPHP, $phpBlocks, $message );
		
		return $message;
	}
}