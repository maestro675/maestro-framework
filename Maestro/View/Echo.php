<?php
/**
 * Description of Echo
 *
 * @author maestro
 */
class Maestro_View_Echo
{

	/**
	 *
	 * @var Maestro_Controller_Base
	 */
	protected $controller;

	/**
	 *
	 * @var <bool
	 */
    public $withErrors = false;

	protected function prepare()
	{
		$this->setParam('_scriptTime',  number_format(microtime(true) - Maestro_App::$startTime, 4));
		$this->setParam('_scriptMem',   number_format(memory_get_peak_usage(true) / 1024 / 1024, 2, ',', ' ').'Mb');
	}

	/**
	 *
	 */
	public function displayResult()
	{
		$out['target']      = isset($_POST['target']) ? $_POST['target'] : 'none';
		$out['qmem']  = number_format(memory_get_peak_usage(true) / 1024 / 1024, 2, ',', ' ').'Mb';
		$out['qtime'] = number_format(microtime(true) - Maestro_App::$startTime, 4);

		$content = ob_get_contents();
		ob_end_clean();

		//header("X-JSON: (".json_encode($out).")");
		header("X-JSON: ".json_encode($out));
		echo htmlspecialchars_decode($content);
	}
}
?>
