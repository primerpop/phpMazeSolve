<?php
class maze {
	private $_start_position = array(0,0);
	private $_target_position = array(0,0);
	private $_pix_map = array();
	private $_last_position = NULL;
	
	private $_current_pos = NULL;
	private $_last_error = "";
	private $_moves = array();
	
	private $_gdimage = null;
	
	const openspace = 0;
	private $run_id = 0;
	public function __construct() {
		$this->run_id = time();
		
	}
	
	public function get_last_error() {
		return $this->_last_error;
	}
	public function read_maze_image($file_url) {
		$image_data = file_get_contents($file_url);
		if ($image_data) {
			$this->_gdimage = imagecreatefromstring ( $image_data );
			unset($image_data);
			if ($this->_gdimage) {
				$pixmap = array();
				$img_x = imagesx($this->_gdimage);
				$img_y = imagesy($this->_gdimage);
				for ($x = 0; $x < $img_x; $x++) {
					for ($y = 0; $y < $img_y; $y++) {
						$rgb = imagecolorat($this->_gdimage, $x, $y);
						$r = ($rgb >> 16) & 0xFF;
						$g = ($rgb >> 8) & 0xFF;
						$b = $rgb & 0xFF;
						$pixmap[$x][$y] = !($r+$g+$b);  //flip this, black vs white
						
						
					}
					
				}
				$this->_pix_map = $pixmap;
				//print_r($pixmap);
				return 1;
			}
		}
	}
	public function get_possible_vectors($position) {
		$xplus = 0;
		$xminus = 0;
		$yplus = 0;
		$yminus = 0;
		list($x,$y) = $position;
		if (! isset($this->_pix_map[$x][$y])) {
			die("x = $x and y = $y");
		}
		if ($this->_pix_map[$x][$y] != self::openspace) {
			$this->_last_error = "Must start on an open space.";
			return 0;
		}
		if (isset($this->_pix_map[$x+1][$y])) {
			if ($this->_pix_map[$x+1][$y] == self::openspace) {
				$xplus = 1;
			}
		}
		if (isset($this->_pix_map[$x-1][$y])) {
			if ($this->_pix_map[$x-1][$y] == self::openspace) {
				$xminus = 1;
			}
		}
		if (isset($this->_pix_map[$x][$y+1])) {
			if ($this->_pix_map[$x][$y+1] == self::openspace) {
				$yplus = 1;
			}
		}
		if (isset($this->_pix_map[$x][$y-1])) {
			if ($this->_pix_map[$x][$y-1] == self::openspace) {
				$yminus = 1;
			}
		}
		return array("xplus"=>$xplus,"xminus"=>$xminus,"yplus"=>$yplus,"yminus"=>$yminus);
	}
	public function _log_move($sx,$sy,$dx,$dy,$verb) {
		$possible_vectors = $this->get_possible_vectors(array($sx,$sy));
		if (array_sum($possible_vectors) > 1) {
			$this->_moves[$sx.",".$sy][$verb] = self::space_visited_has_multi_vector;
		} else {
			$this->_moves[$sx.",".$sy][$verb] = self::space_visited;
		}
	
	}
	public function move($x,$y) {
		$move_complete = 0;
		
		$possible_vectors = $this->get_possible_vectors($this->_current_pos);
		if ($x == 1 && $y == 0) {
			if ($possible_vectors["xplus"]) {
				$temp_new_pos = array($this->_current_pos[0] + 1, $this->_current_pos[1]);
				$this->_log_move($this->_current_pos[0], $this->_current_pos[1], $this->_current_pos[0] + 1, $this->_current_pos[1], "xplus");
				$this->_last_position = $this->_current_pos;
				$this->_current_pos = $temp_new_pos; 
				$move_complete = 1;
			}
		} 
		if ($x == -1 && $y == 0) {
			if ($possible_vectors["xminus"]) {
				$temp_new_pos = array($this->_current_pos[0] - 1, $this->_current_pos[1]);
				$this->_log_move($this->_current_pos[0], $this->_current_pos[1], $this->_current_pos[0] - 1, $this->_current_pos[1], "xminus");
				$this->_last_position = $this->_current_pos;
				$this->_current_pos = $temp_new_pos;
				$move_complete = 1;
			}
		}	
		if ($x == 0 && $y == 1) {
			if ($possible_vectors["yplus"]) {
				$temp_new_pos = array($this->_current_pos[0] , $this->_current_pos[1]+ 1);
				$this->_log_move($this->_current_pos[0], $this->_current_pos[1], $this->_current_pos[0], $this->_current_pos[1] + 1, "yplus");
				$this->_last_position = $this->_current_pos;
				$this->_current_pos = $temp_new_pos;
				$move_complete = 1;
			}
		}
		if ($x == 0 && $y == -1) {
			if ($possible_vectors["yminus"]) {
				$this->_log_move($this->_current_pos[0], $this->_current_pos[1], $this->_current_pos[0], $this->_current_pos[1] + 1, "yminus");
				$temp_new_pos = array($this->_current_pos[0] , $this->_current_pos[1]- 1);
				$this->_last_position = $this->_current_pos;
				$this->_current_pos = $temp_new_pos;
				$move_complete = 1;
			}
		}
		return $move_complete;
	}
	private function _log($message) {
		echo $message . "\n\r";
	}
	private function _write_progress_image($vectors) {
		static $clone_image = NULL;
		static $color_array = NULL;
		static $sequence = 0;
		$min = 1;
		$copy = $vectors;
		arsort($copy,SORT_NUMERIC );
			
		$max_iteration = 0;
		foreach  ($copy as $entry => $iteration) {
			$max_iteration = $iteration;
			//echo "image max is $iteration";
			break;
		}
		if ($clone_image == NULL) {
			$max_rgb = 255 * 255 * 255;
			
			
	
			$clone_image = imagecreatetruecolor (imagesx($this->_gdimage), imagesy($this->_gdimage));
			imagecopy($clone_image, $this->_gdimage, 0, 0, 0, 0, imagesx($this->_gdimage), imagesy($this->_gdimage));
			$color_array = array();
			for ($i = 1; $i <= 200; $i++){
				$color = $max_rgb * ($i/200);
				$color = $color + 1000; 
				
				$r = ($color >> 16) & 0xFF;
				$g = ($color >> 8) & 0xFF;
				$b = $color & 0xFF;
				$gd_color = imagecolorallocate($clone_image, $r, $g, $b);
				$color_array[$i] = $gd_color;
			}
			$pg_image = imagecreatetruecolor (imagesx($clone_image), imagesy($clone_image));
			imagecopy($pg_image, $clone_image, 0, 0, 0, 0, imagesx($clone_image), imagesy($clone_image));
		} else {
			$pg_image = imagecreatetruecolor (imagesx($clone_image), imagesy($clone_image));
			imagecopy($pg_image, $clone_image, 0, 0, 0, 0, imagesx($clone_image), imagesy($clone_image));
		}
		
		
		foreach ($vectors as $vector=> $iteration) {
			$iteration_color = ceil(($iteration  / $max_iteration) * 200);
			if ($iteration_color == 0) {
				$iteration_color++;
			}
			//echo "Iteration color is $iteration_color from $iteration / $max_iteration\n\r";
			$parts = explode(",", $vector);
			if (isset($color_array[$iteration_color])) {
				imagesetpixel($pg_image, $parts[0], $parts[1], $color_array[$iteration_color]);
			} else {
				echo "could not get colour for $iteration_color\n\r";
			}
		}
		$padded = 5;
		$cur_len = strlen((string)$sequence);
		$padding = $padded - $cur_len;
		if (!file_exists("./sequence-".$this->run_id)){
			mkdir("./sequence-".$this->run_id);
		}
		imagepng($pg_image,"./sequence-".$this->run_id."/current_progress-".str_repeat("0", $padding)."$sequence.png",0);
		imagedestroy($pg_image);
		$sequence++;
	}
	public function find_path($start_pos, $target_pos) {
		// build queue
		$pos = $target_pos;
		$this->_start_position = $start_pos;
		$this->_target_position =$target_pos;
		$queue = array();
		$iteration = 0;
		$vectors = array();
		$vectors[$target_pos[0]. ",".$target_pos[1]] =0 ;
		$start_time = microtime(1);
		while ($start_pos != $pos) {
			$iteration++;
			//if ($iteration == 10) {
			//	die("stopped");
			//}
			$v_iteration = current($vectors);
			$vpos = key($vectors);
			//foreach ($vectors as $vpos => $v_iteration) {
				
			$parts = explode(",",$vpos);		
		
			$pos = array($parts[0],$parts[1]);
			$cx = $parts[0];
			$cy = $parts[1];
			//echo "queue is at : $iteration, with ". count($vectors) . " vectors, on $cx,$cy\r";
			
			$possible_vectors = $this->get_possible_vectors($pos);

			$pos_vectors = array();
			if ($possible_vectors["xminus"] == 1) {
				$pos_vectors[($cx - 1) . "," . $cy] = $iteration;
			}
			if ($possible_vectors["xplus"] == 1) {
				$pos_vectors[($cx + 1) . "," . $cy] = $iteration;
			}
			if ($possible_vectors["yplus"] == 1) {
				$pos_vectors[($cx) . "," . ($cy+1)] = $iteration;
			}
			if ($possible_vectors["yminus"] == 1) {
				$pos_vectors[($cx) . "," . ($cy-1)] = $iteration;
			}
			
			foreach ($pos_vectors as $pos_vector => $p_iteration) {
				if (isset($vectors[$pos_vector])) {
					// position is already set.
					if ($vectors[$pos_vector] <= $p_iteration) {
						unset($pos_vectors[$pos_vector]);
					//	$this->_log("Dropped $pos_vector on iteration $p_iteration");
					} else {
						$vectors[$pos_vector] = $p_iteration;
					//	$this->_log("Added $pos_vector on iteration $p_iteration");
					}
				} else {
					$vectors[$pos_vector] = $p_iteration;
					//$this->_log("Added $pos_vector on iteration $p_iteration");
				}
			}
			
			next($vectors);
			if (($iteration % 1000) == 1) {
				echo "queue is at : $iteration, with ". count($vectors) . " vectors, on $cx,$cy\r";
			//	$this->_write_progress_image($vectors);
			}
		}
		$vectors[$pos] = $iteration;
		$this->_write_progress_image($vectors);
		$this->solve($vectors);
	}
	private function solve($vectors) {
		$solve_map = & $this->_pix_map;
		foreach ($vectors as $v_pos => $iteration) {
			//var_dump($v_pos);
			if (is_string($v_pos)){
				$parts = explode(",",$v_pos);
				$solve_map[$parts[0]][$parts[1]] = $iteration;
			//	echo "setting " . $parts[0] . ", ". $parts[1] . " to $iteration\n\r";
			} else {
				echo "v_pos was funny: " . print_r($v_pos,true);
			}
		}
		
		$cx = $this->_start_position[0];
		$cy = $this->_start_position[1];
		
		$ex = $this->_target_position[0];
		$ey = $this->_target_position[1];
		
		$clone_image = imagecreate(imagesx($this->_gdimage), imagesy($this->_gdimage));
		imagecopy($clone_image, $this->_gdimage, 0, 0, 0, 0, imagesx($this->_gdimage), imagesy($this->_gdimage));
		$red = imagecolorallocate($clone_image, 255, 0,0);
		//reset($solve_map);
		
		do  {
			$last_x = 0;
			$last_y = 0;
			$values = array();
			// get y - 1 value
			if (isset($solve_map[$cx][$cy - 1])) {
				if ($solve_map[$cx][$cy - 1] > 0) {
					$values["yminus"] = $solve_map[$cx][$cy - 1];
				}
			}
				
			// get y + 1 value
			if (isset($solve_map[$cx][$cy + 1])) {
				if ($solve_map[$cx][$cy + 1] > 0) {
					$values["yplus"] = $solve_map[$cx][$cy + 1];
				}
			}
			// get x - 1 value
			if (isset($solve_map[$cx - 1][$cy])) {
				if ($solve_map[$cx - 1][$cy] > 0) {
					$values["xminus"] = $solve_map[$cx - 1][$cy];
				}
			}
			// get x + 1 value
			if (isset($solve_map[$cx + 1][$cy])) {
				if ($solve_map[$cx + 1][$cy] > 0) {
					$values["xplus"] = $solve_map[$cx + 1][$cy];
				}
			}
			
			if (!count($values)) {
				die("Could not get a direction for $cx,$cy.");
			}
			
			asort($values);
			
			//print_r($values);
			
			
			switch (key($values)) {
				case "xminus":
					$cx = $cx - 1;
					break;
				case "xplus":
					$cx = $cx + 1;
					break;
				case "yminus":
					$cy = $cy - 1;
					break;
				case "yplus":
					$cy = $cy + 1;
					break;
			}
			imagesetpixel($clone_image, $cx, $cy, $red);
			echo "setting $cx, $cy\r";
			echo "($cx != $ex)  ($cy != $ey)";
					
			
		} while (($cx !=$ex) && ($cy !=$ey)) ;
		
		imagepng($clone_image,"solve.png",0);
		imagedestroy($clone_image);
	}
}


/**
$image = "D:/paul/downloads/example_maze_1.png";
$start_pos = array(18,5);
$target_pos = array(996,974);

$image = "./theseus.png";
$start_pos = array(6,6);
$target_pos = array(530,400);
*/

/* $image = "./MAZE.png";
$start_pos = array(9,3);
$target_pos = array(1799,1797);
*/
$image = "./rather-huge-maze-puzzle.bmp";
$target_pos = array(1780,2);
$start_pos = array(520,2379);

/**$image = "./bigmaze.png";
$start_pos = array(7,17);
$target_pos = array(2155,1392);
*/
$maze = new maze();
$maze->read_maze_image($image);
$maze->find_path($start_pos,$target_pos);
sleep(60);