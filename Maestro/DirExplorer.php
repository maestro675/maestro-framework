<?php
/**
 * DirExplorer: просмотр содержимого каталога
 *
 * @author Sergey Lysiansky, s.lysiansky@ucb-ua.com
 * @version    1.0
 */
 
class Maestro_DirExplorer
{
	public $root;
	private $urlprefix;
	
	/**
	 * конструктор класса
	 *
	 * @param string $root_dir корневой каталог для просмотра
	 * @param string $url_pref каталог-путь для ссылок
	 */
	public function __construct($root_dir, $url_pref='')
	{
		$this->root = DIRECTORY_SEPARATOR.trim($root_dir, DIRECTORY_SEPARATOR);
		$this->urlprefix = DIRECTORY_SEPARATOR.trim($url_pref, DIRECTORY_SEPARATOR);
	}

	/**
	 *
	 * @return array
	 */
	public function getFiles()
	{
		$files = array();
		$rows = $this->getcontent( $this->root, false, false );

		if( $rows )
		foreach($rows as $row)
		{
		    $name = substr($row, 2);
		    $type = substr($row, 0, 1);
		    if(substr($name, 0, 1)=='.') continue;

		    if($type=='f')
		    {
				$files[] = $name;
		    }
		}
		return $files;
	}
	
	/**
	 * просмотр файлов в указанном каталоге
	 *
	 * @param string $subdir просматриваемая подпапка корневого каталога
	 */
	public function view($subdir='')
	{
		echo "<table border=0 width=\"100%\"><tr><td width=\"20\"></td><td width=\"80%\"></td><td width=\"10%\"></td><td width=\"10%\"></td></tr>";
		$icondir = "/common/data/places/16/";
		$subdir = trim($subdir, "/");
		$dir = $this->root.DIRECTORY_SEPARATOR.$subdir;;
		$files = $this->getcontent($dir);

		//cm_preprint($subdir);
		//cm_preprint($dir);
		foreach($files as $file)
		{
			$prefix = substr($file, 0, 1);
			$fname = substr($file, 2);
			
			// parent directory
			if($fname == '..')
			{
				$icon = "folder.png";
				$outname = "Parent Directory";
				$fsize = "";
				$fdate = "";
				continue;
			}
			else
			// hiddens and current dir
			if(substr($fname, 0, 1) == '.')
			{
				continue;
			}
			else
			// directory
			if($prefix == 'd')
			{
				$icon = "folder.png";
				$outname = "<b>".$fname."</b>";
				$fsize = "";
				$fdate = "";
			}
			// file
			else
			{
				$url  = $this->urlprefix;
                if(!empty($subdir))
                {
                    $url .= DIRECTORY_SEPARATOR.$subdir;
                }
                $url .= DIRECTORY_SEPARATOR.$fname;

				$real_file = $dir.DIRECTORY_SEPARATOR.$fname;
				$icon = "unknown.png";
				$outname = "<a href=\"{$url}\">".$fname."</a>";
				$fsize = sprintf("%.1f Mb",filesize($real_file)/1024/1024);
				$fdate = date("d.m.Y H:i", filemtime($real_file));
			}
			echo "<tr><td><img src=\"{$icondir}{$icon}\"></td><td>{$outname}</td><td nowrap class=\"remark\" align=center>{$fdate}</td><td nowrap align=right>{$fsize}</td></tr>";
		}
		echo "</table>";
	}

	/**
	 * просмотр файлов в указанном каталоге
	 *
	 * @param string $subdir просматриваемая подпапка корневого каталога
	 */
	public function view_compact($subdir='', $col_nums=10)
	{
		echo "<table class='tahoma' border=0 cellspacing='1' cellpadding='5'>";
		$icondir = "/common/data/places/16/";
		$subdir = trim($subdir, "/");
		$dir = $this->root.DIRECTORY_SEPARATOR.$subdir;
		$files = $this->getcontent($dir, false, false);
		//cm_preprint($subdir);
		//cm_preprint($dir);
		$items_count = count($files);
		$items_on_cell = (integer)$items_count/$col_nums;


		echo '<col span="'.$col_nums.'" width="100"/><tr valign="top" class=""><td>';
		$i = 0;
		foreach($files as $file)
		{
			$prefix = substr($file, 0, 1);
			$fname = substr($file, 2);

			if($i>=$items_on_cell)
			{
				echo '</td><td>';
				$i=0;
			}

			// directory
			if($prefix == 'd')
			{
				continue;
				/*$icon = "folder.png";
				$outname = "<b>".$fname."</b>";
				$fsize = "";
				$fdate = "";*/
			}
			// file
			else
			{
				$url = $this->urlprefix.DIRECTORY_SEPARATOR.$subdir.DIRECTORY_SEPARATOR.$fname;
				$real_file = $dir.DIRECTORY_SEPARATOR.$fname;
				$icon = "unknown.png";
				$outname = "<a href=\"{$url}\">".$fname."</a>";
				$fsize = sprintf("%.1f Mb",filesize($real_file)/1024/1024);
			}
			$i++;
			echo $outname.'<br/>';
			//echo "<tr><td><img src=\"{$icondir}{$icon}\"></td><td>{$outname}</td><td nowrap class=\"remark\" align=center>{$fdate}</td><td nowrap align=right>{$fsize}</td></tr>";
		}
		echo "</td></tr></table>";
	}
	
	/**
	 * возвращает массив из элементов содержимого указанного каталога
	 *
	 * @param string $dir каталог просмотра
	 */
	public function getcontent($dir, $with_cd=true, $with_pd=true)
	{
		$files = array();
		if(is_dir($dir))
		if($dh = opendir($dir))
		{
			while(($file = readdir($dh)) !== false)
			{
				if(!$with_cd && $file=='.') continue;
				if(!$with_pd && $file=='..') continue;
				if(is_dir($dir.DIRECTORY_SEPARATOR.$file))
				{
					$file = "d_".$file;
				}
				else
					$file = "f_".$file;
				$files[] = $file;
			}
			closedir($dh);
		}
		sort($files);
		return $files;
	}

	/**
	 * возвращает массив из элементов содержимого указанного каталога
	 *
	 * @param string $dir каталог просмотра
	 */
	public function getFilesList($dir, $key_preg_match=null)
	{
		if(!is_dir($dir))
        {
            return array();
        }

		$files = array();
		if(($dh = opendir($dir)))
		{
			while(($file = readdir($dh)) !== false)
			{
				if($file=='.' || $file=='..')
                {
                    continue;
                }

				if(is_dir($dir.DIRECTORY_SEPARATOR.$file))
				{
					continue;
				}

                $key = false;
                if($key_preg_match && preg_match($key_preg_match, $file, $m))
                {
                    $key = $m[0];
                    pre($key);
                    $files[$key] = $file;
                }
			}
			closedir($dh);
		}
        
		return $files;
	}
}
?>