<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/core.php';
require dirname(__DIR__) . '/app/admin.php';
loadEnv(dirname(__DIR__) . '/.env');
$checks=0;
function check(bool $condition,string $message): void { global $checks; if(!$condition) throw new RuntimeException('FAIL: '.$message); $checks++; echo 'PASS '.$message."\n"; }
function invalid(callable $fn): bool { try{$fn();return false;}catch(InvalidArgumentException){return true;} }

$env=parseEnv("# comment\nDB_HOST = localhost\nDB_PASSWORD=\" space#pass=word \"\nEMPTY=\nSINGLE='a=b#c'\n");
check($env['DB_PASSWORD']===' space#pass=word ' && $env['SINGLE']==='a=b#c' && $env['EMPTY']==='', 'dotenv quotes, spaces, hash, equals, empty');
check(invalid(fn()=>finiteNumber('INF','water')) && invalid(fn()=>finiteNumber(NAN,'water')), 'non-finite sensor readings rejected');
$ranges=validateRanges([['min_level'=>null,'max_level'=>19],['min_level'=>20,'max_level'=>39],['min_level'=>40,'max_level'=>null]]);
check(classify(18.99,$ranges)===0 && classify(19,$ranges)===null && classify(20,$ranges)===1 && classify(40,$ranges)===2,'alert range exact boundaries');
check(alertLabel(0)==='Siaga 1' && alertLabel(1)==='Siaga 2' && alertLabel(2)==='Siaga 3' && alertLabel(null)==='Unclassified','Indonesian display labels preserved with English fallback');
check(invalid(fn()=>validateRanges([['min_level'=>0,'max_level'=>20],['min_level'=>19,'max_level'=>40],['min_level'=>41,'max_level'=>null]])), 'overlap rejected');
check(invalid(fn()=>validateRanges([['min_level'=>20,'max_level'=>20],[],[]])), 'empty interval rejected');
$gaps=validateRanges([['min_level'=>0,'max_level'=>10],['min_level'=>20,'max_level'=>30],['min_level'=>40,'max_level'=>null]]);
check(classify(15,$gaps)===null,'unclassified gap');
$equalFirst=$ranges;$equalFirst[0]['max_level']='20.00';
check(invalid(fn()=>validateRanges($equalFirst)),'Siaga 1 upper and Siaga 2 lower cannot be numerically equal');
$equalSecond=$ranges;$equalSecond[1]['max_level']='40.0';
check(invalid(fn()=>validateRanges($equalSecond)),'Siaga 2 upper and Siaga 3 lower cannot be numerically equal');
$zeroBoundary=$ranges;$zeroBoundary[0]['max_level']=0;$zeroBoundary[1]['min_level']='0.00';
check(invalid(fn()=>validateRanges($zeroBoundary)),'equal zero boundaries are rejected rather than treated as empty');
$decimalGap=$ranges;$decimalGap[0]['max_level']='19.99';
check(validateRanges($decimalGap)[0]['max_level']===19.99,'distinct decimal boundaries remain valid without enforcing an integer gap');
check(classify(39.99,$gaps)===null && classify(40,$gaps)===2 && classify(100000,$gaps)===2,'Siaga 3 includes its lower bound and has no upper limit');
check(invalid(fn()=>validateRanges([['min_level'=>null,'max_level'=>20],['min_level'=>20,'max_level'=>40],['min_level'=>40,'max_level'=>50]])),'Siaga 3 upper bound rejected server side');
check(invalid(fn()=>validateRanges([['min_level'=>null,'max_level'=>20],['min_level'=>20,'max_level'=>40],['min_level'=>null,'max_level'=>null]])),'Siaga 3 lower bound is required server side');
$legacyRanges=$ranges;$legacyRanges[2]['max_level']=50;
check(classify(40,$legacyRanges)===2 && classify(50,$legacyRanges)===2 && classify(999,$legacyRanges)===2,'legacy Siaga 3 upper bounds do not stop current classification');
check(!alarmOn(20,20,true) && alarmOn(20.1,20,true) && !alarmOn(21,20,false) && !alarmOn(19,20,true),'buzzer strict threshold and switch');
check(validVersion('v1.10.2')==='v1.10.2' && invalid(fn()=>validVersion('v01.2.3')),'firmware version validation');
check(parseCoordinates(' -7.566712, 110.833458 ') === [-7.566712,110.833458], 'Google Maps decimal coordinate pair is parsed in latitude longitude order');
check(parseCoordinates('0, 0') === [0.0,0.0] && parseCoordinates('90,-180') === [90.0,-180.0], 'zero and boundary coordinates are valid');
check(parseCoordinates('  ') === [null,null] && parseCoordinates('-7.123456789,110.123456789') === [-7.1234568,110.1234568], 'empty coordinates clear location and precision matches database');
foreach (['91,110','-91,110','0,181','0,-181','NaN,110','INF,110','-7','-7,110,12','https://maps.app.goo.gl/example','<script>alert(1)</script>','-7,5,110,5',[],null] as $badCoordinates) {
    check(invalid(fn()=>parseCoordinates($badCoordinates)), 'invalid coordinate value rejected: '.json_encode($badCoordinates));
}

if (!in_array('--integration',$argv,true)) { echo "$checks checks passed. Run --integration for real MySQL/HTTP tests.\n"; exit; }
$server=null;$pdo=null;$testDatabase='water_level_test_'.bin2hex(random_bytes(6));
try {
    $pdo=new PDO('mysql:host='.config('DB_HOST','127.0.0.1').';port='.config('DB_PORT','3306').';charset=utf8mb4',config('DB_USERNAME','root'),config('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE DATABASE `'.$testDatabase.'` CHARACTER SET utf8mb4');
    $pdo->exec('USE `'.$testDatabase.'`');
    $schema=file_get_contents(dirname(__DIR__).'/database/schema.sql');
    $schema=preg_replace('/^CREATE DATABASE[^;]+;\s*USE[^;]+;/','',$schema);
    foreach(explode(';',$schema) as $statement) if(trim($statement)!=='') $pdo->exec($statement);
    $pdo->exec('ALTER TABLE devices DROP COLUMN latitude, DROP COLUMN longitude');
    $pdo->exec("INSERT INTO devices(device_id,alarm_threshold,created_at,updated_at) VALUES('MIGRATION-EXISTING',20,'2026-01-01 00:00:00','2026-01-01 00:00:00')");
    $legacyLocationRow=$pdo->query("SELECT * FROM devices WHERE device_id='MIGRATION-EXISTING'")->fetch(PDO::FETCH_ASSOC);
    $locationMigration=file_get_contents(dirname(__DIR__).'/database/migrations/002_device_coordinates.sql');
    foreach(explode(';',$locationMigration) as $statement) if(trim($statement)!=='') $pdo->exec($statement);
    check(count($pdo->query("SHOW COLUMNS FROM devices WHERE Field IN ('latitude','longitude')")->fetchAll())===2, 'coordinate migration upgrades an existing schema');
    $migratedLocationRow=$pdo->query("SELECT * FROM devices WHERE device_id='MIGRATION-EXISTING'")->fetch(PDO::FETCH_ASSOC);
    check($migratedLocationRow['latitude']===null && $migratedLocationRow['longitude']===null && array_diff_key($migratedLocationRow,['latitude'=>true,'longitude'=>true])===$legacyLocationRow,'coordinate migration preserves existing device data with no invented location');
    putenv('DB_DATABASE='.$testDatabase);
    putenv('APP_URL=http://127.0.0.1:18765');
    $input=['device_id'=>'ESP32-TEST','alarm_threshold'=>'20','hours'=>'0','minutes'=>'1','seconds'=>'0','ranges'=>$ranges];
    $id=saveDevice($input);
    check($id>0,'schema import and create device');
    $pdo->exec('RENAME TABLE device_alert_ranges TO device_siaga_ranges');
    $pdo->exec('ALTER TABLE device_siaga_ranges DROP INDEX device_alert, ADD UNIQUE KEY device_siaga (device_id, alarm_status)');
    $migration=file_get_contents(dirname(__DIR__).'/database/migrations/001_english_alert_names.sql');
    foreach(explode(';',$migration) as $statement) if(trim($statement)!=='') $pdo->exec($statement);
    check((int)query('SELECT COUNT(*) FROM device_alert_ranges WHERE device_id=?',[$id])->fetchColumn()===3,'English table migration preserves existing ranges');
    check(invalid(fn()=>saveDevice($input)),'duplicate device rejected');
    check(invalid(fn()=>saveDevice($input+['id'=>$id,'device_id'=>'OTHER']))===false,'device update accepts unchanged ID');
    $changed=$input;$changed['id']=$id;$changed['device_id']='OTHER';
    check(invalid(fn()=>saveDevice($changed)),'device identity immutable');
    $server=proc_open([PHP_BINARY,'-d','upload_max_filesize=20M','-d','post_max_size=24M','-S','127.0.0.1:18765','-t',dirname(__DIR__).'/public'],[['pipe','r'],['file',dirname(__DIR__).'/storage/logs/test-server.log','a'],['file',dirname(__DIR__).'/storage/logs/test-server.log','a']],$pipes,dirname(__DIR__));
    if(!is_resource($server)) throw new RuntimeException('Cannot start PHP test server');
    fclose($pipes[0]);
    $connected=false;
    for($i=0;$i<40;$i++){ $s=@fsockopen('127.0.0.1',18765,$errno,$errstr,.1);if($s){fclose($s);$connected=true;break;}usleep(100000); }
    check($connected && proc_get_status($server)['running'],'HTTP server started');
    function request(string $path,string $method='GET',mixed $body=null,bool $json=true): array {
        $ch=curl_init('http://127.0.0.1:18765'.$path);
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_TIMEOUT=>10,CURLOPT_COOKIEFILE=>dirname(__DIR__).'/tests/.tmp-cookie',CURLOPT_COOKIEJAR=>dirname(__DIR__).'/tests/.tmp-cookie']);
        if($body!==null) {curl_setopt($ch,CURLOPT_POSTFIELDS,$json?json_encode($body):$body); if($json)curl_setopt($ch,CURLOPT_HTTPHEADER,['Content-Type: application/json']);}
        $response=curl_exec($ch);$code=curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
        if($response===false)throw new RuntimeException($error);
        return [$code,$response,json_decode($response,true)];
    }
    [$code,,$body]=request('/api/device-config.php?device_id=ESP32-TEST');
    check($code===200 && $body['latest_firmware']===null && $body['update_interval']===60,'GET configuration without firmware');
    check((int)query('SELECT device_status FROM devices WHERE id=?',[$id])->fetchColumn()===0 && (int)query('SELECT COUNT(*) FROM sensor_logs')->fetchColumn()===0,'GET does not mark online or insert logs');
    $data=['device_id'=>'ESP32-TEST','water_level'=>20,'device_status'=>1,'firmware_version'=>'v1.0.0'];
    [$code,,$body]=request('/api/telemetry.php','POST',$data);
    check($code===200 && $body['buzzer_status']===0,'telemetry successful');
    check((int)query('SELECT alarm_status FROM sensor_logs LIMIT 1')->fetchColumn()===1,'telemetry classified and logged');
    check(query('SELECT reported_firmware FROM devices WHERE id=?',[$id])->fetchColumn()==='v1.0.0','reported firmware stored');
    $bad=$data;$bad['device_id']='UNKNOWN';check(request('/api/telemetry.php','POST',$bad)[0]===404,'unknown device 404');
    $bad=$data;$bad['water_level']='no';check(request('/api/telemetry.php','POST',$bad)[0]===422,'invalid water 422');
    $bad=$data;$bad['device_status']='1';check(request('/api/telemetry.php','POST',$bad)[0]===422,'status type validated');
    check((int)query('SELECT COUNT(*) FROM sensor_logs')->fetchColumn()===1,'invalid requests do not insert logs');
    check(request('/api/telemetry.php')[0]===405,'method 405');
    // Force a log INSERT failure; assert the device UPDATE is rolled back as well.
    $pdo->exec("CREATE TRIGGER test_log_failure BEFORE INSERT ON sensor_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='intentional test failure'");
    $bad=$data;$bad['water_level']=999;
    check(request('/api/telemetry.php','POST',$bad)[0]===500,'database error yields 500');
    check((float)query('SELECT water_level FROM devices WHERE id=?',[$id])->fetchColumn()===20.0,'telemetry update rolled back on log failure');
    $pdo->exec('DROP TRIGGER test_log_failure');
    query('UPDATE devices SET last_seen_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 181 SECOND) WHERE id=?',[$id]);
    markOffline();check((int)query('SELECT device_status FROM devices WHERE id=?',[$id])->fetchColumn()===0,'offline timeout');
    foreach(['/','/devices.php','/maps.php','/device-edit.php?id='.$id,'/logs.php?device_id=ESP32-TEST&alarm_status=1','/firmwares.php','/api/dashboard.php'] as $path) check(request($path)[0]===200,'page '.$path);
    check(request('/logs.php?from=2026-02-30')[0]===200,'invalid filter handled');
    [$code,$html]=request('/firmwares.php');preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$match);$token=$match[1]??'';
    check($token!=='','CSRF token provided');
    [$code,$html]=request('/device-edit.php');preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$deviceToken);
    $deviceForm=['csrf'=>$deviceToken[1],'device_id'=>'ESP32-FORM','alarm_threshold'=>20,'hours'=>0,'minutes'=>0,'seconds'=>30,'ranges'=>$ranges];
    check(request('/device-edit.php','POST',http_build_query($deviceForm),false)[0]===302,'device created through HTTP form');
    $locationForm=$deviceForm;$locationForm['device_id']='ESP32-MAP';$locationForm['coordinates']='-7.566712, 110.833458';
    $logsBeforeLocation=(int)query('SELECT COUNT(*) FROM sensor_logs')->fetchColumn();
    check(request('/device-edit.php','POST',http_build_query($locationForm),false)[0]===302,'device created with coordinates through master form');
    $locationId=(int)query('SELECT id FROM devices WHERE device_id=?',['ESP32-MAP'])->fetchColumn();
    check(query('SELECT latitude,longitude FROM devices WHERE id=?',[$locationId])->fetch()===['latitude'=>'-7.5667120','longitude'=>'110.8334580'],'latitude and longitude stored on device');
    $mapHtml=request('/maps.php')[1];preg_match('/<script id="map-data" type="application\/json">(.*?)<\/script>/s',$mapHtml,$mapMatch);
    $mapPoints=json_decode($mapMatch[1]??'',true);
    check(count($mapPoints)===1 && $mapPoints[0]['device_id']==='ESP32-MAP' && $mapPoints[0]['latitude']===-7.566712 && str_contains($mapHtml,'leaflet-1.9.4/leaflet.js'),'map contains only devices with coordinates and uses local Leaflet');
    $locationForm['id']=$id;$locationForm['coordinates']='0, 0';
    $unrelatedDevice=query('SELECT * FROM devices WHERE id=?',[$id])->fetch();
    check(request('/device-edit.php?id='.$locationId,'POST',http_build_query($locationForm),false)[0]===302 && query('SELECT * FROM devices WHERE id=?',[$id])->fetch()===$unrelatedDevice,'posted ID cannot redirect a location update away from the URL device');
    check(query('SELECT latitude,longitude FROM devices WHERE id=?',[$locationId])->fetch()===['latitude'=>'0.0000000','longitude'=>'0.0000000'],'location replaces previous coordinates and accepts zero');
    check(str_contains(request('/maps.php')[1],'"latitude":0,"longitude":0'),'zero coordinates are included in map data');
    $beforeInvalidLocation=query('SELECT * FROM devices WHERE id=?',[$locationId])->fetch();
    $locationForm['coordinates']='91, 110';
    $invalidLocationResponse=request('/device-edit.php?id='.$locationId,'POST',http_build_query($locationForm),false);
    check($invalidLocationResponse[0]===422 && str_contains($invalidLocationResponse[1],'value="91, 110"') && query('SELECT * FROM devices WHERE id=?',[$locationId])->fetch()===$beforeInvalidLocation,'invalid location returns 422 and retains draft without writes');
    $locationForm['coordinates']='-7,110';$locationForm['csrf']='bad';
    check(request('/device-edit.php?id='.$locationId,'POST',http_build_query($locationForm),false)[0]===422 && query('SELECT * FROM devices WHERE id=?',[$locationId])->fetch()===$beforeInvalidLocation,'location form rejects invalid CSRF without writes');
    $locationForm['csrf']=$deviceToken[1];$locationForm['coordinates']='';
    check(request('/device-edit.php?id='.$locationId,'POST',http_build_query($locationForm),false)[0]===302 && query('SELECT latitude,longitude FROM devices WHERE id=?',[$locationId])->fetch()===['latitude'=>null,'longitude'=>null],'blank coordinates remove the current location');
    check((int)query('SELECT COUNT(*) FROM sensor_logs')->fetchColumn()===$logsBeforeLocation && count(query('SHOW TABLES')->fetchAll())===4,'location creates updates and removals do not create sensor or location history');
    $beforeRollbackLocation=query('SELECT * FROM devices WHERE id=?',[$locationId])->fetch();
    $beforeRollbackRanges=query('SELECT * FROM device_alert_ranges WHERE device_id=? ORDER BY alarm_status',[$locationId])->fetchAll();
    $pdo->exec("CREATE TRIGGER test_location_range_failure BEFORE INSERT ON device_alert_ranges FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='intentional location test failure'");
    $rollbackInput=$locationForm;$rollbackInput['id']=$locationId;$rollbackInput['coordinates']='-6.2,106.8';$locationFailed=false;
    try { saveDevice($rollbackInput); } catch (PDOException $exception) { $locationFailed=true; }
    $pdo->exec('DROP TRIGGER test_location_range_failure');
    check($locationFailed && query('SELECT * FROM devices WHERE id=?',[$locationId])->fetch()===$beforeRollbackLocation && query('SELECT * FROM device_alert_ranges WHERE device_id=? ORDER BY alarm_status',[$locationId])->fetchAll()===$beforeRollbackRanges,'location update rolls back with device settings if saving alert ranges fails');
    $binary=dirname(__DIR__).'/tests/.tmp-firmware.bin';file_put_contents($binary,"\xE9".random_bytes(2047));
    $form=['csrf'=>$token,'version'=>'v1.1.0','description'=>'Test <script>unsafe</script>','firmware'=>new CURLFile($binary,'application/octet-stream','update.bin')];
    check(request('/firmwares.php','POST',$form,false)[0]===302,'multipart firmware uploaded');
    [$code,,$body]=request('/api/device-config.php?device_id=ESP32-TEST');
    check($code===200 && $body['latest_firmware']==='v1.1.0' && $body['firmware_sha256']===hash_file('sha256',$binary),'global firmware configuration and checksum');
    check(query('SELECT current_firmware FROM devices WHERE id=?',[$id])->fetchColumn()==='v1.1.0','global target updated');
    $download=request(parse_url($body['ota_url'],PHP_URL_PATH).'?'.parse_url($body['ota_url'],PHP_URL_QUERY));
    check($download[0]===200 && $download[1]===file_get_contents($binary),'download exact binary');
    $form['version']='v1.0.0';check(str_contains(request('/firmwares.php','POST',$form,false)[1],'higher'),'downgrade rejected');
    check((int)query('SELECT COUNT(*) FROM firmwares')->fetchColumn()===1,'failed upload leaves no firmware row');
    $form['version']='v1.2.0';
    $form['firmware']=new CURLFile($binary,'text/plain','wrong.txt');
    check(str_contains(request('/firmwares.php','POST',$form,false)[1],'.bin extension'),'wrong extension rejected');
    $form['firmware']=new CURLFile($binary,'application/octet-stream','update.bin');
    $beforeFiles=glob(dirname(__DIR__).'/storage/firmware/*.bin');
    $pdo->exec("CREATE TRIGGER test_firmware_failure BEFORE INSERT ON firmwares FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='intentional firmware failure'");
    check(request('/firmwares.php','POST',$form,false)[0]===500,'firmware database failure yields 500');
    check(glob(dirname(__DIR__).'/storage/firmware/*.bin')===$beforeFiles && latestFirmware()['version']==='v1.1.0','failed publication removes binary and preserves global version');
    $pdo->exec('DROP TRIGGER test_firmware_failure');
    file_put_contents($binary,'');
    check(str_contains(request('/firmwares.php','POST',$form,false)[1],'Firmware size'),'empty binary rejected');
    file_put_contents($binary,str_repeat('x',17*1024*1024));
    check(str_contains(request('/firmwares.php','POST',$form,false)[1],'Firmware size'),'oversize binary rejected');
    $form['csrf']='bad';check(str_contains(request('/firmwares.php','POST',$form,false)[1],'expired'),'CSRF rejected');
    $new=$input;$new['device_id']='ESP32-NEW';$newId=saveDevice($new);
    check(query('SELECT current_firmware FROM devices WHERE id=?',[$newId])->fetchColumn()==='v1.1.0','new device inherits global firmware');
    $edit=$input;$edit['id']=$id;$edit['buzzer_status']='1';$edit['hours']='1';$edit['minutes']='2';$edit['seconds']='3';saveDevice($edit);
    [$code,,$body]=request('/api/telemetry.php','POST',$data);
    check($body['buzzer_status']===1 && $body['update_interval']===3723,'updated switch and HH:MM:SS returned to ESP32');
    check(str_contains(request('/firmwares.php')[1],'&lt;script&gt;unsafe&lt;/script&gt;'),'changelog HTML escaped');
    for($i=0;$i<26;$i++) query('INSERT INTO sensor_logs(device_id,water_level,alarm_status,created_at) VALUES(?,?,?,?)',['ESP32-TEST',5,0,'2026-11-04 00:00:00']);
    $filtered=request('/logs.php?device_id=ESP32-TEST&alarm_status=0&from=2026-11-04&to=2026-11-04&page=2')[1];
    check(str_contains($filtered,'26 logs') && str_contains($filtered,'Page 2 of 2'),'combined log filters and pagination');
    $beforeHistory=query('SELECT * FROM devices WHERE id=?',[$id])->fetch();
    $beforeHistoryCount=(int)query('SELECT COUNT(*) FROM sensor_logs')->fetchColumn();
    [$code,,$history]=request('/api/device-history.php?device_id=ESP32-TEST');
    check($code===200 && $history['status']==='success' && $history['device']['device_id']==='ESP32-TEST' && $history['timezone']==='Asia/Jakarta','public device history endpoint returns scoped data and timezone');
    check(count($history['points'])===(int)query('SELECT COUNT(*) FROM sensor_logs WHERE device_id=?',['ESP32-TEST'])->fetchColumn(),'history returns actual recorded readings');
    $times=array_column($history['points'],'x');$sortedTimes=$times;sort($sortedTimes);
    check($times===$sortedTimes && is_int($times[0]) && $times[0]>1000000000000,'history points use ascending UTC epoch milliseconds');
    check(query('SELECT * FROM devices WHERE id=?',[$id])->fetch()===$beforeHistory && (int)query('SELECT COUNT(*) FROM sensor_logs')->fetchColumn()===$beforeHistoryCount,'history GET does not update devices or insert readings');
    check(request('/device-history.php?device_id=ESP32-TEST')[0]===200,'device detail page renders');
    $detailHtml=request('/device-history.php?device_id=ESP32-TEST')[1];
    check(str_contains($detailHtml,'/assets/vendor/chartjs-4.6.1/chart.umd.min.js') && !str_contains($detailHtml,'cdn.jsdelivr.net') && str_contains($detailHtml,'id="history-data"') && str_contains($detailHtml,'id="history-chart"'),'detail page includes local Chart.js and history chart without CDN');
    check(request('/api/device-history.php?device_id=UNKNOWN')[0]===404 && request('/device-history.php?device_id=UNKNOWN')[0]===404,'unknown history device returns controlled 404');
    check(request('/api/device-history.php?device_id=bad%27OR%201%3D1')[0]===422 && request('/api/device-history.php?device_id[]=ESP32-TEST')[0]===422 && request('/api/device-history.php')[0]===422,'invalid history identifiers rejected');
    check(request('/api/device-history.php?device_id=ESP32-TEST','POST')[0]===405,'history endpoint rejects writes');
    check(request('/device-history.php?device_id=%3Cscript%3E')[0]===404,'invalid history page identifier returns safe 404');
    check((int)query('SELECT COUNT(*) FROM sensor_logs')->fetchColumn()===$beforeHistoryCount,'denied and invalid history requests have no write side effects');
    [$code,,$emptyHistory]=request('/api/device-history.php?device_id=ESP32-NEW');
    check($code===200 && $emptyHistory['points']===[],'device without historical readings returns an empty series');
    query('UPDATE device_alert_ranges SET max_level=4 WHERE device_id=? AND alarm_status=0',[$id]);
    $changedHistory=request('/api/device-history.php?device_id=ESP32-TEST')[2];
    check(count(array_filter($changedHistory['points'],fn($point)=>$point['y']===5 && $point['alarm_status']===0))===26,'historical classifications remain unchanged when current ranges change');
    query('UPDATE device_alert_ranges SET max_level=20 WHERE device_id=? AND alarm_status=0',[$id]);
    db()->beginTransaction();
    for($i=0;$i<520;$i++) {
        query('INSERT INTO sensor_logs(device_id,water_level,alarm_status,created_at) VALUES(?,?,?,?)',['ESP32-TEST',1000+$i,2,'2099-01-01 00:00:00']);
    }
    query('INSERT INTO sensor_logs(device_id,water_level,alarm_status,created_at) VALUES(?,?,?,?)',['ESP32-NEW',99999,2,'2099-01-01 00:00:00']);
    db()->commit();
    $limitedHistory=request('/api/device-history.php?device_id=ESP32-TEST')[2];
    check(count($limitedHistory['points'])===500 && $limitedHistory['limit']===500 && $limitedHistory['points'][0]['y']===1020 && $limitedHistory['points'][499]['y']===1519,'history is bounded to the latest 500 readings with stable ordering for equal timestamps');
    check(!in_array(99999,array_column($limitedHistory['points'],'y'),true),'tampering with history device selection cannot mix another device readings');
    $periodDevice=$input;$periodDevice['device_id']='ESP32-PERIOD';saveDevice($periodDevice);
    foreach ([['2025-01-14 16:59:59',999],['2025-01-14 17:00:00',10],['2025-01-15 05:00:00',30],['2025-01-15 16:59:59',50],['2025-01-15 17:00:00',70],['2025-02-02 04:00:00',80],['2026-01-03 05:00:00',90]] as [$time,$level]) {
        query('INSERT INTO sensor_logs(device_id,water_level,alarm_status,created_at) VALUES(?,?,?,?)',['ESP32-PERIOD',$level,2,$time]);
    }
    $periodPath='/api/device-history.php?device_id=ESP32-PERIOD&';
    [$code,,$daily]=request($periodPath.'from=2025-01-15&to=2025-01-15&group_by=day');
    check($code===200 && $daily['aggregation']==='day' && $daily['reading_count']===3 && count($daily['points'])===1,'date range includes both local date boundaries and excludes adjacent readings');
    check($daily['points'][0]['y']===30 && $daily['points'][0]['min']===10 && $daily['points'][0]['max']===50 && $daily['points'][0]['count']===3,'daily average preserves minimum maximum and sample count');
    check($daily['period']['from']===strtotime('2025-01-14 17:00:00 UTC')*1000 && $daily['period']['to']===strtotime('2025-01-15 17:00:00 UTC')*1000-1,'filtered axis uses inclusive selected local dates converted to UTC');
    $hourly=request($periodPath.'from=2025-01-15&to=2025-01-15')[2];
    check($hourly['aggregation']==='hour' && count($hourly['points'])===24 && $hourly['points'][0]['y']===10 && $hourly['points'][12]['y']===30 && $hourly['points'][23]['y']===50,'auto single day uses chronological hourly calendar buckets');
    [$code,,$minutes]=request($periodPath.'from=2025-01-15&to=2025-01-15&group_by=minute');
    check($code===200 && $minutes['aggregation']==='minute' && count($minutes['points'])===1440 && $minutes['points'][0]['y']===10 && $minutes['points'][720]['y']===30 && $minutes['points'][1439]['y']===50,'minute grouping includes the entire local day with correct midnight and final minute boundaries');
    check($minutes['reading_count']===3 && $minutes['points'][1]['y']===null && $minutes['points'][1]['count']===0 && $minutes['points'][1]['x']-$minutes['points'][0]['x']===60000,'minute grouping preserves actual time spacing and null intervals');
    $minuteAggregate=request('/api/device-history.php?device_id=ESP32-TEST&from=2099-01-01&to=2099-01-01&group_by=minute')[2];
    check($minuteAggregate['reading_count']===520 && $minuteAggregate['points'][420]['y']===1259.5 && $minuteAggregate['points'][420]['min']===1000 && $minuteAggregate['points'][420]['max']===1519 && $minuteAggregate['points'][420]['count']===520,'minute averages extrema and sample counts include all scoped readings beyond the raw cap');
    $minuteError=request($periodPath.'from=2025-01-15&to=2025-01-16&group_by=minute');
    check($minuteError[0]===422 && str_contains($minuteError[2]['message'],'single date'),'minute grouping rejects multiple dates with clear validation');
    $emptyMinutes=request('/api/device-history.php?device_id=ESP32-NEW&from=2024-01-01&to=2024-01-01&group_by=minute')[2];
    check(count($emptyMinutes['points'])===1440 && $emptyMinutes['reading_count']===0 && count(array_filter($emptyMinutes['points'],fn($point)=>$point['y']!==null))===0,'empty minute history has no invented zero readings');
    $daily=request($periodPath.'from=2025-01-15&to=2025-01-17')[2];
    check($daily['aggregation']==='day' && array_column($daily['points'],'y')===[30,70,null] && $daily['points'][2]['count']===0 && $daily['points'][2]['min']===null,'auto short range uses daily buckets and missing intervals remain null');
    $monthly=request($periodPath.'from=2025-01-15&to=2025-12-31')[2];
    check($monthly['aggregation']==='month' && count($monthly['points'])===12 && $monthly['points'][0]['y']===40 && $monthly['points'][1]['y']===80 && $monthly['reading_count']===5,'auto annual range averages all readings by month including partial first month');
    check($monthly['points'][0]['x']===$monthly['period']['from'],'partial first calendar bucket stays inside selected axis range');
    $yearly=request($periodPath.'from=2025-01-15&to=2026-12-31&group_by=year')[2];
    check($yearly['aggregation']==='year' && count($yearly['points'])===2 && $yearly['points'][0]['y']===48 && $yearly['points'][1]['y']===90,'year grouping uses weighted means of actual readings rather than means of means');
    check(request($periodPath.'from=2025-01-15&to=2028-01-01')[2]['aggregation']==='year','auto long range groups by year');
    $leap=request($periodPath.'from=2024-02-27&to=2024-03-01&group_by=day')[2];
    check(count($leap['points'])===4 && $leap['reading_count']===0 && array_column($leap['points'],'y')===[null,null,null,null],'leap day and empty calendar intervals are retained without fabricated zeros');
    $allReadings=request('/api/device-history.php?device_id=ESP32-TEST&from=2099-01-01&to=2099-01-01&group_by=day')[2];
    check($allReadings['reading_count']===520 && $allReadings['points'][0]['y']===1259.5 && $allReadings['points'][0]['min']===1000 && $allReadings['points'][0]['max']===1519 && $allReadings['limit']===null,'filtered aggregates include all selected readings beyond the raw 500 reading cap and remain device scoped');
    $beforeFilterCount=(int)query('SELECT COUNT(*) FROM sensor_logs')->fetchColumn();
    foreach (['from=2025-02-30&to=2025-03-01','from=2025-01-16&to=2025-01-15','from=2025-01-15','from[]=2025-01-15&to=2025-01-16','from=2025-01-15&to=2025-01-16&group_by=bad','from=2025-01-15&to=2025-01-16&group_by[]=day','from=2025-01-01&to=2025-12-31&group_by=hour'] as $filters) {
        check(request($periodPath.$filters)[0]===422,'invalid or excessive history filter rejected: '.$filters);
    }
    check(request('/device-history.php?device_id=ESP32-PERIOD&from=2025-02-30&to=2025-03-01')[0]===422,'invalid page date filters produce a controlled validation response');
    check((int)query('SELECT COUNT(*) FROM sensor_logs')->fetchColumn()===$beforeFilterCount,'filtered GET and invalid filters never insert readings');
    foreach ([['2024-11-03 05:30:00',11],['2024-11-03 06:30:00',22]] as [$time,$level]) {
        query('INSERT INTO sensor_logs(device_id,water_level,alarm_status,created_at) VALUES(?,?,?,?)',['ESP32-PERIOD',$level,0,$time]);
    }
    $originalZone=config('APP_TIMEZONE','Asia/Jakarta');
    try {
        putenv('APP_TIMEZONE=America/New_York');
        $dst=deviceHistory('ESP32-PERIOD',['from'=>'2024-11-03','to'=>'2024-11-03','group_by'=>'hour']);
        check(count($dst['points'])===25 && $dst['points'][1]['y']===11.0 && $dst['points'][2]['y']===22.0 && $dst['points'][2]['x']-$dst['points'][1]['x']===3600000,'repeated daylight saving hour keeps distinct chronological buckets without MySQL timezone tables');
        $dstMinutes=deviceHistory('ESP32-PERIOD',['from'=>'2024-11-03','to'=>'2024-11-03','group_by'=>'minute']);
        check(count($dstMinutes['points'])===1500 && $dstMinutes['points'][90]['y']===11.0 && $dstMinutes['points'][150]['y']===22.0 && $dstMinutes['points'][150]['x']-$dstMinutes['points'][90]['x']===3600000,'minute grouping preserves the two occurrences of a repeated local minute on a 25-hour day');
    } finally {
        putenv('APP_TIMEZONE='.$originalZone);
    }
    $invalidForm=$deviceForm;$invalidForm['device_id']='ESP32-REJECTED';$invalidForm['ranges'][2]['max_level']=50;
    $rejectedForm=request('/device-edit.php','POST',http_build_query($invalidForm),false);
    check(str_contains($rejectedForm[1],'Siaga 3 has no upper bound.') && (int)query('SELECT COUNT(*) FROM devices WHERE device_id=?',['ESP32-REJECTED'])->fetchColumn()===0,'tampered Siaga 3 upper bound is rejected through HTTP without saving a device');
    unset($invalidForm['ranges'][2]['max_level']);$invalidForm['ranges'][2]['min_level']='';
    check(str_contains(request('/device-edit.php','POST',http_build_query($invalidForm),false)[1],'Siaga 3 requires a lower bound.') && (int)query('SELECT COUNT(*) FROM devices WHERE device_id=?',['ESP32-REJECTED'])->fetchColumn()===0,'missing Siaga 3 lower bound is rejected through HTTP without writes');
    $legacyDevice=$input;$legacyDevice['device_id']='ESP32-LEGACY';$legacyId=saveDevice($legacyDevice);
    query('UPDATE device_alert_ranges SET max_level=50 WHERE device_id=? AND alarm_status=2',[$legacyId]);
    $legacyTelemetry=$data;$legacyTelemetry['device_id']='ESP32-LEGACY';$legacyTelemetry['water_level']=999;
    check(request('/api/telemetry.php','POST',$legacyTelemetry)[0]===200 && (int)query('SELECT alarm_status FROM sensor_logs WHERE device_id=? ORDER BY id DESC LIMIT 1',['ESP32-LEGACY'])->fetchColumn()===2,'telemetry above an obsolete stored upper bound is recorded as Siaga 3');
    $legacyForm=$deviceForm;$legacyForm['id']=$legacyId;$legacyForm['device_id']='ESP32-LEGACY';unset($legacyForm['ranges'][2]['max_level']);
    check(request('/device-edit.php?id='.$legacyId,'POST',http_build_query($legacyForm),false)[0]===302 && query('SELECT max_level FROM device_alert_ranges WHERE device_id=? AND alarm_status=2',[$legacyId])->fetchColumn()===null,'saving an existing device without a Siaga 3 upper field clears its obsolete upper bound');
    foreach ([0=>'Siaga 1 upper bound must not equal Siaga 2 lower bound.',1=>'Siaga 2 upper bound must not equal Siaga 3 lower bound.'] as $status=>$message) {
        $equalForm=$deviceForm;$equalForm['device_id']='ESP32-EQUAL-'.$status;
        $equalForm['ranges'][$status]['max_level']=(string)$equalForm['ranges'][$status+1]['min_level'].'.00';
        $rejected=request('/device-edit.php','POST',http_build_query($equalForm),false);
        check(str_contains($rejected[1],$message) && (int)query('SELECT COUNT(*) FROM devices WHERE device_id=?',[$equalForm['device_id']])->fetchColumn()===0,'equal adjacent boundary rejected through HTTP without creating device: '.$status);
        $beforeDevice=query('SELECT * FROM devices WHERE id=?',[$legacyId])->fetch();
        $beforeRanges=query('SELECT * FROM device_alert_ranges WHERE device_id=? ORDER BY alarm_status',[$legacyId])->fetchAll();
        $equalForm['id']=$legacyId;$equalForm['device_id']='ESP32-LEGACY';$equalForm['alarm_threshold']=999;
        $rejected=request('/device-edit.php?id='.$legacyId,'POST',http_build_query($equalForm),false);
        check(str_contains($rejected[1],$message) && query('SELECT * FROM devices WHERE id=?',[$legacyId])->fetch()===$beforeDevice && query('SELECT * FROM device_alert_ranges WHERE device_id=? ORDER BY alarm_status',[$legacyId])->fetchAll()===$beforeRanges,'equal adjacent boundary edit retains device settings and ranges: '.$status);
    }
    echo "$checks checks passed.\n";
} catch(Throwable $e) {
    fwrite(STDERR,(string)$e."\n");$failed=true;
} finally {
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if($pdo && preg_match('/^water_level_test_[a-f0-9]{12}$/D',$testDatabase)) {
        try {
            foreach($pdo->query('SELECT file_path FROM firmwares')->fetchAll(PDO::FETCH_COLUMN) as $file) {
                if(preg_match('#^storage/firmware/[a-f0-9]{32}\.bin$#D',$file)) @unlink(dirname(__DIR__).'/'.$file);
            }
            $pdo->exec('DROP DATABASE `'.$testDatabase.'`');
        } catch(Throwable $cleanupError){fwrite(STDERR,'Cleanup: '.$cleanupError->getMessage()."\n");}
    }
    foreach(['.tmp-firmware.bin','.tmp-cookie'] as $file) if(is_file(__DIR__.'/'.$file)) unlink(__DIR__.'/'.$file);
}
exit(isset($failed)?1:0);
