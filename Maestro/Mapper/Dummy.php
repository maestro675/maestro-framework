<?php
/**
 * Description of Dummy
 *
 * @author maestro
 */
class Maestro_Mapper_Dummy extends Maestro_Mapper_Abstract
{
	protected $_debugMode = true;

	protected $_initTable = array( 'U' => 'USERS' );

	protected $_initFields = array(
		'id' => array(
			'truename'  => 'USERID',
			'type'    => 'integer',
			'primary' => true,
			'check'   => array( 'present', 'integer' )
		),
		'namelast' => array(
			'truename' => 'LAST_NAME',
			'type'   => 'string',
			'title'  => 'Фамилия',
			'check'  => array( 'present', 'length' => 64 )
		)
	);

	/**
	 *
	 */
	public function __construct( $dbname )
	{
		parent::__construct( $dbname );
	}
}
?>
