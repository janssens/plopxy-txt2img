#!/usr/bin/env php
<?php
// plopxyTxt2Img.php

require __DIR__.'/vendor/autoload.php';
require __DIR__.'/src/AppBundle/Command/Txt2ImgCommand.php';

use Symfony\Component\Console\Application;
use AppBundle\Command\Txt2ImgCommand;

$application = new Application();

$application->add(new Txt2ImgCommand());

$application->run();