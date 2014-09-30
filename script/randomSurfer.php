<?php
  /**
    This script follows links generated by the ValueManager.link() method randomly
    over a defined lenght of hops.
    It uses the 'ValueManagerLinks' get parameter,
    which causes the ValueManager to append this parameter to
    all links it generates.
    This way, such links can be easily found via regexes.
    By calling different random spots on the site,
    this script allows for easier detection of errors
    and can generate some statistics for generation times
    by parsing the generationtime from the page.
  */
  $hops  = 100;
  $base  = "http://127.0.0.1/shk/main/";
  $href  = $_href = "?ValueManagerLinks";
  $times = array();
  //Hopping around:
  for($i = 1; $i <= $hops; $i++){
    echo "Fetching($i): $href\n";
    $data = file_get_contents($base.$href);
    preg_match('/Page generated in ([^s]*)s/', $data, $time);
    if(count($time) === 2)
      array_push($times, $time[1]);
    preg_match_all('/href=["\']([^"\']*ValueManagerLinks)["\']/', $data, $hrefs);
    if(count($hrefs) === 2){
      $hrefs = $hrefs[1];
      if(count($hrefs) === 0){
        //This may happen if links are longer than file_get_contents likes.
        echo "No hrefs found, resetting to start!\n";
        $href = $_href;
      }else
        $href = $hrefs[rand(0, count($hrefs) - 1)];
    }else die('No hrefs found!');
  }
  //Calculating Times:
  $sum = 0;
  $max = 0;
  $min = 999;
  foreach($times as $time){
    $sum += $time;
    if($max < $time)
      $max = $time;
    if($min > $time)
      $min = $time;
  }
  $avg = $sum / count($times);
  echo "Times (min, avg, max): $min, $avg, $max\n";
?>