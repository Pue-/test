<?php

//db配置
function conf() {
	return array(
		'host' => '',
		'user' => 'root',
		'passwd' => '123456',
		'port' => '4000',
		'dbname' => '',
	);
}

//统计耗时
function getCurrentTime ()  {  
	list ($msec, $sec) = explode(" ", microtime());  
	return (float)$msec + (float)$sec;  
}

//默认用车事由
function reason() {
	return array(
		'加班',
		'商务出行',
		'出差'
	);
}

//连接数据库
function connctDb() {
	$confData = conf();
	$conn=mysql_connect($confData['host'].':'.$confData['port'], $confData['user'], $confData['passwd']);
	return $conn;
}

//产生18位随机数--用车事由id
function randomNum() {
	$flag = rand(1,5);
	$vsid = rand(6,20);
	$autoIncSig = rand(0,1000000);
	$ntime = microtime(true)- mktime(0, 0, 0, 1, 1, 2013);
	$timeSig = intval($ntime * 1000);
	$serialId = $timeSig << 8 | $flag;
	$serialId = $serialId << 8 | $vsid;
	$serialId = $serialId << 5 | ($autoIncSig % 1024);
	return $serialId;
}

//同步company数据
function getDataFromCompany($fileName) {

	$startTime = getCurrentTime();

	if (!file_exists('failed_company.txt')) {
		$handle1 = fopen('failed_company.txt' ,"a+");
	}
	
	$handle2 = fopen($fileName, "r");
	if (!$handle2) {
		die('open file fail');
	}

	$monitor = 0;
	$num = 0;

	$reasonData = reason();

	while($line = fgets($handle2, 4096)) {
		$conn = connctDb();
		if (!$conn) {
			die('Could not connect: ' . mysql_error());
		}
		
		$companyId = intval($line);
		$confData = conf();
		mysql_select_db($confData['dbname']);
		mysql_query("set names utf8");

		//判断公司是否存在
		$sql = 'select * from es_use_car_reason where company_id=' . $companyId;
		$ret = mysql_query($sql, $conn);
		if (empty($row = mysql_fetch_row($ret))) {
			foreach ($reasonData as $index => $item) {
				$sql = 'insert into es_use_car_reason (`id`,`company_id`,`use_reason`,`detail`,`create_time`) values('. randomNum() .','.$companyId. ',' .'\''.$item.'\''.',1,' .time(). ')';
				$result = mysql_query($sql);
				if (!$result) {
					file_put_contents('failed_company.txt', '[1]'.$sql ."\n", FILE_APPEND);
				}
				//理论randomNum()不会重复
				usleep(5000);
			}
		} else {
			//判断‘加班’,'商务出行','出差'是否存在
			foreach ($reasonData as $index => $item) {
				$sql = 'select * from es_use_car_reason where use_reason=\''.$item.'\' and company_id=' .$companyId;
				$result = mysql_query($sql);
				if (empty($row = mysql_fetch_row($result))) {
					$sql = 'insert into es_use_car_reason (`id`,`company_id`,`use_reason`,`detail`,`create_time`) values('.randomNum().','.$companyId. ',' .'\''.$item.'\''. ',1,' .time(). ')';
					$result = mysql_query($sql);
					if (!$result) {
						file_put_contents('failed_company.txt', '[2]'.$sql ."\n", FILE_APPEND);
						//echo '新公司新增执行失败:' .$sql;
					}
					usleep(5000);
				}
			}
		}
		
		$monitor++;
		
		//每隔100次db断开一次，并sleep 0.05 秒
		if ($monitor >= 100) {
			$monitor = 0;
			mysql_close($conn);
			$num++;
			echo '[' .$num .']:sleep 0.05 sec ...'. "\n";
			usleep(50000);
		}
	}
	fclose($handle1);
	fclose($handle2);
	mysql_close($conn);
	$endTime = getCurrentTime();
	echo "总耗时：" .($endTime - $startTime) ."\n"; 
}

$ret = getDataFromCompany('./company.txt');
