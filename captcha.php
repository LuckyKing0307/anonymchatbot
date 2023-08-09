<?php 
session_start();
$captcha_text = "";
if(isset($_GET['captcha']))
{
$captcha_text = $_GET['captcha'];
}

header("Content-type: image/png");// setting the content type as png
$captcha_image=imagecreatetruecolor(200,200);

$captcha_background=imagecolorallocate($captcha_image,0,0,0);//setting captcha background colour
$captcha_text_colour=imagecolorallocate($captcha_image,255,255,255);//setting cpatcha text colour

imagefilledrectangle($captcha_image,0,0,120,29,$captcha_background);//creating the rectangle

$font='arial.ttf';//setting the font path

imagettftext($captcha_image,20,0,50,100,$captcha_text_colour,$font,$captcha_text);
imagepng($captcha_image);
imagedestroy($captcha_image);