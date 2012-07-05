<?php
date_default_timezone_set('America/Los_Angeles');
set_time_limit(36000);
error_reporting(E_ALL);
ini_set('display_errors', '1');

include("simple_html_dom.php");


$clips = getClips();
checkClips($clips);

foreach ($clips as &$clip)
    {
    $cid = $clip['id'];
    if ($clip['status'] == "incomplete" || $clip['status'] == "none")  {
        $clip['shortdate'] = date('ymd', strtotime($clip['date']));

        msg("Writing events for clip $cid");
        if (!writeEvents($cid, $clip['shortdate']))
            {
            echo "Write failed!";
            exit;
            }
        msg("Writing captions for clip $cid");
               writeCaptions($cid, $clip['shortdate']);
        $msg = "Writing video for clip $cid - senate stream for ";
        $msg .= $clip['date'];
        $fp = fopen("downloads/" .$clip['shortdate'] .'/vardump.json', 'w');
        echo "<p>Writing JSON</p>";
        fwrite($fp,json_encode($clip));
        fclose($fp);    
        msg($msg);
        $urls = findMedia($clip['id']);
        //var_dump($urls);
        if ($urls['mp4']) {
           $dur = getDuration($urls['mp4']);
           echo "mp4 duration is $dur for " .$urls['mp4'];
           if ($dur) { getExtFile($clip['id'], $urls['mp4'], $clip['shortdate']); }
        
        } else if ($urls['wmv']) {
        
        getExtFile($clip['id'], $urls['wmv'], $clip['shortdate']);
        }
        //exit;
        //getVideo($cid, $clip['shortdate']);
        //$clip['status'] = "finished";
    }
}

function getClips()
    {
    //Get list of clips, pull IDs
    $feedUrl = 'http://floor.senate.gov/VPodcast.php?view_id=2';
    $rawFeed = file_get_contents($feedUrl);
    $xml = new SimpleXmlElement($rawFeed);
    $items = array();
    foreach ($xml->channel->item as $item)
        {    
        $id = explode("clip_id=", $item->link);
        $id = $id[1];
        $itm["id"] = $id;
        $itm["date"] = strval($item->pubDate);
        array_push($items, $itm);
        }
    //return json_encode($items);
    return $items;
    }

function getEvents($id)
    {
    $url = "http://floor.senate.gov/MediaPlayer.php?view_id=2&clip_id=" .$id;

    $html = file_get_html($url);
    $events = Array();
    // Find all images
    foreach($html->find('div.indexPoints a') as $event)
        {
        $position = $event->time;
        $item['position'] = $position;
        $item['description'] = preg_replace('/[^(\x20-\x7F)]*/','', $event->innertext);
        $events[] = $item;
        }
    return json_encode($events);
    }
    
function writeEvents($id, $shortdate)
    {
    $content = getEvents($id);
    $dir = "downloads/$shortdate";
    $handle = fopen("$dir/events.json", "w");
    if (!fwrite($handle, $content)) {
        return false; // failed.
        } else {
        return true; //success.
        }
    }

function getCaptions($id)
    {
    $url = "http://floor.senate.gov/JSON.php?view_id=2&clip_id=$id";
    $html = file_get_html($url);
    return $html;
    }

function writeCaptions($id, $shortdate)
    {
    $content = getCaptions($id);
    $dir = "downloads/$shortdate";
    $handle = fopen("$dir/captions.json", "w");
    if (!fwrite($handle, $content)) {
        return false; // failed.
        } else {
        return true; //success.
        }
    }

    
function findMedia($id)
    {
    $media = Array();
    $url = "http://floor.senate.gov/MediaPlayer.php?view_id=2&clip_id=" .$id;
    $html = file_get_html($url);   
    $process = 0;
    foreach($html->find('script') as $script) {
        if (preg_match("/meta_id/", $script)) {
            $process = $script->innertext;
            }
        }
    //echo $process;
    $process = explode("([[", $process);
    $process = explode("]])", $process[1]);
    $process = str_replace("'", "", $process[0]);
    $process = str_replace(" ", "", $process);
    $process = explode(",", $process);
    foreach ($process as $file){
        $ext = pathinfo(parse_url($file,PHP_URL_PATH),PATHINFO_EXTENSION);
        if ($ext == "mp4")  { $media['mp4'] = $file; }
        if ($ext == "mp3") { $media['mp3'] = $file; }
        if ($ext == "aspx") { $media['wmv'] = $file; }
        //$media['wmv'] = "http://207.7.154.96:443/MediaVault/Download.aspx?server=houselive.gov&clip_id=$id";
        }
    return($media);
    }
    

function checkClips(&$clips)
    {
    foreach($clips as &$clip)
        {
        $dir = "downloads/";
        $clip['shortdate'] = date('ymd', strtotime($clip['date']));
        $dir .= $clip['shortdate'];
        $dir .= "/";
        if (!file_exists("$dir"))
            {
            mkdir($dir);
            $clip['status'] = "none";
            }
        else if ( (file_exists("$dir/video.mp4") || file_exists("$dir/video.wmv") ) && (file_exists("$dir/captions.json")) && (file_exists("$dir/events.json")))
                {
                $clip['status'] = "complete";
                }
        else {
        $clip['status'] = "incomplete";
            }
        }
    return $clips;
    }
    
    
function getExtFile($id, $url, $shortdate)
    {
    echo "Attempting to fetch video $id...";
    $ext = pathinfo(parse_url($url,PHP_URL_PATH),PATHINFO_EXTENSION);
    $newfilename = "downloads/$shortdate/video.$ext";
    if ( file_exists($newfilename) )
        { return 0; }
    $fp = fopen($newfilename, 'wb');
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    curl_setopt($ch, CURLOPT_URL, $url); 
    curl_setopt($ch, CURLOPT_NOPROGRESS, false);
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, 'callback');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 99);
    $response = curl_exec($ch);     
    
    if(!curl_errno($ch))
        {
        $info = curl_getinfo($ch);
        echo 'Took ' . $info['total_time'] . ' seconds to send a request to ' . $info['url'] ."for stream $id " .'<br/>';
        }
    else
        {
       echo 'Curl error: ' . curl_error($ch);
        }
    curl_close($ch);
    fclose($fp);
    return $response; 
    }

    function msg($msg)
    {
    //echo '<script type="text/javascript"> document.getElementById("msg").innerHTML="' .$msg .'"' ."</script>";
    echo "$msg \n";
    }
    
    
$percent = 0;

function callback($download_size, $downloaded, $upload_size, $uploaded) {
    global $percent;



    if ($download_size >= 1 ) { 
        $pct = floor(100 / $download_size * $downloaded);   
        } else { 
        $pct = 0;
        //echo '<script type="text/javascript">resetProgress("status"); </script>';
        } 

    if ($pct > $percent) {
         echo floor($pct) ."% $downloaded of $download_size \n";
        }
    $percent = $pct;
}

function getDuration($filename)
{
    $string = shell_exec( "ffmpeg -i $filename 2>&1");
    $pattern = "/Duration: ([0-9])([0-9]):([0-9])([0-9]):([0-9])([0-9])/";
    preg_match($pattern, $string, $reg_array);
    if (!isset($reg_array[0])) { 
        return 0; 
        }
    $result = $reg_array[0];
    $hms = explode(" ", $result);
    $durationhms = $hms[1];
    return $durationhms;
    return 0;
}

?>
