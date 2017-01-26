![](title.png)

***

##Requirement

* php-cli version > 5 (php7 OK)
* composer https://getcomposer.org/
* php-imagick (php5-imagick for php5)

##Install

	$ php composer.phar install 

##Usage

	$ sh text2img.sh [-s|--style [STYLE]] [-f|--format [FORMAT]] [-o|--output [OUTPUT]] [--fontSizeMax [FONTSIZEMAX]] [-w|--wrap] [--] <text>
OR

	$ php application.php text2img [-s|--style [STYLE]] [-f|--format [FORMAT]] [-o|--output [OUTPUT]] [--fontSizeMax [FONTSIZEMAX]] [-w|--wrap] [--] <text>

###text
Is the only required param.
It can be a simple word, a markdown syntax or a file containing markdown

	$ sh txt2img.sh "foo bar" > /tmp/foobar.png

![foobar](doc/images/foobar.png)

	$ sh txt2imag.sh "*foo*<br>bar" > /tmp/foo_bar_.png

![foo_bar_](doc/images/foo_bar_.png)

	$ sh txt2imag.sh filename.md > /tmp/finame.md.png

![filename.md](doc/images/filename.md.png)

###[STYLE]
A json array of style parameters

* **font-family** : a font familly
* **font** : a font file (ttf)
* **font-size** : size of the font (if set to "fit", lines will fit the output lenght)
* **color** : color of the text
* **background-color** : color of the background, default is transparent for PNG and white for JPG
* **background-image** : file to be used as a background. url(filename.jpg). the output while have the same size unless **max-height** or **max-width** is set.
* **padding** : space between image border and text. **padding-left**, **padding-right**, **padding-to**, **padding-bottom** 
* **max-width** : width of the output image. if font-size is not set to "fit" the content could be cropped
* **max-height** : height of the output image. the content could be cropped.
* **text-align** : EXPERIMENTAL, should align content LEFT or RIGHT
* **text-transform** : uppercase, lowercase string
* **text-decoration** : underline, uperline or line trought

####exemples

	$ sh txt2img.sh Plopxy-Txt2img -s '{"color":"purple","font":"doc/font/beyond_the_mountains.ttf","font-size":"60px","padding-top":"20px"}' -o doc/images/font

![Plopxy-Txt2img](doc/images/font.png)

	$ sh txt2img.sh "best fit<br>multiple lines" -s '{"color":"white","background-color":"red","padding":"10px","text-transform":"uppercase","font-size":"fit","max-width":"200px","font-family":"Helvetica"}' -o doc/images/bestfit

![bestfit](doc/images/bestfit.png)

###[-w|--wrap]

If set, text will be wrapped, meaning automatic line return preventing word crop. You should use style **max-height** or **background-image** in order to fix the width of the output image.
**/!\ will not work for text-align : right **

	$ sh txt2img.sh test/test2.md -s '{"padding":"10px","font-size":"20","max-width":"400px"}' -o doc/images/wrap.png -w

![textwap](doc/images/wrap.png)

###[OUTPUT]

Set the output file. If no extension is given, or if the given is not matching format, it will be added.
If no output is given, image is returned to standard output.

	$ sh text2imag.sh "image png" > /tmp/image.png
	$ sh text2imag.sh "image png" -o /tmp/image.png


###[FORMAT]

Default is png, can be set to jpg or gif

	$ sh text2img.sh "test gif" -f gif -o web/images/tmp
	Output file: web/images/tmp.gif


###[FONTSIZEMAX]

when using font-size "fit", fontsizemax tell a limit of font-size to be used

	$ sh txt2img.sh toto -s '{"padding":"10px","font-size":"fit","max-width":"300px"}' -o ./web/images/test -v --fontSizeMax 80

###[-v|--verbose]

verbose

## TO DO // Ideas

* wrap on text-align right
* Combine text-decoration
* Support of text-align center 
* Support of text-align justify 
* Font-size for h1, h2 and h3
* support list-style for ul
* neasted UL ?!!