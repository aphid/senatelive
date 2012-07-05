<?

$topdir = "/var/www/senatelive/downloads/";
$astreams = Array();
echo "Scanning $topdir \n";
$days = scandir($topdir,1);
foreach($days as $dir) {
	$path = $topdir .$dir ."/";
	if  (file_exists("$path/video.mp4")){
	$astreams[] = $topdir .$dir; 
	}
}
foreach ($astreams as $stream){
	echo "Trying $stream \n";
	$outputdir = "/metavid/video_archive";
	//$streamname = explode("/", $stream);
	//$streamname = $streamname[count($streamname)-1];
	$streamname = "video";
	$in = $stream ."/" .$streamname .".mp4";
	echo "Checking for $in \n";
	//$ogv = "$stream/$streamname.ogv";
	$ogv = "";

	//$mp4 = "$stream/$streamname.mp4";
	$mp4 = "";
	$webm = "$stream/$streamname.webm";
	if (file_exists($in) && !file_exists($webm)) {
		echo "Transcoding webm\n";
		transcodeWebM($in,$webm);
		
	} else { echo "not transcoding webm\n";}
}


function transcodeOgv($in, $out) {
	$cmd = "ffmpeg2theora $in -o $out";
	echo "Running $cmd \n";
	passthru($cmd);

}

function transcodeMp4($in, $out) {
	$cmd = "HandBrakeCLI -i $in -o $out --preset 'iPhone & iPod Touch' -- vb 200 --width 320 --two-pass --turbo --optimize";
	echo "Running $cmd \n";
	passthru($cmd);
}

function transcodeWebM($in, $out){
	/* $cmd = "ffmpeg -pass 1 -passlogfile $in -threads 16  -keyint_min 0 -g 250 -skip_threshold 0 -qmin 1 -qmax 51 -i $in -vcodec libvpx -b 204800 -s 320x240 -aspect 4:3 -an -f webm";
	echo "Running $cmd";
	passthru($cmd);
	$cmd = "ffmpeg -pass 2 -passlogfile $in -threads 16  -keyint_min 0 -g 250 -skip_threshold 0 -qmin 1 -qmax 51 -i $in -vcodec libvpx -b 204800 -s 320x240 -aspect 4:3 -acodec libvorbis -ac 2 -y $out";
	*/
	$cmd = "ffmpeg -i $in -b:v 600k -r 30 $out";
	passthru($cmd);
}


?>

