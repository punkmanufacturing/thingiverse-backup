<?php

include 'CurlReq.php';
include 'HttpReq.php';
include 'Thingiverse.php';


set_time_limit(0);
echo '<meta content="text/html; charset=UTF-8" http-equiv="Content-type">';

$tv = new Thingiverse;

/* Print a list of all the things a user has uploaded
echo '<pre>';
print_r($tv->get_user_things('DrewPetitclerc', false));
echo '</pre>';
*/

/* Get newest things up to last thing ID seen
echo '<pre>';
print_r($tv->get_newest(13366));
echo '</pre>';
*/

/* Get a range of things */
echo '<pre>';
$tv->get_range(7001,8000);
echo '</pre>';


?>
