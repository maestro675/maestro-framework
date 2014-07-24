<?php
/**
 * Description of Maestro_Exception
 *
 * @author maestro
 */
class Maestro_Exception extends Exception
{
	/**
	 *
	 * @var string
	 */
	protected $header = 'Exception error';

	/**
	 *
	 * @param string $str 
	 */
	public function setHeader($str) {
		$this->header = $str;
	}
	
	/**
	 *
	 * @return string
	 */
	public function getHeader() {
		return $this->header;
	}
}
?>
