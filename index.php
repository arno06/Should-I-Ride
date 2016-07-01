<?php

define('CACHE_DIR', 'cache/');
define('GMAP_KEY', 'AIzaSyDlYKHMqs08IaEt6q3w6C8qSsGK_s4dC-c');
define('OWEATHER_KEY', 'f52cdbd05a0fae7b30cf4f536100f44f');
define('EARTH_RADIUS', 6367445);
define('DIST_GAP', 5000);//meters
define('CACHE_DURATION', 1);//hours

$icons = array(
    "01d"=>"wi-day-sunny",
    "02d"=>"wi-day-cloudy",
    "03d"=>"wi-day-cloudy-gusts",
    "04d"=>"wi-cloud",
    "09d"=>"wi-showers",
    "10d"=>"wi-day-rain",
    "11d"=>"wi-day-thunderstorm",
    "13d"=>"wi-day-snow",
    "50d"=>"wi-day-windy",
    "01n"=>"wi-night-clear",
    "02n"=>"wi-night-alt-cloudy",
    "03n"=>"wi-night-alt-cloudy-gusts",
    "04n"=>"wi-cloud",
    "09n"=>"wi-night-alt-showers",
    "10n"=>"wi-night-alt-rain",
    "11n"=>"wi-night-alt-thunderstorm",
    "13n"=>"wi-night-alt-snow",
    "50n"=>"wi-night-alt-windy"
);

$availables_lang = array(
    'fr'=>'Français',
    'en'=>'English'
);

$availables_short_lang = array_keys($availables_lang);

$lang = isget('lang')&&in_array($_GET['lang'], $availables_short_lang)?$_GET['lang']:'fr';

$origin = isget('origin')?$_GET['origin']:"Groslay";

$destination = isget('destination')?$_GET['destination']:"Issy-Les-Moulineaux";

include_once('locales/'.$lang.'.php');

/**
 * Debug - trace & trace_r emulated from debugger
 **/
$debug = "";
function trace($pString){global $debug;$debug .= $pString."<br/>";}
function trace_r($pData){global $debug;$debug .= "<pre>".print_r($pData, true)."</pre><br/>";}

function isget($pName){return isset($_GET[$pName])&&!empty(trim($_GET[$pName]));}

function stillRelevant($pTime)
{
    return ($pTime+(60*60*CACHE_DURATION))>time(); 
}

/**
 * @param $pPoint
 **/
function retrieveForecastData($pPoint, $pLanguage)
{
    $final_url = "http://api.openweathermap.org/data/2.5/forecast?lat=".$pPoint->lat."&lon=".$pPoint->lng."&units=metric&lang=".$pLanguage."&appid=".OWEATHER_KEY;

    $cache_key = "forecast_".md5($pPoint->lat.$pPoint->lng.$pLanguage);
    
    $cacheFile = CACHE_DIR.$cache_key;

    if(file_exists($cacheFile)&&stillRelevant(filemtime($cacheFile)))
    {
        trace("<span style='font-size:10px'>Cache : ".$cacheFile."</span>");
        return json_decode(file_get_contents($cacheFile));
    }

    trace("<span style='font-size:10px'>API : ".$final_url."</span>");

    $data = file_get_contents($final_url);

    file_put_contents($cacheFile, $data);

    return json_decode($data);

}

function retrieveDirectionsData($pOrigin, $pDestination, $pLanguage)
{
    trace("Willing to go from ".$pOrigin." to ".$pDestination." ".$pLanguage);
    
    $final_url = "https://maps.googleapis.com/maps/api/directions/json?origin=".$pOrigin."&destination=".$pDestination."&language=".$pLanguage."&key=".GMAP_KEY;

    $cache_key = "direction_".md5($pOrigin.$pDestination.$pLanguage);


    $cacheFile = CACHE_DIR.$cache_key;


    if(file_exists($cacheFile))
    {
        trace("<span style='font-size:10px'>Cache : ".$cacheFile.'</span>');
        return json_decode(file_get_contents($cacheFile));
    }

    trace("<span style='font-size:10px'>API : ".$final_url.'</span>');
    $data = file_get_contents($final_url);

    file_put_contents($cacheFile, $data);

    return json_decode($data);
}

function getDist($pFrom, $pTo)
{
    $a = array(
        "lat"=>degreeToRadian($pFrom->lat),
        "lng"=>degreeToRadian($pFrom->lng)
    );

    $b = array(
        "lat"=>degreeToRadian($pTo->lat),
        "lng"=>degreeToRadian($pTo->lng)
    );

    $v = EARTH_RADIUS * acos((sin($a["lat"])*sin($b["lat"])) + (cos($a["lat"])*cos($b["lat"])*cos($a["lng"]-$b["lng"])));

    return $v;
}

function degreeToRadian($pValue)
{
    return $pValue * (M_PI/180);
}

function radianToDegree($pValue)
{
    return $pValue * (180 / M_PI);
}

function getDuration($pValue)
{
    if($pValue<60)
    {
        return ceil($pValue)." sec";
    }

    $pValue /= 60;

    return ceil($pValue)." min";
}

function formatDate($pStrDate)
{
    global $dictionary;
    $strtotime = strtotime($pStrDate);
    $day = $dictionary['days'][date('N', $strtotime)-1];
    $month = $dictionary['months'][date('n', $strtotime)-1];

    return $day.' '.date('d', $strtotime).' '.$month.' '.date('H', $strtotime).'h';
    
}

class Description
{
    public $duration;
    public $distance;
    public $text;

    public function __construct($pDuration=null, $pDistance=null, $pText=null)
    {
        $this->duration = $pDuration;
        $this->distance = $pDistance;
        $this->text = $pText;
    }
}

$data = retrieveDirectionsData($origin, $destination, $lang);

if(!$data||!isset($data->routes)||$data->status!=="OK"||empty($data->routes))
{
    trace("no can do");
}

$route = $data->routes[0];

$leg = $route->legs[0];

trace(getDist($leg->start_location, $leg->end_location));

$duration = 0;

$fromLocation = $leg->start_location;
$fromLocation->name = $leg->start_address;
$fromLocation->duration = 0;
$interestsPoints = array($fromLocation);
$instructionPoints = array();

foreach($leg->steps as $s)
{
    $instructionPoints[] = new Description($s->duration->text, $s->distance->text, $s->html_instructions);
    $duration += $s->duration->value;

    $dist = getDist($fromLocation, $s->end_location);
    $dist_arrival = getDist($s->end_location, $leg->end_location);
    if($dist > DIST_GAP && $dist_arrival > DIST_GAP)
    {
        $s->end_location->duration = $duration;
        $interestsPoints[] = $s->end_location;
        $fromLocation = $s->end_location;
    }
}

trace_r($instructionPoints);

$toLocation = $leg->end_location;
$toLocation->name = $leg->end_address;
$toLocation->duration = $leg->duration->value;
$interestsPoints[] = $leg->end_location;


foreach($interestsPoints as &$point)
{
    $forecast = retrieveForecastData($point, $lang);

    if(!isset($point->name)||empty($point->name))
    {
        $point->name = $forecast->city->name;
    }
    $point->forecast = $forecast->list;
}

trace_r($interestsPoints);


?>
<!DOCTYPE html>
<html>
    <head>
        <title>DryDrive</title>
        <link href="assets/css/weather-icons.min.css" rel="stylesheet">
        <style>
        *{font-family:Arial,sans-serif;}
        .container .steps{flex-grow:0;width:300px;overflow:hidden;}
        .container .steps .step{padding:10px;border-bottom:solid 1px #999;}
        .container .container{flex-grow:1;overflow:auto;border-left:solid 2px #eee;padding-left:10px;}
        .container{display:flex;}
        .container .point.first{width:100px;}
        .container .point.first .label {height:80px;}
        .container .point.first .weather{padding-top:10px;height:62px;border-top:solid 2px #eee}
        .container .point{width:200px;margin:0 10px;}
        .container .point .header{height:80px;text-align:center;}
        .container .point .header h4{margin:0;padding:0;}
        .container .point .header .duration{font-style:italic;}
        .container .point .weather{height:100px;text-align:center;}
        .container .point .weather span{display:block;}
        .container .point .weather .weather-icon{font-size:30px;width:44px;height:44px;margin:0 auto;}
        .container .point .weather .humidity{font-size:12px;}
        .container .point .weather span.wi-humidity{display:inline-block;color:#666;}
        .container .point .weather span.temp{font-weight:bold;}
        </style>
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    </head>
    <body>
    <div class="form">
        <select name="lang">
<?php
foreach($availables_lang as $v=>$l)
{
    echo "<option value='".$v."'>".$l."</option>";
}
?>
        </select>
        <form action="index.php" method="get">
            <input type="text" value="<?php echo $origin; ?>" placeholder="From :" name="origin">
            <input type="text" value="<?php echo $destination; ?>" placeholder="To :" name="destination">
            <input type="submit" value="Go">
        </form>
    </div>
    <div class="container">
        <div class="steps">
<?php
foreach($instructionPoints as $p)
{
    echo "<div class='step'>";
    echo "<div class='instructions'>".$p->text."</div>";
    echo "<div class='duration'>".$p->duration."</div>";
    echo "<div class='distance'>".$p->distance."</div>";
    echo "</div>";
}
?>
        </div>
        <div class='container'>

            <div class='point first'>
                <div class='label'></div>

<?php
foreach($interestsPoints[0]->forecast as $f)
{
    $date = $f->dt_txt;

    echo "<div class='weather'>";
    echo formatDate($date);
    echo "</div>";
}
?>
            </div>

<?php
foreach($interestsPoints as $p)
{
    echo "<div class='point'>";
    echo "<div class='header'>";
    echo "<div class='duration'>".(isset($p->duration)&&$p->duration?getDuration($p->duration):"Départ")."</div>";
    echo "<h4>".$p->name."</h4>";
    echo "</div>";
    foreach($p->forecast as $f)
    {
        echo "<div class='weather'>";
        echo "<span class='weather-icon wi ".$icons[$f->weather[0]->icon]." ".$f->weather[0]->icon."' title='".ucfirst($f->weather[0]->description)."'></span>";
        echo "<span class='temp'>".round($f->main->temp)."°C</span>";
        echo "<span class='humidity'>".$f->main->humidity." %</span>";
        echo "</div>";
    }
    echo "</div>";
}
?>
        </div>
    </div>
    </body>
</html>

<?php
echo "<div style='position:fixed;bottom:0;right:0;width:50%;height:200px;background:#fff;padding:10px;border:solid 1px #444;overflow:auto;'>".$debug."</div>";
