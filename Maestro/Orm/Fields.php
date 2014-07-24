<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 *
 *
 * Description of Field
 *
 * @author maestro
 */
class Maestro_Orm_Field
{
	public $id;
	public $class;
	public $name;
	public $table;
	public $realname;
	public $type;
	public $descript;
	public $width;
	public $scale;
	public $enabled;
	public $f_hide;
	public $f_allow_empty;
	public $f_hide_grid;
	public $f_class_def;
	public $f_primary_key;
	public $f_secondary_key;
	public $f_show_ptr_name;
}

/**
 *
 * Description of Fields
 *
 * @author maestro
 */
class Fields
{
	static $HIDE          = 1;
	static $ALLOW_NONE    =  2;
	static $HIDE_GRID     =  4;
	static $CLASS_DEF     =  8;
	static $PRIMARY_KEY   = 16;
	static $SECONDARY_KEY = 32;
	static $HOW_PTR_NAME  = 64;


    /**
	 *
	 *
	 * @var DBSimple object 
	 */
	private $_base;
	private $_classid;

	/**
	 *
	 * @param DBSimple $base
	 * @param integer|null $classid
	 */
	public function __construct( &$base, $classid=null )
	{
		$this->_base = $base;
		if( !is_null( $classid ) )
		{
			$this->setClassId( $classid );
		}
	}

	/**
	 *
	 * @param integer $classid
	 */
	public function setClassId( $classid )
	{
		$this->_classid = $classid;
	}

	/**
	 *
	 * @return array of Field objects 
	 */
	public function get()
	{
		$query  = " select FIELDNAME as ARRAY_KEY, FIELDNAME, OWNERID, FIELDID, PRTABLE, \n".
                  "        PRNAME, TYPEID, TYPECODE, DESCRIPT, WIDTH, SCALE, FLAG, ENABLED, PARENTLEVEL \n".
			      " from GET_PROPFIELDS(?d)";
		$query .= " order by PARENTLEVEL desc, FIELDID";
		$fields = $instance->Base->select($query, $cid);
		return $fields;
	}

	/**
	 *
	 * @param array $info
	 */
	private function _createUnit( $info )
	{
		$f = new Field();
		$f->id         = $info['FIELDID'];
		$f->class      = $this->_classid;
		$f->name       = $info[''];
		$f->table      = $info[''];
		$f->realname   = $info[''];
		$f->type       = $info[''];
		$f->descript   = $info[''];
		$f->width      = $info[''];
		$f->scale      = $info[''];
		$f->flag       = $info[''];
		$f->enabled    = $info[''];

	}

}
?>
