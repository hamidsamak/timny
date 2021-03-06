<?php

/*
 * Timny 0.7
 * tiny php framework
 * Hamid Reza Samak
 * https://github.com/hamidsamak/timny
 */

// constatns
define('DS', DIRECTORY_SEPARATOR);
define('PATH', str_replace('\\', '/', dirname(__FILE__)));
define('BASE', str_replace($_SERVER['DOCUMENT_ROOT'], '', PATH));

// configuration
define('TITLE', 'Timny'); // defualt pages title
define('LANGCODE', 'en'); // default langauge iso-639-1 code
define('DIRECTION', 'ltr'); // default pages direction
define('EXTENSIONS', 'php,inc'); // extension types (seperate with comma)
define('INITIALIZE', PATH . DS . 'misc' . DS . 'init.php'); // initialize file path
define('INDEX_TEMPLATE', PATH . DS . 'misc' . DS . 'index.tpl'); // index template file path
define('PAGE_TEMPLATE', PATH . DS . 'misc' . DS . 'page.tpl'); // page template file path
define('GLOBAL_FILE', 'global'); // global file name
define('EXTS_AUTO_LOAD', true); // automatic inclusion for files inside exts directory
define('TIMEZONE', 'UTC'); // default time zone

// 404 Error
define('ERROR', 'Error'); // error title
define('PAGE_NOT_FOUND', 'Page not found'); // error content

class Timny {
	public $file;
	public $query;
	public $included = array();
	
	public $head;
	public $title;
	public $langcode = LANGCODE;
	public $direction = DIRECTION;
	
	public function load_exts() {
		if (file_exists(PATH . DS . 'exts') && is_dir(PATH . DS . 'exts')) {
			$exts = explode(',', EXTENSIONS);
			$files = scandir(PATH . DS . 'exts');
			asort($files);
			
			foreach ($files as $file)
				if (in_array(substr($file, strrpos($file, '.') + 1), $exts))
					require_once PATH . DS . 'exts' . DS . $file;
			
			return true;
		}
		
		return false;
	}
	
	public function parse_url($query) {
		$query = empty($query) ? array('default') : explode('/', str_replace('\\', '', $query));
		
		foreach ($query as $key => $value)
			if (substr($value, 0, 1) === '.' || empty($value))
				unset($query[$key]);
		
		$query = array_values($query);
		$this->file = PATH . DS . 'data' . DS . implode('/', $query);

		if (is_dir($this->file)) {
			$this->file .= DS . 'default';
			$query[count($query)] = 'default';
		}
		
		$this->query = array_map('htmlspecialchars', $query);
		
		return true;
	}
	
	private function parse_tpl_tags($tpl, $tags) {
		foreach ($tags as $tag) {
			$length = strlen($tag);
			
			foreach (array('<' . $tag . '>', '</' . $tag . '>') as $key => $value)
				$position[$key] = strpos($tpl['content'], $value);
			
			if ($position[0] < $position[1]) {
				$tpl[$tag] = substr($tpl['content'], $position[0] + $length + 2, $position[1] - $position[0] - $length - 2);
				$tpl['content'] = substr($tpl['content'], 0, $position[0]) . trim(substr($tpl['content'], $position[1] + $length + 3));
			}
		}
		
		return $tpl;
	}
	
	private function include_file($file) {
		$file = substr($this->file, 0, strrpos($this->file, '/')) . $file;
		
		if (file_exists($file) === false)
			die('<b>Timny error:</b> file missing "' . $file . '"');
		else if ($file === $this->file . '.tpl')
			die('<b>Timny error:</b> self inclusion "' . $file . '"');
		else if (in_array($file, $this->included))
			die('<b>Timny error:</b> already included "' . $file . '"');
		
		$this->included[] = $file;
		$ext = substr($file, strrpos($file, '.') + 1);
		
		if ($ext === 'php') {
			ob_start();
			include $file;
			$content = '\'' . ob_get_contents() . '\'';
			ob_end_clean();
		} else if ($ext === 'tpl') {
			$tpl = $this->parse_tpl($file, array('php'));
			
			if (isset($tpl['php']))
				eval($tpl['php']);
			
			$content = '\'' . eval('return \'' . $tpl['content'] . '\';') . '\'';
		} else
			$content = '\'' . file_get_contents($file) . '\'';
		
		return $content;
	}
	
	private function parse_tpl($file, $tags) {
		$tpl['content'] = file_get_contents($file);
		$tpl = $this->parse_tpl_tags($tpl, $tags);
		$tpl['content'] = explode('{', $tpl['content']);
		
		foreach ($tpl['content'] as $value) {
			$value = explode('}', $value);
			
			if (count($value) === 1)
				$php[] = str_replace(array('\'', '&#123;', '&#125;'), array('\\\'', '{', '}'), $value[0]);
			else {
				if (substr($value[0], 0, 1) === '/')
					$value[0] = $this->include_file($value[0]);
				
				$php[] = '\' . ' . $value[0] . ' . \'' . str_replace(array('\'', '&#123;', '&#125;'), array('\\\'', '{', '}'), $value[1]);
			}
		}
		
		$tpl['content'] = implode($php);
		
		return $tpl;
	}
	
	public function load_file() {
		if (file_exists($this->file . '.tpl')) {
			$tpl = $this->parse_tpl($this->file . '.tpl', array('php', 'head', 'title', 'direction'));
			
			if (isset($tpl['php']))
				eval($tpl['php']);
			
			$this->head = @$tpl['head'];
			$this->title = @$tpl['title'];
			
			if (isset($tpl['direction']))
				$this->direction = $tpl['direction'];
			
			$this->content = eval('return \'' . $tpl['content'] . '\';');
		} else if (file_exists($this->file . '.php')) {
			ob_start();
			include $this->file . '.php';
			$content = ob_get_contents();
			ob_end_clean();
			
			$this->content = @$this->content . @$content;
		} else {
			$count = count($this->query);
			if ($count > 0)
				for ($i = $count; $i > 0; $i--) {
					for ($j = 0; $j < $i - 1; $j++)
						$file[] = $this->query[$j];
					
					$file = isset($file) ? implode('/', $file) . '/' : null;
					$file = PATH . DS . 'data' . DS . $file . GLOBAL_FILE;

					if (file_exists($file . '.tpl') || file_exists($file . '.php')) {
						$this->file = $file;
						return $this->load_file();
					}
					
					unset($file);
				}
			
			header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found', true, 404);

			$this->title = ERROR;
			$this->content = PAGE_NOT_FOUND;
		}
		
		$this->title = empty($this->title) ? TITLE : $this->title;
		
		return true;
	}
	
	public function template() {
		if (isset($this->template) && empty($this->template) === false)
			$template_file = $this->template;
		else if ((count($this->query) > 1 || $this->query[0] != 'default') && file_exists(PAGE_TEMPLATE))
			$template_file = PAGE_TEMPLATE;
		else
			$template_file = INDEX_TEMPLATE;

		if (file_exists($template_file)) {
			$template = $this->parse_tpl($template_file, array('php'));
			
			if (isset($template['php']))
				eval($template['php']);
			
			if (file_exists($this->file . '.js'))
				$this->head .= '<script type="text/javascript" src="' . BASE . '/data/' . implode('/', $this->query) . '.js"></script>';
			
			if (file_exists($this->file . '.css'))
				$this->head .= '<link rel="stylesheet" type="text/css" href="' . BASE . '/data/' . implode('/', $this->query) . '.css">';
			
			return eval('return \'' . $template['content'] . '\';');
		} else
			die('<b>Timny error:</b> template missing "' . $template_file . '"');
		
		return false;
	}
}

$timny = new Timny;

if (EXTS_AUTO_LOAD === true)
	$timny->load_exts();

$timny->parse_url(isset($_GET['q']) ? $_GET['q'] : null);

date_default_timezone_set(TIMEZONE);

if (file_exists(INITIALIZE))
	require_once INITIALIZE;

$timny->load_file();

print $timny->template();

?>