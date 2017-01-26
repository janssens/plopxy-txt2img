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
	private $default_params;
	private $draw;
	
	private $minx;
	private $x;
	private $y;
	private $nextY;

	private $maxwidth;
	private $maxheight;

	private $output;
	private $input;

	private $isUl;

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
		->addOption('wrap','w',InputOption::VALUE_NONE,'should we wrap');
	}

	protected function execute(InputInterface $input, OutputInterface $output)
	{
		// TODO Justification (gauche/droite/centré)

		$this->default_params = array(
			'font-family' => "Times",
			'color' => "#000000",
			'font-size' => "20px",
			'font-weight' => 500,
			'padding-top' => '0px',
			'padding-left' => '0px',
			'font-style' => 'normal');


		$this->output = $output;
		$this->input = $input;
		$this->isUl = false;

	    $style = $input->getOption('style');

	    $style = json_decode($style, true);
	    if (is_array($style) && ($this->input->getOption('verbose')))
			$output->writeln('<info>'.json_encode($style).'</info>');
	    if (is_array($style))
		    $this->default_params = array_merge($this->default_params,$style);
		if (is_array($style)){
			if (isset($style["padding"])&&!isset($style["padding-left"]))
				$this->default_params["padding-left"] = $style["padding"];
			if (isset($style["padding"])&&!isset($style["padding-right"]))
				$this->default_params["padding-right"] = $style["padding"];
			if (isset($style["padding"])&&!isset($style["padding-top"]))
				$this->default_params["padding-top"] = $style["padding"];
			if (isset($style["padding"])&&!isset($style["padding-bottom"]))
				$this->default_params["padding-bottom"] = $style["padding"];
		}

		$this->minx = intval($this->default_params['padding-left']);
		$this->miny = intval($this->default_params['padding-top']);
		$this->x = $this->minx;
		$this->y = $this->miny;

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
//        $html = str_replace("\n\n", "<br>\n", $html);
        if ($input->getOption('verbose'))
	    	$output->writeln(['============','Html: '.$html,'============']);

		$transparent = new ImagickPixel('none'); // Transparent


		$this->draw = new \ImagickDraw();

		/* Font properties */
		$this->draw->setGravity(\Imagick::GRAVITY_NORTHWEST);
		$this->draw->setStrokeAntialias(true);
		$this->draw->setTextAntialias(true);
		
		$doc = new DOMDocument('1.0', $input->getOption('encoding'));
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', $input->getOption('encoding'));
        $doc->loadHTML($html);

		$this->drawDomNode($doc);

		$format = $input->getOption("format");

		if (isset($this->default_params["padding-right"]))
			$this->maxwidth = $this->maxwidth + intval($this->default_params["padding-right"]);

		$imagick = new \Imagick();
		if (isset($this->default_params["background-image"])){
			$filename = $this->default_params["background-image"];
			if (strpos($filename, 'url(')==1)
				$filename = substr($filename, 4,-1);
			$imagick->readImage($filename);
		}else{
			if (isset($this->default_params['background-color']))
				$imagick->newImage($this->maxwidth, $this->maxheight, new ImagickPixel($this->default_params['background-color']));
			else {
				if ($format == "png")
					$imagick->newImage($this->maxwidth, $this->maxheight, $transparent);
				else
					$imagick->newImage($this->maxwidth, $this->maxheight, new ImagickPixel("#ffffff"));
			}
			    
	    }
	    if (isset($this->default_params["max-width"]))
	    	$this->maxwidth = intval($this->default_params["max-width"]);
	    if (isset($this->default_params["max-height"]))
	    	$this->maxheight = intval($this->default_params["max-height"]);

	    $imagick->cropImage( $this->maxwidth, $this->maxheight , 0 , 0 );
	    $imagick->setImageFormat($format);
	    $imagick->drawImage($this->draw);

	    if ($input->getOption('output')){
	    	if (substr($input->getOption('output'), -4) == '.'.$format)
	    		$fullpath = $input->getOption('output');
	    	else
		    	$fullpath = $input->getOption('output').".".$format;
			$file = fopen($fullpath,"w");
			fwrite($file, $imagick->getImageBlob());
			fclose($file);
			$output->writeln('<info>Output file: '.$fullpath.'</info>');
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

				if (!$this->isUl && strpos($node->getNodePath(),"ul")){
					// echo "enter ul\n";
					$this->isUl = true;
					$this->jumpLine($this->default_params);
				}
				if ($this->isUl && !strpos( $node->getNodePath(), "ul")){
					// echo "\nleave ul\n";
					$this->isUl = false;
					// $this->jumpLine($this->default_params);
				}

				if ($node->nodeType == XML_TEXT_NODE && $node->nodeValue){
					$parentNode = $node->parentNode->nodeName;
					
					if ($this->input->getOption('verbose'))
						echo $parentNode;
					$txt = $node->nodeValue;

					$local_params = array();
					if ($parentNode == "strong")
						$local_params["font-weight"] = 800;
					if ($parentNode == "em")
						$local_params["font-style"] = "italic";
					if ($parentNode == "sup" || $parentNode == "sub")
						$local_params["font-size"] = intval(intval($this->default_params["font-size"])/2)."px";
					if ($parentNode == "li"){
						$txt = " ".$txt;
						$circleSize = intval(intval($this->default_params["font-size"])/10);
						$linecenter = intval(intval($this->default_params["font-size"])/2);
						// echo "circle(".($this->x+intval($circleSize/2)).",".($this->y+intval($circleSize/2)+$linecenter).",".($this->x+$circleSize).",".($this->y + $linecenter +$circleSize).")";
						$this->draw->circle($this->x+intval($circleSize/2), $this->y+intval($circleSize/2)+$linecenter, $this->x+$circleSize , $this->y + $linecenter + $circleSize);
					}
					$this->drawTxt($txt,array_merge($this->default_params,$local_params));
				}
				if ($node->nodeName == "br"){
					$this->jumpLine(array_merge($this->default_params,$local_params));
				}
			}
		}
	}

	protected function drawTxt($text,$params = array()){
		if ($text){
			$specific = array_diff($params, $this->default_params);
			
			
			$params = $this->checkParams($params);

			if (isset($params['font-weight']))
				$this->draw->setFontWeight($params["font-weight"]);

			if (isset($params['font-family']))
				$this->draw->setFontFamily($params["font-family"]);

			if (isset($params['font']))
				$this->draw->setFont($params["font"]);

			switch ($params['font-style']) {
				case 'italic':
					$this->draw->setFontStyle(\Imagick::STYLE_ITALIC);
					break;
				case 'oblic':
					$this->draw->setFontStyle(\Imagick::STYLE_OBLIQUE);
					break;
				default:
					$this->draw->setFontStyle(\Imagick::STYLE_NORMAL);
					break;
			}

			if ($params["font-size"] != "fit")
				$this->draw->setFontSize(intval($params["font-size"]));

			if (isset($params['text-decoration'])){
				switch ($params['text-decoration']) {
					case 'line-through':
						$this->draw->setTextDecoration(\Imagick::DECORATION_LINETROUGH);
						break;
					case 'overline':
						$this->draw->setTextDecoration(\Imagick::DECORATION_OVERLINE);
						break;
					case 'underline':
						$this->draw->setTextDecoration(\Imagick::DECORATION_UNDERLINE);
						break;
					default:
						$this->draw->setTextDecoration(\Imagick::DECORATION_NO);
						break;
				}
			}			

			if (isset($params['text-align'])){
				switch ($params['text-align']) {
					case 'center':
						$this->draw->setTextDecoration(\Imagick::ALIGN_LEFT);
						break;
					case 'right':
						$this->draw->setTextAlignment(\Imagick::ALIGN_RIGHT);
						break;
                    case 'justify':
                        $this->draw->setTextAlignment(\Imagick::ALIGN_LEFT);
                        break;
					default:
					case 'left':
						$this->draw->setTextDecoration(\Imagick::ALIGN_LEFT);
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
						$text = $text; //TODO implement
						break;
					default:
						break;
				}
			}

			if (isset($params['color'])){
				$this->draw->setFillColor(new ImagickPixel($params['color']));
			}
		    
		    /* Get font metrics */
			$image = new \Imagick();

			$lines = explode("\n", $text); 

			
			// var_dump($metrics); textWidth
			if (isset($params['text-align']) && $params['text-align']=='right'){
				foreach ($lines as $key => $line) {
					$metrics = $image->queryFontMetrics($this->draw, $line);
					if ( (intval($metrics["textWidth"]) + intval($params["padding-left"])) > $this->minx)
						$this->minx = intval($metrics["textWidth"]) + intval($params["padding-left"]);
				}
				$this->x = $this->minx;
				$this->maxwidth =  $this->minx + intval($params["padding-right"]);
			}

			if ($params["font-size"] == "fit" || $this->input->getOption('wrap')){
				$maxw = 0;
				if (isset($params["background-image"])){
					$filename = $this->default_params["background-image"];
					if (strpos($filename, 'url(')==1)
						$filename = substr($filename, 4,-1);
					$image = new \Imagick($filename); 
					$d = $image->getImageGeometry(); 
					$maxw = $d['width']; 
				}else if(isset($params['max-width'])){
					$maxw = intval($params['max-width']); 
				}else{
					$this->output->writeln("<error>using fit you should use background-image or max-width</error>");
				}
				if (isset($params["padding-right"]))
					$maxw -= intval($params["padding-right"]);
				$this->maxwidth = $maxw;
				if (isset($params["padding-left"]))
					$maxw -= intval($params["padding-left"]);
				if ($maxw<0)
					$maxw = 0;
				echo "maxw : ".$maxw."\n";
			}

			foreach ($lines as $key => $line) {
				if ($params["font-size"] == "fit"){
					if ($maxw){
						$textWidth = 0;
						$fontsize = 1;
						while ($textWidth < $maxw) {
							$fontsize++;
							$this->draw->setFontSize($fontsize);
							$metrics = $image->queryFontMetrics($this->draw, $line);
							$textWidth = $metrics["textWidth"];
						}
						$this->draw->setFontSize($fontsize--);
						if ($this->input->getOption('verbose'))
							$this->output->writeln("<info>best fit font size is ".$fontsize."</info>");
						if ($this->input->getOption('fontSizeMax')&& $this->input->getOption('fontSizeMax')<$fontsize){
							$this->draw->setFontSize($this->input->getOption('fontSizeMax'));
							if ($this->input->getOption('verbose'))
								$this->output->writeln("<info>but fontSizeMax : ".$this->input->getOption('fontSizeMax')."</info>");
						}
					}else{
						$this->draw->setFontSize(10);
					}
				}else {
					$this->draw->setFontSize(intval($params["font-size"]));
				}
				if ($key > 0){
					$this->jumpLine($params);
				}
				if ($line){ //something to draw
					if ($this->input->getOption('wrap') && $maxw){
						$metrics = $image->queryFontMetrics($this->draw, " ");
						$spacewidth = intval($metrics["textWidth"]);
						$this->nextY = intval($metrics["textHeight"]);
						if ($this->input->getOption('verbose')){
							echo "spacewidth : ".$spacewidth."\n";
							echo " > ";
							if ($specific)
								echo json_encode($specific);
						}
						if ($params['text-align'] == 'justify'||$params['text-align'] == 'center'){
						    $myx = $this->minx;
						    $justify_line_index = 0;
						    $justify_line_words_count = 0;
						    $justify_line_words_total_length = 0;
                            $justify_spacewidth = array();
                            $center_index = array();
                            foreach (explode(' ', $line) as $key => $world) {
                                $metrics = $image->queryFontMetrics($this->draw, $world);
                                if ($myx + intval($metrics["textWidth"])>$maxw){
                                    if ($params['text-align'] == 'justify')
                                        $justify_spacewidth[$justify_line_index] = (($maxw - $justify_line_words_total_length) / ($justify_line_words_count - 1));
                                    else
                                        $justify_spacewidth[$justify_line_index] = $spacewidth;
                                    $center_index[$justify_line_index] = intval(($maxw - $justify_line_words_total_length) / 2);
                                    $myx = $this->minx;
                                    if ($this->input->getOption('verbose')&&$params['text-align'] == 'justify')
                                        echo "Line ".$justify_line_index.", ".$justify_line_words_count." words for a total length of ".$justify_line_words_total_length."px => spaces are ".$justify_spacewidth[$justify_line_index]."px large\n";
                                    if ($this->input->getOption('verbose')&&$params['text-align'] == 'center'){
                                        echo "Line ".$justify_line_index.", ".$justify_line_words_count." words for a total length of ".$justify_line_words_total_length."px => ";
                                        echo "should start at ".$center_index[$justify_line_index]."\n";
                                    }
                                    $justify_line_index++;
                                    $justify_line_words_total_length = 0;
                                    $justify_line_words_count = 0;
                                }
                                $myx += intval($metrics["textWidth"]) + $spacewidth;
                                $justify_line_words_count++;
                                $justify_line_words_total_length += intval($metrics["textWidth"]);
                                if ($params['text-align'] == 'center')
                                    $justify_line_words_total_length += $spacewidth;
                            }
                            if ($params['text-align'] == 'center')
                                $center_index[$justify_line_index] = intval(($maxw - $justify_line_words_total_length) / 2);
                            if ($this->input->getOption('verbose')&&$params['text-align'] == 'center'){
                                echo "Line ".$justify_line_index.", ".$justify_line_words_count." words for a total length of ".$justify_line_words_total_length."px => ";
                                echo "should start at ".$center_index[$justify_line_index]."\n";
                            }

                            $justify_spacewidth[$justify_line_index+1] = $spacewidth;
                        }
                        $line_count = 0;
						$justify_space_buffer = 0;
                        $justify_line_words_count = 0;
                        $computed_spacewidth = $spacewidth;
                        if ($params['text-align'] == 'center')
                            $this->x += $center_index[$line_count];
						foreach (explode(' ', $line) as $key => $world) {
							$metrics = $image->queryFontMetrics($this->draw, $world);
							if ($this->x + intval($metrics["textWidth"])>$maxw+$computed_spacewidth+1){
								if ($this->input->getOption('verbose')){
                                    echo "   >>>   Overflow, jumpLine (".($this->x + intval($metrics["textWidth"])).">".$maxw.")\n";
                                }
                                $this->jumpLine($params);
                                $line_count++;
                                if ($params['text-align'] == 'center')
                                    $this->x += $center_index[$line_count];
                                $justify_line_words_count = 0;
                                $justify_space_buffer = 0;
							}
                            if (!isset($justify_spacewidth[$line_count]))
                                $justify_spacewidth[$line_count] = $spacewidth;
                            if (!isset($center_index[$line_count]))
                                $center_index[$line_count] = 0;
							$this->draw->annotation($this->x , $this->y, $world);
                            if ($this->input->getOption('verbose')){
                                echo $world;
                                echo " > at ".$this->x." ".$this->y."\n";
                            }
							$this->x += intval($metrics["textWidth"]);
                            $justify_space_buffer += $justify_spacewidth[$line_count]-intval($justify_spacewidth[$line_count]);
                            $justify_line_words_count++;
                            $computed_spacewidth = intval($justify_spacewidth[$line_count])+intval($justify_space_buffer);

                            if ($this->input->getOption('verbose')){
//                                echo "space buffer ".$justify_space_buffer."\n";
                                echo "space width ".$computed_spacewidth."\n";
                            }

                            $this->x += $computed_spacewidth;

                            if (intval($justify_space_buffer)>0){
                                $justify_space_buffer = $justify_space_buffer - intval($justify_space_buffer);
                            }
						}
						if ($this->input->getOption('verbose')){
							echo "\n";
						}
					}else{
						if ($this->input->getOption('verbose')){
							echo " > ";
							echo $line;
							if ($specific)
								echo json_encode($specific);
							echo " > at ".$this->x." ".$this->y;
							echo "\n";
						}
						$metrics = $image->queryFontMetrics($this->draw, $line);
						$this->draw->annotation($this->x , $this->y, $line);
						$this->x = $this->x + intval($metrics["textWidth"]);
						$this->nextY = intval($metrics["textHeight"]);
					}

				}else{
					if ($this->input->getOption('verbose')){
						echo "\n";
					}
				}
				if (isset($params['text-align']) && $params['text-align']=='left' || !isset($params['text-align'])){
					if ($this->x > $this->maxwidth)
						$this->maxwidth = $this->x;
				}
				if (($this->y + $this->nextY) > $this->maxheight)
					$this->maxheight = $this->y + $this->nextY;
			}
		}
	}

	protected function checkParams($params){
		if (isset($params['font-weight'])){
			if (!is_int($params['font-weight'])){
				$this->output->writeln("font-weight should be a integer 100-900. using 500.");
				$params['font-weight'] = 500;
			} else if ($params['font-weight'] > 900 || $params['font-weight'] < 100) {
				$this->output->writeln("font-weight should be a integer 100-900. using 500.");
				$params['font-weight'] = 500;
			}
		}
		if (isset($params['font-size'])){
			
		}
		return $params;
	} 

	protected function jumpLine($params){
		if (isset($params['line-height']))
			$this->y = $this->y +  str_replace("px", "", $params['line-height']);
		else
			$this->y = $this->y +  $this->nextY;
		$this->x = $this->minx;
	}
}