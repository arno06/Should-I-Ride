<?php

define('CONFIG_FILE', '../../config/services.json');

$config = json_decode(file_get_contents(CONFIG_FILE), true);

define('CACHE_DIR', 'includes/cache/');
define('GMAP_KEY', $config['api']['gmap']['key']);
define('OWEATHER_KEY', $config['api']['openweather']['key']);
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

include_once('includes/locales/'.$lang.'.php');

/**
 * Debug - trace & trace_r emulated from debugger
 **/
$debug = [];
function trace($pString){global $debug;$debug[] = $pString;}
function trace_r($pData){global $debug;$debug[] = $pData;}

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


header('Content-type: application/json');
echo json_encode(array('interest_points'=>$interestsPoints, 'debug'=>$debug));
exit();
