<?php
/**
 * ViewFactory: фабрика по созданию экземпляра класса вывода
 *
 * @author Sergey Lysiansky, s.lysiansky@ucb-ua.com
 * @version    1.0
 */
class Maestro_View_Factory
{
	/**
	 *
	 */
	private function __construct() {}
	
	/**
	 * object create()
	 * непосредственно фабрика
	 */
	public static function factory(Maestro_Controller_Base $controller, $inmode=null)
	{
		if(is_null($inmode)) {
			$mode = Maestro_App::getRequest()->getIsAjaxRequest() ? 'query' : 'page';
		} else {
			$mode = $inmode;
		}
		if(!$mode) {
			$mode = 'page';
		}
		$classname = '';
		try
		{
			if($mode=='xhtml' || $mode=='query')
				$view = new Maestro_View_Query( $controller, $mode );
			else
			if($mode=='cache')
				$view = new Maestro_View_Cache( $controller, $mode );
			else
			if($mode=='widget')
				$view = new Maestro_View_Widget( $controller, $mode );
			else
			if($mode=='file')
				$view = new Maestro_View_File( $controller, $mode );
			else
			if($mode=='page')
				$view = new Maestro_View_Page( $controller, $mode );
			else
			if($mode=='echo')
				$view = new Maestro_View_Echo( $controller, $mode );
			else
				throw new Maestro_Exception(get_class().sprintf(': Unknown mode `%s`', $mode));

			return $view;
		}
		catch(Exception $e)
		{
			//Errors::create('Error on view factory: '.$classname);
			throw new Exception('Factory ('.$classname.'): '.$e->getMessage());
			return false;
		}
	}  
}