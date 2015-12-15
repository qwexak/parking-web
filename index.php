<?
require __DIR__ . '/vendor/autoload.php';
session_start();
function Fetch($query, $param = [ ]) {
	global $db;
	if (is_array($param) || count($param) > 0) {
		if (!array_key_exists('limit', $param)) $param['limit'] = 100;
		foreach ($param as $key => $val) $query = str_replace("{" . $key . "}", $val, $query);
	}
	$res = $db->query($query);
	$rows = [ ];
	while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
		$rows[] = $row;
	}
	return $rows;
}

$config = [
	'dbs'   => [
		0 => [ 'name' => 'ТЦ Остров', 'alias' => 'ostrov', 'fname' => 'ostrov.base.sqlite' ],
		1 => [ 'name' => 'Петр Столыпин', 'alias' => 'petr', 'fname' => 'petr.base.sqlite' ],
	],
	'query' => [
		'getEvents'           => "
			SELECT id,datetimeevent,ideventname,type,station,sourceid,source,cardtype,zone,number
			FROM `logjoin`
			ORDER BY datetime(datetimeevent) LIMIT {limit}",
		'getEventsID'         => "
			SELECT id,name
			FROM `logeventname`
			WHERE id in (SELECT DISTINCT(log.ideventname) FROM log)",
		'getEventsStats'      => "
			SELECT t1.ideventname AS ideventname,t1.type AS type, count(0) AS total, month
			FROM `logjoin` AS t1
			LEFT JOIN (
				SELECT ideventname, type, count(0) AS month
				FROM `logjoin`
				WHERE strftime('%m',datetimeevent) = '{month}'
				GROUP BY ideventname) AS t2 ON t1.ideventname = t2.ideventname
			GROUP BY t1.ideventname",
		'getEventsByIDs'      => "
			SELECT id,datetimeevent,ideventname,type,station,sourceid,source,cardtype,zone,number
			FROM `logjoin`
			WHERE ideventname IN ({ids})
			ORDER BY datetime(datetimeevent) DESC LIMIT {limit}",
		'getEventsByID'       => "
			SELECT id,datetimeevent,ideventname,type,station,sourceid,source,cardtype,zone,number
			FROM `logjoin`
			WHERE ideventname = {id}
			ORDER BY datetime(datetimeevent) DESC LIMIT {limit}",
		'getEventsByNumber'   => "
			SELECT id,datetimeevent,ideventname,type,station,sourceid,source,cardtype,zone,number
			FROM `logjoin`
			WHERE number = '{number}'
			ORDER BY datetime(datetimeevent) DESC LIMIT {limit}",
		'getEventsBySourceID' => "
			SELECT id,datetimeevent,ideventname,type,station,sourceid,source,cardtype,zone,number
			FROM `logjoin`
			WHERE sourceid = {id}
			ORDER BY datetime(datetimeevent) DESC LIMIT {limit}",
		'getSplash'           => [
			'ostrov' => "
			SELECT number,time_start,time_end,ownername,name AS cardtype,
				CASE WHEN sheduleid = '0' THEN 'Въезд по билетам'
				WHEN sheduleid = '2' THEN 'Сотрудники ТРЦ'
				WHEN sheduleid = '6' THEN 'Карта 24/7'
				END AS sheduleid
			FROM `Card`
			JOIN logcardtype ON card.card_type = logcardtype.Id
			ORDER BY sheduleid,ownername",
			'petr'   => "
			SELECT number,time_start,time_end,ownername,name AS cardtype,sheduleid
			FROM `Card`
			JOIN logcardtype ON card.card_type = logcardtype.Id
			ORDER BY sheduleid,ownername",
		],
	],
];
$env['db'] = isset($_SESSION['db']) ? $_SESSION['db'] : $config['dbs'][0];
$env['dbs'] =& $config['dbs'];
$db = new SQLite3($env['db']['fname']);
$db->exec('pragma short_column_names  = false'); //TODO: все в lowercase при создание
$loader = new Twig_Loader_Filesystem(__DIR__ . '/templates');
$twig = new Twig_Environment($loader);
$query = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : "";
$action = explode('/', substr($query, 1));
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
	//JSON
	if ($_SERVER['REQUEST_URI'] = '/getEvents') {
		$events = $_POST['events'];
		if (count($events > 0)) {
			$events = implode(',', $events);
			echo json_encode(Fetch($config['query']['getEventsByIDs'], [ 'ids' => $events ]));
		}
	}
} else {
	if ($action[0] == 'card') {
		//=============================card=============================
		if (isset($action[1])) {
			$res = Fetch($config['query']['getEventsByNumber'], [ 'number' => $action[1] ]);
			echo $twig->render('log_detail.twig', array( 'cards' => $res, 'env' => $env ));
		} else {
			$res = Fetch($config['query']['getSplash'][$env['db']['alias']], [ 'limit' => 500 ]);
			echo $twig->render('card.twig', array( 'cards' => $res, 'env' => $env ));
		}
	} elseif ($action[0] == 'db') {
		//=============================db=============================
		$_SESSION['db'] = $config['dbs'][$action[1]];
		$url = (isset($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : '/';
		header('Location: ' . $url);
	} elseif ($action[0] == 'stats') {
		//=============================stats=============================
		if (isset($action[1]) && $action[1] == 'event') {
			$res = Fetch($config['query']['getEventsByID'], [ 'id' => $action[2] ]);
			echo $twig->render('log_detail.twig', array( 'cards' => $res, 'env' => $env ));
		} elseif (isset($action[1]) && $action[1] == 'source') {
			$res = Fetch($config['query']['getEventsBySourceID'], [ 'id' => $action[2] ]);
			echo $twig->render('log_detail.twig', array( 'cards' => $res, 'env' => $env ));
		} else {
			$month = date('m');
			$res = Fetch($config['query']['getEventsStats'], [ 'month' => $month ]);
			echo $twig->render('stats.twig', array( 'events' => $res, 'env' => $env ));
		}
	} else {
		$eventsid = Fetch($config['query']['getEventsID'], [ 'limit' => 150 ]);
		$res = Fetch($config['query']['getEvents'], [ 'limit' => 30 ]);
		echo $twig->render('log_detail.twig', array( 'cards' => $res, 'events' => $eventsid, 'main' => true, 'env' => $env ));
	}
}

