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

    private $contentmaxwidth;

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
		->addOption('wrap','w',InputOption::VALUE_NONE,'should we wrap')
		->addOption('fit',NULL,InputOption::VALUE_NONE,'fit output to background-image');
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

		$format = $input->getOption("format");

		if (isset($this->default_params["padding-right"]))
			$this->maxwidth = $this->maxwidth + intval($this->default_params["padding-right"]);

		$imagick = new \Imagick();
		if (isset($this->default_params["background-image"])){
			$filename = $this->default_params["background-image"];
			if (strpos($filename, 'url(')==0)
				$filename = substr($filename, 4,-1);
			$imagick->readImage($filename);
			if ($input->getOption("fit")){
                $d = $imagick->getImageGeometry();
                $this->maxheight = $d['height'];
                $this->maxwidth = $d['width'];
            }
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

        $this->contentmaxwidth = $this->maxwidth - intval($this->default_params["padding-right"]) - intval($this->default_params["padding-left"]);

	    $imagick->cropImage( $this->maxwidth, $this->maxheight , 0 , 0 );
	    $imagick->setImageFormat($format);

        $this->drawDomNode($doc);

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

            if ($this->input->getOption('verbose')){
                if ($specific)
                    echo json_encode($specific);
            }

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

			if ($params["font-size"] == "fit" || $this->input->getOption('wrap')
                || $this->default_params['text-align']=='right' || $this->default_params['text-align']=='center'
                || $this->default_params['vertical-align']=='bottom' || $this->default_params['vertical-align']=='center' ){
				if($this->contentmaxwidth < 1 ){
					$this->output->writeln("<error>using fit/wrap/text-align/vetical-align you should use background-image or max-width to fix the output width</error>");
                    die();
				}
                if ($this->input->getOption('verbose'))
    				echo "contentmaxwidth : ".$this->contentmaxwidth."\n";
			}

            if ($this->default_params["font-size"] == "fit"){ //lets find the best font size
                $textWidth = 0;
                $font_size = 1;
                while ($textWidth < $this->contentmaxwidth) {
                    $font_size++;
                    $this->draw->setFontSize($font_size);
                    $metrics = $image->queryFontMetrics($this->draw, $lines[0]);
                    $textWidth = $metrics["textWidth"];
                    if ($this->input->getOption('verbose'))
                        echo "font size = ".$font_size." text width = ".$textWidth."(max ".$this->contentmaxwidth.")\n";
                }
                $this->draw->setFontSize($font_size--); //SET FONT SIZE
                if ($this->input->getOption('verbose'))
                    $this->output->writeln("<info>best fit font size is ".$font_size."</info>");
                if ($this->input->getOption('fontSizeMax')&& $this->input->getOption('fontSizeMax')<$font_size) {
                    $this->draw->setFontSize($this->input->getOption('fontSizeMax'));
                    if ($this->input->getOption('verbose'))
                        $this->output->writeln("<info>but fontSizeMax : " . $this->input->getOption('fontSizeMax') . "</info>");
                }
            }else {
                $this->draw->setFontSize(intval($params["font-size"])); //SET FONT SIZE
            }

			$geometry = $this->computeGeometry($lines);
            print_r($geometry);
            $justify_space_width = $geometry['justify_space_width'];
//            $fit_font_size = $geometry['fit_font_size']; //TODO multiline font fit
            $center_x_index = $geometry['center_x_index'];
            $text_total_height = $geometry['text_total_height'];
            $line_height = $geometry['line_height'];

            $this->y = 0;
            $this->x = 0;

            if ($this->default_params['vertical-align']=="bottom"){
                $this->y = ($this->maxheight - intval($this->default_params['padding-bottom'])) - $text_total_height;
                $this->miny = 0;
            }else{
                $this->miny += $line_height;
            }

            $line_count = 0;
            $this->nextY = $line_height;

			foreach ($lines as $key => $line) {
				if ($key > 0) //jump line
					$this->jumpLine($params);
				if ($line){ //something to draw
                    $this->x = 0;
                    if ($this->default_params['text-align'] == 'center')
                        $this->x += $center_x_index[$line_count];
                    $this->point($this->minx + $this->x,$this->miny + $this->y);
                    //for each line
                    foreach (explode(' ', $line) as $key => $world) {
                        $metrics = $image->queryFontMetrics($this->draw, $world);
                        if ($this->input->getOption("wrap")){ //do we have to deal with overflow ?
                            $max = $this->contentmaxwidth + $justify_space_width[$line_count] + 1;
                            if ($this->x + intval($metrics["textWidth"]) > $max){
                                if ($this->input->getOption('verbose')){
                                    echo "   >>>   Overflow, jumpLine (".($this->x + intval($metrics["textWidth"])).">".($max).")\n";
                                }
                                $this->jumpLine($params);
                                $line_count++;
                                $this->x = 0;
                                if ($this->default_params['text-align'] == 'center')
                                    $this->x += $center_x_index[$line_count];
                            }
                        }
                        $this->draw->annotation($this->minx + $this->x , $this->miny + $this->y, $world); // WRITING
                        if ($this->input->getOption('verbose')){
                            echo $world;
                            echo " > at ".($this->minx + $this->x)." ".($this->miny + $this->y)."\n";
                        }
                        $this->x += intval($metrics["textWidth"]);
                        if (!isset($justify_space_width[$line_count]))
                            $justify_space_width[$line_count] = $justify_space_width[$line_count-1];
                        $this->x += $justify_space_width[$line_count];
                    }
				}
				if (!$this->isSizeFixed()){
					if ($this->x > $this->maxwidth)
						$this->maxwidth = $this->x;
                    if (($this->y + $this->nextY) > $this->maxheight) //push bottom
                        $this->maxheight = $this->y + $this->nextY;
				}
				$line_count++;
			}
		}
	}

	protected function isSizeFixed(){
	    return ((isset($this->default_params["max-width"]))||(isset($this->default_params["max-height"]))||(isset($this->default_params["background-image"])&&$this->input->getOption('fit')));
    }

    protected function point($x,$y){
        $backupColor = $this->draw->getStrokeColor();
        $this->draw->setStrokeColor("red");
        $this->draw->line($x-5,$y,$x+5,$y);
        $this->draw->line($x,$y-5,$x,$y+5);
        $this->draw->setStrokeColor($backupColor);
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

        $image = new \Imagick();

        $metrics = $image->queryFontMetrics($this->draw, " "); //space metrics
        $space_width = $metrics['textWidth'];
        $line_height = $metrics['characterHeight'];

        foreach ($lines as $key => $line){ //foreach line
            $text_total_height += $line_height;
            foreach (explode(' ', $line) as $key => $word) { //foreach word
                $metrics = $image->queryFontMetrics($this->draw, $word); //word metrics
                if ($this->input->getOption('wrap')){ //if we take care of overflow
                    if ($index_x + intval($metrics["textWidth"]) > $this->contentmaxwidth ){ //overflow
                        if ($this->default_params['text-align'] == 'justify')
                            $justify_space_width[$justify_line_index] = intval(($this->contentmaxwidth - $justify_line_words_total_length) / ($justify_line_words_count - 1));
                        else
                            $justify_space_width[$justify_line_index] = $space_width;
                        $center_x_index[$justify_line_index] = intval(($this->contentmaxwidth - $center_line_total_length) / 2);
                        $index_x = 0;

                        $text_total_height += $line_height;
                        $justify_line_index++;
                        $justify_line_words_total_length = 0;
                        $justify_line_words_count = 0;
                    }
                }
                $index_x += intval($metrics["textWidth"]) + $space_width;
                $justify_line_words_count++;
                $justify_line_words_total_length += intval($metrics["textWidth"]);
                $center_line_total_length += intval($metrics["textWidth"]) + $space_width;
            }
        }
        $center_x_index[$justify_line_index] = intval(($this->contentmaxwidth - $center_line_total_length) / 2);
        //$justify_space_width[$justify_line_index+1] = $space_width;

        $geometry = array();
        $geometry['justify_space_width'] = $justify_space_width;
        $geometry['center_x_index'] = $justify_space_width;
        $geometry['text_total_height'] = $text_total_height;
        $geometry['line_height'] = $line_height;

        return $geometry;
    }
}