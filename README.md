#Plopxy Txt2Image

##Requirement

5 <= php <= 7
composer https://getcomposer.org/

##Install

composer install 

##Usage

$ sh text2img.sh [-s|--style [STYLE]] [-f|--format [FORMAT]] [-o|--output [OUTPUT]] [--fontSizeMax [FONTSIZEMAX]] [--] <text>
OR
$ php application.php text2img [-s|--style [STYLE]] [-f|--format [FORMAT]] [-o|--output [OUTPUT]] [--fontSizeMax [FONTSIZEMAX]] [--] <text>

###<text> 
Is the only required param.
It can be a simple word, a markdown syntax or a file containing markdown

$ sh text2img.sh "foo bar"
$ sh text2imag.sh "*foo*<br>bar"
$ sh text2imag.sh filename.md

###[STYLE]
is a json array of style parameters

* font-family : a font familly
* font : a font file (ttf)
* font-size : size of the font (if set to "fit", lines will fit the output lenght)
* color : color of the text
* background-color : color of the background, default is transparent for PNG and white for JPG
* background-image : file to be used as a background. url(filename.jpg). the output while have the same size unless max-size or * max-widht is set.
* padding : space between image border and text. padding-left, padding-right, padding-to, padding-bottom 
* max-width : width of the output image. if font-size is not set to "fit" the content could be cropped
* max-height : height of the output image. the content could be cropped.
* text-align : EXPERIMENTAL, should align content LEFT or RIGHT
* text-transform : uppercase, lowercase string
* text-decoration : underline, uperline or line trought

$ sh text2img.sh Beamart '{"color":"purple","font":"beyond_the_mountains.ttf","font-size":"60px","padding-top":"20px"}'
$ sh text2img.sh txt2img bestfit.md '{"color":"white","background-color":"red","padding":"10px","text-transform":"uppercase","font-size":"fit","max-width":"200px","font-family":"Helvetica"}'

###[OUTPUT]
set the output file. if no extension is given, or if the given is not matching format, it will be added.
If no output is given, image is returned to standard output.

$ sh text2imag.sh "image png" > /tmp/image.png
$ sh text2imag.sh "image png" -o /tmp/image.png


###[FORMAT]
default is png, can be set to jpg or gif
$ sh text2img.sh "test gif" -f gif -o web/images/tmp
Output file: web/images/tmp.gif


###[FONTSIZEMAX]
when using font-size "fit", fontsizemax tell a limit of font-size to be used

php application.php txt2img toto -s '{"padding":"10px","font-size":"fit","max-width":"300px"}' -o ./web/images/test -v --fontSizeMax 80

## TO DO
* Combine text-decoration
* Support of text-align center 
