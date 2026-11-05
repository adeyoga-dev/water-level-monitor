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
$ranges=validateRanges([['min_level'=>null,'max_level'=>20],['min_level'=>20,'max_level'=>40],['min_level'=>40,'max_level'=>null]]);
check(classify(19.99,$ranges)===0 && classify(20,$ranges)===1 && classify(40,$ranges)===2,'alert range exact boundaries');
check(alertLabel(0)==='Siaga 1' && alertLabel(1)==='Siaga 2' && alertLabel(2)==='Siaga 3' && alertLabel(null)==='Unclassified','Indonesian display labels preserved with English fallback');
check(invalid(fn()=>validateRanges([['min_level'=>0,'max_level'=>20],['min_level'=>19,'max_level'=>40],['min_level'=>40,'max_level'=>50]])), 'overlap rejected');
check(invalid(fn()=>validateRanges([['min_level'=>20,'max_level'=>20],[],[]])), 'empty interval rejected');
$gaps=validateRanges([['min_level'=>0,'max_level'=>10],['min_level'=>20,'max_level'=>30],['min_level'=>40,'max_level'=>50]]);
check(classify(15,$gaps)===null,'unclassified gap');
check(!alarmOn(20,20,true) && alarmOn(20.1,20,true) && !alarmOn(21,20,false) && !alarmOn(19,20,true),'buzzer strict threshold and switch');
check(validVersion('v1.10.2')==='v1.10.2' && invalid(fn()=>validVersion('v01.2.3')),'firmware version validation');

if (!in_array('--integration',$argv,true)) { echo "$checks checks passed. Run --integration for real MySQL/HTTP tests.\n"; exit; }
$server=null;$pdo=null;$testDatabase='water_level_test_'.bin2hex(random_bytes(6));
try {
    $pdo=new PDO('mysql:host='.config('DB_HOST','127.0.0.1').';port='.config('DB_PORT','3306').';charset=utf8mb4',config('DB_USERNAME','root'),config('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE DATABASE `'.$testDatabase.'` CHARACTER SET utf8mb4');
    $pdo->exec('USE `'.$testDatabase.'`');
    $schema=file_get_contents(dirname(__DIR__).'/database/schema.sql');
    $schema=preg_replace('/^CREATE DATABASE[^;]+;\s*USE[^;]+;/','',$schema);
    foreach(explode(';',$schema) as $statement) if(trim($statement)!=='') $pdo->exec($statement);
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
    check($connected,'HTTP server started');
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
    foreach(['/','/devices.php','/device-edit.php?id='.$id,'/logs.php?device_id=ESP32-TEST&alarm_status=1','/firmwares.php','/api/dashboard.php'] as $path) check(request($path)[0]===200,'page '.$path);
    check(request('/logs.php?from=2026-02-30')[0]===200,'invalid filter handled');
    [$code,$html]=request('/firmwares.php');preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$match);$token=$match[1]??'';
    check($token!=='','CSRF token provided');
    [$code,$html]=request('/device-edit.php');preg_match('/name="csrf" value="([a-f0-9]+)"/',$html,$deviceToken);
    $deviceForm=['csrf'=>$deviceToken[1],'device_id'=>'ESP32-FORM','alarm_threshold'=>20,'hours'=>0,'minutes'=>0,'seconds'=>30,'ranges'=>$ranges];
    check(request('/device-edit.php','POST',http_build_query($deviceForm),false)[0]===302,'device created through HTTP form');
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
