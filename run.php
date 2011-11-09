<?php

include 'CurlReq.php';
include 'HttpReq.php';
include 'Thingiverse.php';

set_time_limit(0);
echo '<meta content="text/html; charset=UTF-8" http-equiv="Content-type">';
echo '<pre>';

$tv = new Thingiverse;

/* Print a list of all the things a user has uploaded

print_r($tv->get_user_things('DrewPetitclerc'));

*/

/* Get newest things up to last thing ID seen

print_r($tv->get_list(13366, 'newest'));

*/

/* Get a range of things 

$tv->get_range(12701,13000);

*/

/* Get a list of things 

$failed = explode(' ', '5812 5814 5815 5829 5852 5853 5872 5875 5876 5880 5882 5918 5920 5921 5944 5989 5990');
$tv->get_things($failed);
*/

echo '</pre>';
?>
