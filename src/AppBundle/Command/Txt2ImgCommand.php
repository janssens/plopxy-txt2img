<?php
// src/AppBundle/Command/Txt2ImgCommand.php
namespace AppBundle\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

use Michelf\Markdown;
use ImagickDraw;
use ImagickPixel;
use DOMDocument;
use DOMNode;

class Txt2ImgCommand extends Command
{
	private $_params;
	private $_draw;
	
	private $_min_x;
	private $_min_y;
	private $_x;
	private $_y;
	private $_nextY;
	private $_ascender;

	private $_isUl;

	//dimentions
	private $_width;
	private $_height;

    private $_content_max_width;

	private $_output;
	private $_input;

	protected function configure()
	{
		$this
		->setName('txt2img')
		->setDescription('make an img from txt')
		->setHelp("This command allows you to create an img such as a png containing a given text...")
		->addArgument('text', InputArgument::REQUIRED, 'The text or text file')
		->addOption('style','s',InputOption::VALUE_OPTIONAL,'The style','{}')
		->addOption('format','f',InputOption::VALUE_OPTIONAL,'Output Format','png')
		->addOption('output','o',InputOption::VALUE_OPTIONAL,'Output file','')
		->addOption('encoding','e',InputOption::VALUE_OPTIONAL,'Encoding','UTF-8')
		->addOption('fontSizeMax',NULL,InputOption::VALUE_OPTIONAL,'Max font size','')
		->addOption('wrap','w',InputOption::VALUE_NONE,'should we wrap')
		->addOption('fit',NULL,InputOption::VALUE_NONE,'fit output to background-image');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$default_params = array(
			'font-family' => "Times",
			'color' => "#000000",
			'font-size' => "20px",
			'font-weight' => 500,
			'padding-top' => '0px',
			'padding-left' => '0px',
			'padding-right' => '0px',
			'padding-bottom' => '0px',
			'font-style' => 'normal',
			'text-align' => 'left',
        );


		$this->_output = $output;
		$this->_input = $input;
		$this->_isUl = false;

	    $style = $input->getOption('style');

	    $style = json_decode($style, true);
        if (!is_array($style))
            $style = array();
	    if ($this->isVerbose())
			$output->writeln('<info>'.json_encode($style).'</info>');

        $this->_params = array_merge($default_params,$style);
        if (isset($style["padding"])&&!isset($style["padding-left"]))
            $this->_params["padding-left"] = $style["padding"];
        if (isset($style["padding"])&&!isset($style["padding-right"]))
            $this->_params["padding-right"] = $style["padding"];
        if (isset($style["padding"])&&!isset($style["padding-top"]))
            $this->_params["padding-top"] = $style["padding"];
        if (isset($style["padding"])&&!isset($style["padding-bottom"]))
            $this->_params["padding-bottom"] = $style["padding"];

		$this->_min_x = intval($this->_params['padding-left']);
		$this->_min_y = intval($this->_params['padding-top']);

        $text = $input->getArgument('text');
	    if (file_exists($text)){
	    	$filename = $text;
			$handle = fopen($filename, "r");
			$text = fread($handle, filesize($filename));
			fclose($handle);
	    }

	 	if ($input->getOption('verbose'))
	    	$output->writeln(['============','Text: '.$text]);
        $html = Markdown::defaultTransform($text);
        $html = str_replace("\n", "<br>", $html);
        if ($input->getOption('verbose'))
	    	$output->writeln(['============','Html: '.$html,'============']);

		$transparent = new ImagickPixel('none'); // Transparent
		$this->_draw = new \ImagickDraw();

		/* Font properties */
		$this->_draw->setGravity(\Imagick::GRAVITY_NORTHWEST);
		$this->_draw->setStrokeAntialias(true);
		$this->_draw->setTextAntialias(true);
		
		$doc = new DOMDocument('1.0', $input->getOption('encoding'));
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', $input->getOption('encoding'));
        $doc->loadHTML($html);

        $imagick = new \Imagick();
        if (isset($this->_params["background-image"])){
            $filename = $this->_params["background-image"];
            if (strpos($filename, 'url(')==0)
                $filename = substr($filename, 4,-1);
            $imagick->readImage($filename);
            if ($this->_input->getOption("fit")){
                $d = $imagick->getImageGeometry();
                $this->_params["max-height"] = $d['height'];
                $this->_params["max-width"] = $d['width'];
            }
        }

        $this->_y = 0;

        // DRAW TEXT :
        $this->drawDomNode($doc);
        // TEXT OK


        if (isset($this->_params["max-width"]))
            $this->_width = intval($this->_params["max-width"]);
        if (isset($this->_params["max-height"]))
            $this->_height = intval($this->_params["max-height"]);

		if (!isset($this->_params["background-image"])){
			if (isset($this->_params['background-color']))
				$imagick->newImage($this->_width, $this->_height, new ImagickPixel($this->_params['background-color']));
			else {
				if ($this->_input->getOption("format") == "png")
					$imagick->newImage($this->_width, $this->_height, $transparent);
				else
					$imagick->newImage($this->_width, $this->_height, new ImagickPixel("#ffffff"));
			}
			    
	    }

	    if ($this->isVerbose())
            $this->_output->writeln("finale output is w".$this->_width." and h".$this->_height);

        //crop
	    $imagick->cropImage( $this->_width, $this->_height , 0 , 0 );
	    $imagick->setImageFormat($this->_input->getOption("format"));
	    $imagick->drawImage($this->_draw);

	    if ($input->getOption('output')){
	    	if (substr($input->getOption('output'), -4) == '.'.$this->_input->getOption("format"))
	    		$full_path = $input->getOption('output');
	    	else
                $full_path = $input->getOption('output').".".$this->_input->getOption("format");
			$file = fopen($full_path,"w");
			fwrite($file, $imagick->getImageBlob());
			fclose($file);
			$this->_output->writeln('<info>Output file: '.$full_path.'</info>');
	    }else{
	    	echo $imagick->getImageBlob();
	    }

    }

    protected function drawDomNode(DOMNode $domNode){
	    foreach ($domNode->childNodes as $node)
	    {
			if($node->hasChildNodes()) {
				$this->drawDomNode($node);
			}else{

				if (!$this->_isUl && strpos($node->getNodePath(),"ul")){
					$this->_isUl = true;
					$this->jumpLine();
				}
				if ($this->_isUl && !strpos( $node->getNodePath(), "ul")){
					$this->_isUl = false;
				}

				if ($node->nodeType == XML_TEXT_NODE && $node->nodeValue){
					$parentNode = $node->parentNode->nodeName;
					
					if ($this->_input->getOption('verbose'))
						echo "<".$parentNode.">\n";
					$txt = $node->nodeValue;

					$local_params = array();
					if ($parentNode == "strong")
						$local_params["font-weight"] = 800;
					if ($parentNode == "em")
						$local_params["font-style"] = "italic";
					if ($parentNode == "sup" || $parentNode == "sub")
						$local_params["font-size"] = intval(intval($this->_params["font-size"])/2)."px";
					if ($parentNode == "li"){
						$txt = " ".$txt;
						$circleSize = intval(intval($this->_params["font-size"])/10);
						$line_center = intval(intval($this->_params["font-size"])/2);
						$this->_draw->circle(
						    $this->_x+intval($circleSize/2), 
                            $this->_y+intval($circleSize/2)+$line_center, 
                            $this->_x+$circleSize , 
                            $this->_y + $line_center + $circleSize);
					}
					$this->drawTxt($txt,array_merge($this->_params,$local_params));
				}
				if ($node->nodeName == "br"){
					$this->jumpLine();
				}
			}
		}
	}

	protected function drawTxt($text,$params = array()){
		if ($text){
//            if ($this->isVerbose())
//                echo "Text : $text\n";

			$specific = array_diff($params, $this->_params);

            if ($this->isVerbose()&&$specific)
                    echo "specific : ".json_encode($specific)."\n";

			$params = $this->checkParams($params);

			if (isset($params['font-weight']))
				$this->_draw->setFontWeight($params["font-weight"]);

			if (isset($params['font-family']))
				$this->_draw->setFontFamily($params["font-family"]);

			if (isset($params['font']))
				$this->_draw->setFont($params["font"]);

			switch ($params['font-style']) {
				case 'italic':
					$this->_draw->setFontStyle(\Imagick::STYLE_ITALIC);
					break;
				case 'oblique':
					$this->_draw->setFontStyle(\Imagick::STYLE_OBLIQUE);
					break;
				default:
					$this->_draw->setFontStyle(\Imagick::STYLE_NORMAL);
					break;
			}

			if ($params["font-size"] != "fit")
				$this->_draw->setFontSize(intval($params["font-size"]));

			if (isset($params['text-decoration'])){
				switch ($params['text-decoration']) {
					case 'line-through':
						$this->_draw->setTextDecoration(\Imagick::DECORATION_LINETROUGH);
						break;
					case 'overline':
						$this->_draw->setTextDecoration(\Imagick::DECORATION_OVERLINE);
						break;
					case 'underline':
						$this->_draw->setTextDecoration(\Imagick::DECORATION_UNDERLINE);
						break;
					default:
						$this->_draw->setTextDecoration(\Imagick::DECORATION_NO);
						break;
				}
			}			

			if (isset($params['text-align'])){
				switch ($params['text-align']) {
					case 'center':
						$this->_draw->setTextAlignment(\Imagick::ALIGN_LEFT);
						break;
					case 'right':
						$this->_draw->setTextAlignment(\Imagick::ALIGN_RIGHT);
						break;
                    case 'justify':
                        $this->_draw->setTextAlignment(\Imagick::ALIGN_LEFT);
                        break;
					default:
					case 'left':
						$this->_draw->setTextAlignment(\Imagick::ALIGN_LEFT);
						break;
				}
			}

			if (isset($params['text-transform'])){
				switch ($params['text-transform']) {
					case 'uppercase':
						$text = strtoupper($text);
						break;
					case 'lowercase':
						$text = strtolower($text);
						break;
					case 'capitalize':
						$text = ucwords($text);
						break;
					default:
						break;
				}
			}

			if (isset($params['color'])){
				$this->_draw->setFillColor(new ImagickPixel($params['color']));
			}

            $this->_content_max_width = intval($params['max-width']) - intval($params['padding-right']) - intval($params['padding-left']);
			if ($this->_content_max_width < 1 && $params['max-width'] > 0){
                die("padding too big, no room for txt");
            }

			$lines = explode("\n", $text);

            $geometry = $this->computeGeometry($lines);

            if ($this->isVerbose())
                echo "geometry : ".json_encode($geometry)."\n";

            $justify_space_width = $geometry['justify_space_width'];
            $fit_font_size = $geometry['fit_font_size'];
            $center_x_index = $geometry['center_x_index'];
            $text_total_height = $geometry['text_total_height'];
//            $text_true_height = $geometry['text_true_height'];
            $line_height = $geometry['line_height'];
            $y_jump = $geometry['y_jump'];

            if ($this->_y == 0){
                $this->_y = $this->_min_y;
                if ($params['vertical-align']=="bottom"){
                    $this->_y = intval($params['max-height']) - intval($params['padding-bottom']) - $text_total_height;
                }else if ($params['vertical-align']=="middle"){
                    $top_space = $params['max-height'] - $params['padding-top'] - $params['padding-bottom'] - $text_total_height;
                    if ($top_space>0){
                        $this->_y += intval($top_space/2);
                    }
                }
            }

            $this->_x = $this->_min_x;

            //$this->point($this->_x,$this->_y);

            $line_count = 0;
            $this->_nextY = $line_height;

            $image = new \Imagick();

			foreach ($lines as $key => $line) {
                $this->_draw->setFontSize($fit_font_size[$line_count]); //SET FONT SIZE
                if (isset($y_jump[$line_count]))
                    $this->_nextY = $y_jump[$line_count];
				if ($key > 0) //jump line
                    $this->jumpLine($params);

				if ($line){ //something to draw
                    //x
                    $this->_x = $this->_min_x;
                    if ($params['text-align'] == 'center')
                        $this->_x += $center_x_index[$line_count];
                    if ($params['text-align'] == 'right')
                        $this->_x += 2*$center_x_index[$line_count];
                    //for each line
                    foreach (explode(' ', $line) as $key => $world) {
                        if (!isset($justify_space_width[$line_count]))
                            $justify_space_width[$line_count] = $justify_space_width[$line_count-1];
                        if ($key > 0)
                            $this->_x += $justify_space_width[$line_count];
                        $metrics = $image->queryFontMetrics($this->_draw, $world);
                        $this->_ascender = $metrics['ascender'];
                        if ($this->_input->getOption("wrap")){ //do we have to deal with overflow ?
                            $max = $this->_content_max_width + $this->_min_x;
                            if ($this->_x + intval($metrics["textWidth"]) > $max){
                                if ($this->isVerbose()){
                                    echo "   >>>   Overflow, jumpLine (".($this->_x + intval($metrics["textWidth"])).">".($max).")\n";
                                }
                                $this->jumpLine($params);
                                $line_count++;
                                $this->_x = $this->_min_x;
                                if ($params['text-align'] == 'center')
                                    $this->_x += $center_x_index[$line_count];
                                if ($params['text-align'] == 'right')
                                    $this->_x += 2*$center_x_index[$line_count];
                            }
                        }
                        //
                        // WRITING WORD
                        $this->_draw->annotation($this->_x , $this->_y + $this->_ascender, $world);
                        //
                        //
                        if ($this->isVerbose()){
                            echo $world;
                            echo " > at (".($this->_x).",".($this->_y).") (w".intval($metrics["textWidth"]).")\n";
                        }
                        $this->_x += intval($metrics["textWidth"]);
                    }
				}
                if ($this->_x > $this->_width)
						$this->_width = $this->_x;
                if ($this->_y > $this->_height)
                    $this->_height = $this->_y;
				$line_count++;
			}
		}//if text
        $this->_height += $this->_ascender + intval($this->_params["padding-bottom"]);
        $this->_width += intval($this->_params["padding-right"]);
//        if ($this->_height < $text_true_height)
//            $this->_height = $text_true_height;

	}

	protected function isSizeFixed(){
	    return ((isset($this->_params["max-width"]))||(isset($this->_params["max-height"]))||(isset($this->_params["background-image"])&&$this->_input->getOption('fit')));
    }

    protected function point($x,$y){
        $backupColor = $this->_draw->getStrokeColor();
        $this->_draw->setStrokeColor("red");
        $this->_draw->line($x-5,$y,$x+5,$y);
        $this->_draw->line($x,$y-5,$x,$y+5);
        $this->_draw->setStrokeColor($backupColor);
    }

	protected function checkParams($params){
		if (isset($params['font-weight'])){
			if (!is_int($params['font-weight'])){
				$this->_output->writeln("font-weight should be a integer 100-900. using 500.");
				$params['font-weight'] = 500;
			} else if ($params['font-weight'] > 900 || $params['font-weight'] < 100) {
				$this->_output->writeln("font-weight should be a integer 100-900. using 500.");
				$params['font-weight'] = 500;
			}
		}
		if (isset($params['font-size'])){
			
		}

		if (isset($params['vertical-align'])){
            if ($params['vertical-align'] == "bottom" || $params['vertical-align'] == "center"){
                if( ! isset($params['max-height']) || $params['max-height'] < 1 ){
                    $this->_output->writeln("<error>using vetical-align you should use background-image or max-height to fix the output height</error>");
                    die();
                }
            }
        }else{
            $params['vertical-align'] = 'top';
        }

        if (   $params["font-size"] == "fit"
            || $this->_input->getOption('wrap')
            || $params['text-align']=='right'
            || $params['text-align']=='center'
            || $params['vertical-align']=='bottom'
            || $params['vertical-align']=='center' ){

		    if( ! isset($params['max-width']) || $params['max-width'] < 1 ){
                $this->_output->writeln("<error>using fit/wrap/text-align you should use background-image or max-width to fix the output width</error>");
                die();
            }

        }else if(!isset($params['max-width']) ){
            $params['max-width'] = 0;
        }
        if ( $this->_input->getOption('wrap') && $params["font-size"] == "fit" ){
            $this->_output->writeln("<error>You cannot use booth wrap and font-size : fit</error>");
            die();
        }
		return $params;
	} 

	protected function jumpLine(){
        if (isset($this->_params['line-height']))
            $this->_nextY = intval($this->_params['line-height']);
        if ($this->isVerbose())
            echo "[[ jump line ]] ".$this->_nextY."\n";
        $this->_y +=  $this->_nextY;
	}

	protected function computeGeometry($lines){
        $index_x = 0;
        $justify_line_index = 0;
        $justify_line_words_count = 0;
        $justify_line_words_total_length = 0;
        $justify_space_width = array();
        $text_total_height = 0;
        $center_x_index = array();
        $center_line_total_length = 0;
        $fit_font_size = array();
        $y_jump = array();

        $image = new \Imagick();

        $metrics = $image->queryFontMetrics($this->_draw, " "); //space metrics
        $space_width = $metrics['textWidth'];
        $line_height = $metrics['characterHeight'];

        $last_line = $lines[count($lines)-1];
        $first_line = $lines[0];

        foreach ($lines as $key => $line){ //foreach line
            $text_total_height += $line_height;
            $center_line_total_length = 0;
            foreach (explode(' ', $line) as $key => $word) { //foreach word
//                echo "$word (".$metrics["textWidth"].") at ".$index_x." \n";
                $metrics = $image->queryFontMetrics($this->_draw, $word); //word metrics
                if ($this->_input->getOption('wrap')){ //if we take care of overflow
                    if ($index_x + intval($metrics["textWidth"]) > $this->_content_max_width ){ //overflow
//                        echo " >> overflow >> ";
//                        echo $center_line_total_length;
//                        echo " >> ";
                        if ($this->_params['text-align'] == 'justify')
                            $justify_space_width[$justify_line_index] = intval(($this->_content_max_width - $justify_line_words_total_length) / ($justify_line_words_count - 1));
                        else
                            $justify_space_width[$justify_line_index] = $space_width;
                        $center_x_index[$justify_line_index] = intval(($this->_content_max_width - $center_line_total_length) / 2);
//                        echo $center_x_index[$justify_line_index]."\n";
                        $fit_font_size[$justify_line_index] = $this->_draw->getFontSize();
                        $index_x = 0;
                        $text_total_height += $line_height;
                        $justify_line_index++;
                        $justify_line_words_total_length = 0;
                        $justify_line_words_count = 0;
                        $center_line_total_length = 0;
                    }
                }
                $index_x += intval($metrics["textWidth"]) + $space_width;
                $justify_line_words_count++;
                $justify_line_words_total_length += intval($metrics["textWidth"]);
                $center_line_total_length += intval($metrics["textWidth"]) + $space_width;
            }
            if ($this->_params["font-size"] == "fit"){
                $textWidth = 0;
                $font_size = 1;
                while ($textWidth < $this->_content_max_width) {
                    $font_size++;
                    $this->_draw->setFontSize($font_size);
                    $metrics = $image->queryFontMetrics($this->_draw, $line);
                    $textWidth = $metrics["textWidth"];
                    if ($this->isVerbose())
                        echo "font size = ".$font_size." text width = ".$textWidth."(max ".$this->_content_max_width.")\n";
                }
                $fit_font_size[$justify_line_index] = $font_size--; //SET FONT SIZE
                $this->_draw->setFontSize($font_size);
                $metrics = $image->queryFontMetrics($this->_draw, $line);
                $y_jump[$justify_line_index] = $metrics["characterHeight"];
                $metrics = $image->queryFontMetrics($this->_draw, " "); //space metrics
                $justify_space_width[$justify_line_index] = $metrics['textWidth'];
                if ($this->isVerbose())
                    $this->_output->writeln("<info>best fit font size is ".$font_size."</info>");
                if ($this->_input->getOption('fontSizeMax')&& $this->_input->getOption('fontSizeMax')<$font_size) {
                    $fit_font_size[$justify_line_index] = intval($this->_input->getOption('fontSizeMax'));
                    if ($this->isVerbose())
                        $this->_output->writeln("<info>but fontSizeMax : " . $this->_input->getOption('fontSizeMax') . "</info>");
                }
            }else{
                if (!isset($fit_font_size[$justify_line_index]))
                    $fit_font_size[$justify_line_index] = $this->_draw->getFontSize();
                $y_jump[$justify_line_index] = $line_height;
            }
            if (!isset($justify_space_width[$justify_line_index]))
                $justify_space_width[$justify_line_index] = $space_width;
        }
        if (!isset($center_x_index[$justify_line_index]))
            $center_x_index[$justify_line_index] = intval(($this->_content_max_width - $center_line_total_length) / 2);
        //$justify_space_width[$justify_line_index+1] = $space_width;

        $geometry = array();
        $geometry['justify_space_width'] = $justify_space_width;
        $geometry['center_x_index'] = $center_x_index;
        $geometry['text_total_height'] = $text_total_height;
        $metrics_first_line = $image->queryFontMetrics($this->_draw, $first_line);
        $metrics_last_line = $image->queryFontMetrics($this->_draw, $last_line);
        $first_line_height = $metrics_first_line["boundingBox"]["y2"] - $metrics_first_line["boundingBox"]["y1"];
        $last_line_height = $metrics_last_line["boundingBox"]["y2"] - $metrics_last_line["boundingBox"]["y1"];
        $geometry['text_true_height'] = $text_total_height-2*$line_height+$first_line_height+$last_line_height;
        $geometry['line_height'] = $line_height;
        $geometry['fit_font_size'] = $fit_font_size;
        $geometry['y_jump'] = $y_jump;

        return $geometry;
    }

    protected function isVerbose(){
        return $this->_input->getOption('verbose');
    }
}