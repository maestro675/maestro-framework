<?php
/**
 * Description of Maestro_DateTime
 *
 * @author maestro
 */
class Maestro_DateTime extends DateTime
{
    /**
     * Свой формат даты
     */
    const DMY = 'd.m.Y';
	
	/**
	 *
	 * @param type $time
	 * @param type $object 
	 */
	/*public function __construct ($time = null, $object = null) {
		if(is_numeric($time)) {
			$time = date('d.m.Y H:i:s', $time);
		}
		parent::__construct($time, $object);
	}*/

    /**
     *
     * @return string
     */
    public function __toString()
    {
        return $this->format(self::DMY);
    }

    /**
     * Возвращает строку даты и времени с использованием слова "сегодня"
	 * 
     * @return string
     */
    public function humanity($prefix = 'в')
    {
		$f = $this->format('d.m.Y H:i');
		$old_f = $f;
        $f = str_replace(date('d.m.Y'), 'сегодня ' . $prefix, $f);
		if($f == $old_f) {
			$old_f = $f;
			$f = str_replace(date('d.m.Y', time()-60*60*24), 'вчера ' . $prefix, $f);
		}
		if($f == $old_f) {
			$old_f = $f;
		    $f = str_replace(date('d.m.Y', time()+60*60*24), 'завтра ' . $prefix, $f);
		}
        return $f;
    }
	
	/**
	 *
	 * @param string $lang
	 * @return string
	 */
	public function monthly($lang = 'ru', $show_time = true) {
		$mtext = Maestro_DateCommon::month2text($this->format('m'), 'date', $lang);
		if($show_time) {
			return sprintf($this->format('d %\s Y, H:i'), $mtext);
		} else {
			return sprintf($this->format('d %\s Y'), $mtext);
		}
	}
	
	/**
	 * 
	 * @param type $to_time
	 * @return type
	 */
	public function monthly_period($to_time) {
		if($to_time instanceof DateTime) {
			$to_time = $to_time->getTimestamp();
		}
		
		$mtext = Maestro_DateCommon::month2text($this->format('m'));
		if($this->format('dmY') == date('dmY', $to_time)) {
			return sprintf($this->format('d %\s Y, с H:i') . ' по %s', $mtext, date('H:i', $to_time));
		} else {
			$m2text = Maestro_DateCommon::month2text(date('m', $to_time));
			return sprintf($this->format('с d %\s Y, H:i'), $mtext) . sprintf(date(' по d %\s Y, H:i', $to_time), $m2text);
		}
	}
}
?>
