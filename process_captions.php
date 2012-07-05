<?
error_reporting(0);
set_time_limit(36000);
error_reporting(E_ALL);
ini_set('display_errors', '1');

include("simple_html_dom.php");
$topdir = "/var/www/senatelive/downloads/";
$astreams = Array();
echo "Scanning $topdir \n";
$days = scandir($topdir,1);

foreach($days as $dir) {
        $path = $topdir .$dir;
	echo "Processing $path \n</br>";
        if  (file_exists("$path/captions.json")){
        	$speeches = processCaptions("$path/captions.json");
		//$speeches = clusterSpeeches($speeches);
		$jsonspeeches = json_encode($speeches);
    		writeFile("$path/speeches.json", $jsonspeeches);
		//$ppl = justNames($speeches);
    		$webvtt = webvttspeeches($speeches);
		//echo $webvtt;		
		writeFile("$path/captions.vtt", $webvtt);
	
        }
}

$speeches = processCaptions("downloads/120202/captions.json");
var_dump(justNames($speeches));

function writeFile($fn, $content){
	$handle = fopen($fn, "w");
	fwrite($handle, $content);
	echo "Writing $fn \n<br/>";
	fclose($handle);
	chmod($fn, "0777");
}
function processCaptions($captionsfile){
    
    $handle = fopen($captionsfile, "r");
    
    
    $output = fread($handle, filesize('captions.json'));
    fclose($handle);
         
    $pattern = array("time:", "type:", ", text:", "], ]", "[ [", "] ]");
    $replace   = array("\"time\":", "\"type\":", ", \"text\":", "]", "[", "]");
    $output = str_replace($pattern, $replace, $output);
    $output = stripslashes($output);
    //var_dump($output);
    //exit;
    $captions = json_decode($output, true);
    
    $congress = file_get_contents('congress.json');
    $congress = json_decode($congress, true);
    $congress = $congress['response']['legislators'];
    
    
    for ($i = 0; $i < count($congress); $i++){
        if ($congress[$i]["legislator"]["chamber"] == "senate") { 
            $senators[]= $congress[$i]["legislator"];
        }
    }
    $speeches = array();
    //var_dump($captions);
    foreach ($captions as $line) {
        $text = processText($line["text"], $line["time"]);
        if (is_array($text)) {
	    echo "Found speaker: " .$text[1] ."\n<br/>";
            $speaker["name"] = $text[1];
            $speaker["start"] = $line["time"];
            $speaker["end"] = "";
            $speaker["captions"] = Array();
            array_push($speeches, $speaker);
            $text = $text[0];
            
        }
	if(!isset($speeches[count($speeches) - 1])){
	    $speaker["name"] = "Unknown";
	    $speaker["start"] = $line['time'];
	    $speaker["end"] = "";
	    $speaker["captions"] = Array();
	    array_push($speeches, $speaker);
	    $text = $text[0];
	
	} 
        $cap['text'] = trim($text);
        $cap['start'] = $line['time'];
        $cap['end'] = "";
        $lastspeaker = count($speeches) - 1;
        if ($text != null || $text != "") {
		//echo "inserting " .$cap['text'] ."for speaker " .$speeches[$lastspeaker]["name"] ."...\n<br/>";
                array_push($speeches[$lastspeaker]["captions"], $cap);
            }
    
    	}
    
    	foreach($speeches as &$speaker){
        $lastcaption = count($speaker["captions"]) - 1;
        if (isset($speaker["captions"][$lastcaption])){
            $speaker["end"] = $speaker["captions"][$lastcaption]["start"];
        }
        for ($i = 0; $i < count($speaker['captions']); $i++) {
            if (isset($speaker['captions'][$i+1])){
                $speaker['captions'][$i]['end'] = $speaker['captions'][$i+1]['start'] - .01;
            } else {
                $speaker['captions'][$i]['end'] = $speaker['end'] + .8;
            }
        }
    }
    
return $speeches;   
}		
/*
    function clusterSpeeches($speeches){
        for ($i =0; $i < count($speeches) - 1; $i++){
            if ($speeches[$i]["name"] == $speeches[$i + 1]["name"]){
                foreach($speeches[$i + 1]["captions"] as $caps) {
                    array_push($speeches[$i]["captions"], $caps);
                    }
                $extras[]= $i + 1;
                }
            }
        foreach ($extras as $extra){
            unset($speeches[$extra]);
        }
        return array_values($speeches);
    }
*/	
function justNames($speeches){
	foreach($speeches as $speaker){
		$nameparts = preg_split("/ /", $speaker["name"]);
		if ($nameparts[0] == "MR." || $nameparts == "MRS.") {
			$person['lastname'] = $nameparts[1];
			$person['title'] = $nameparts[0];
			$people[]= $person;
		}
	}
	return ($people);
}
	
function htmlSpeeches($speeches){
	foreach($speeches as $speaker){

		echo "<div data-speaker='" .$speaker["name"] ."' class='speaker'>" ."<h3>" .$speaker['name'] ."</h3>";
		echo "<h4>" .$speaker["start"] ." to " .$speaker["end"] ."</h4>";
		foreach ($speaker['captions'] as $cap){
			echo "<div class='caption'><span class='times'>" .$cap['start'] ." to " .$cap['end'] ."</span><div class='captiontext'>" .$cap['text'] ."</div></div>";
		}
		echo "</div>";
	}
}


function webvttSpeeches($speeches){
	$webvtt = "WEBVTT\n\n";
	$iterator = 1;
	foreach($speeches as $speech){
		foreach($speech['captions'] as $cap){
			$webvtt.= $iterator ."\n";
			$webvtt .= sec2hms($cap['start']) ." --> " .sec2hms($cap['end']) ."\n";
			$webvtt .= "<v " .$speech['name'] .">" .$cap['text'] ."\n\n";
			$iterator++;
		}
	}	
    return $webvtt;
}




function processText($textin, $time) {
	$output = array();	
	if (strpos($textin, ":") === false) {
		return $textin;
	}	
	$lin = explode(":", $textin);
	$speaker = strtoupper($lin[0]);
	$text = $lin[1];
	//echo "<div style='background: pink'>$text</div>";
	if (str_word_count($speaker) > 3 || $speaker == "UNTIL" || $speaker == "TO THE SENATE" || $speaker == "SIGNED" || $speaker == "SENATE"|| intval($speaker) || preg_match('/[0-9]+/', $text))  	{
		return $textin;
	} else {
		$output[0] = $text;
		//echo "<i>$text</i>";
		$output[1] = $speaker;
		return $output;
	}	
}

function sec2hms ($sec, $padHours = true) {
$hms = "";
$mm = preg_split("/\./", $sec);
$hours = intval(intval($sec) / 3600);
$hms .= ($padHours)
? str_pad($hours, 2, "0", STR_PAD_LEFT). ':'
: $hours. ':';
$minutes = intval(($sec / 60) % 60);
$hms .= str_pad($minutes, 2, "0", STR_PAD_LEFT). ':';
$seconds = intval($sec % 60);
$hms .= str_pad($seconds, 2, "0", STR_PAD_LEFT) ."." .str_pad($mm[1], 3, "0");
return $hms;
}


?>
