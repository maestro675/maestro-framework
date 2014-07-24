<?php
/**
 * Description of Reflection
 *
 * @author maestro
 */
class Maestro_Reflection extends ReflectionClass
{
    /**
	 * Возвращает значение первого найденного по имени тега из описания класса.
	 *
	 * @param string $str комментарий класса, свойства или метода
	 * @param string $tag имя тега для поиска
	 * @param string|integer $default значение по умолчанию
	 */
	public function getDocTag( $str, $tag, $default=null )
	{
		$matches = array();
		preg_match( "/".$tag."\s(.*)(\\r\\n|\\r|\\n)/U", $str, $matches );
		if( isset( $matches[1] ) )
		{
			return trim( $matches[1] );
		}
		return null;
	}

	public function getDocTags( $str, $tag )
	{
		$res = array();
		$matches = array();
		pre($str);
		//preg_match_all( "#{$tag}\s+([[:alnum:]]+)\s+([[:digit:]]+)\s(.*)(\\r\\n|\\r|\\n)#isU", $str, $matches );
		//preg_match_all( "#{$tag}\s(.*)(\\r\\n|\\r|\\n)#isU", $str, $matches );
		preg_match( "/front\s(.*)(\\r\\n|\\r|\\n)/U", $str, $matches );
		pre($matches);
		/*foreach( $matches[1] as $i => $code )
		{
			$u = new Maestro_Unit( $code );
			$u->access = isset( $matches[2][$i] ) ? trim( $matches[2][$i] ) : -1;
			$u->name   = isset( $matches[3][$i] ) ? trim( $matches[3][$i] ) : -1;
			$res[$code] = $u;
		}*/
		return $res;
	}

	/**
	 * Возвращает информацию о классе
	 * 
	 * @return Maestro_Unit object
	 */
	public function getInfo()
	{
		$_class_comment = $this->getDocComment();
		$_code = $this->getDocTag( $_class_comment, 'code' );

		$u = new Maestro_Unit( $_code );
		$u->package  = $this->getDocTag( $_class_comment, 'package' );
		$u->parent   = $this->getDocTag( $_class_comment, 'parent' );
		$u->descript = $this->getDocTag( $_class_comment, 'descript' );
		return $u;
	}
}
?>
