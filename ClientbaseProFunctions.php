<?php

	// файл с PHP функциями проекта ClientbasePro

	// предопределённые константы
define('NULL_DATETIME', '0000-00-00 00:00:00');
define('NULL_DATE', '0000-00-00');	

	
	
	
	
	
	
	
	
	
	
	
	

	// преобразует кол-во секунд $sec в формат чч:мм:сс      
function TimeFormat(int $sec) {
    if (!is_numeric($sec)) return "00:00:00";
    else {
        $hour = intval(floor($sec/3600));
        if ($hour<10) $hour = "0".$hour;
        $minute_ = $sec - 3600*$hour;
        $minute = intval(floor($minute_/60));
        if ($minute<10) $minute = "0".$minute;
        $second = intval($sec - 3600*$hour - 60*$minute);
        if ($second<10) $second = "0".$second;
        return $hour.":".$minute.":".$second;
    }
}

        // функция получает курс валюты $currency с сайта ЦБ РФ на дату $date
		// без параметров получаем курс Евро на сегодня
function GetCurrency($date, $currency='EUR') {
		// проверка входных данных
	if (!$date) $date = date("d/m/Y");
	else $date = date("d/m/Y", strtotime($date));
		// ссылка на сайте ЦБ РФ
	$url = 'http://www.cbr.ru/scripts/XML_daily.asp?date_req='.$date;
		// запрос к сайту ЦБ РФ
	$curl = curl_init($url);
	curl_setopt_array($curl, array(CURLOPT_RETURNTRANSFER=>true));
    if ($response=curl_exec($curl)) {
		$pattern = '/<Valute ID=\"R.{5}\".+?<CharCode>'.$currency.'<\/CharCode>(.*?)<\/Valute\>/is';
		preg_match($pattern, $response, $m);
		preg_match("/<Value>(.*?)<\/Value>/is", $m[1], $r);
		return floatval(str_replace(",", ".", $r[1]));
	}
	return false;
}




    // функция возвращает форматированный номер +7XXXXXXXXXXX
	// $number - номер телефона в любом формате
	// $code - код города по умолчанию, по умолчанию 495 Москва
	// $plus - отображать ли "+" перед выводимым номером
function SetNumber($number, $code='', $plus='+') {
    $plus = (!$plus || '+'==$plus) ? $plus : '+';
	if (!$code && DEFAULT_PHONE_CODE) $code = DEFAULT_PHONE_CODE;
		// оставляем только цифры в $number
	$str = strval($number);
    $str = preg_replace('/\D/i','',$str);
    $strlen = strlen($str);
        // сначала иностранные, начинающиеся на 810
    if ('810'==$str[0].$str[1].$str[2]) return $str;
		// номера РБ
    if (0===strpos($str,'375') && 12==$strlen) return $plus.$str;
        // далее короткие внутренние 3-хзначные
    if (3==$strlen && 1000>$str) return $str;
        // далее российские 11-значные номера, начинающиеся на 7 или 8
    if (11==$strlen && ('7'==$str[0] || '8'==$str[0])) { $str = substr($str,1); return $plus.'7'.$str; }
        // далее 10-значные, к ним дописываем 7
    if (10==$strlen) return $plus.'7'.$str; 
        // суммируем длину кода и длину номера
    $code = preg_replace('/\D/i', '', strval($code));
    if ($code && 10==($strlen+strlen($code))) return $plus.'7'.$code.$str;
    return '';
}


    // функция возвращает id клиента по номеру телефона $number или эл.почте $email (кроме $someId)
function GetAccount($number='',$email='',$someId=0) {
        // проверка входных условий
    $number = SetNumber($number);
    if ((!$number && !$email) || (!$number && $email && !filter_var($email,FILTER_VALIDATE_EMAIL))) return 'invalid or empty input data';
		// проверка id таблиц и полей
	$accountTableId = intval(ACCOUNT_TABLE);
	$accountFieldPhone = intval(ACCOUNT_FIELD_PHONE);
	$accountFieldEmail = intval(ACCOUNT_FIELD_EMAIL);
	$accountFieldDouble = intval(ACCOUNT_FIELD_DOUBLE);
	if (!$accountTableId || !$accountFieldPhone || !$accountFieldEmail) return 'invalid table or fields settings';
	$contactTableId = intval(CONTACT_TABLE);
	$contactFieldPhone = intval(CONTACT_FIELD_PHONE);
	$contactFieldEmail = intval(CONTACT_FIELD_EMAIL);
	$contactFieldAccountId = intval(CONTACT_FIELD_ACCOUNTID);
        // набор условий для SQL-запросов
    $numberCond = $emailCond = $mainCond = $idCond = '';
    $shortPhoneCond = ($number<1000) ? "" : " OR f".$accountFieldPhone." LIKE '%".$number."%'";
    if ($number && !$email) $numberCond = " AND (f".$accountFieldPhone."='".$number."' {$shortPhoneCond}) ";
    if ($email && !$number) $emailCond = " AND (f".$accountFieldEmail."='".$email."' OR f".$accountFieldEmail." LIKE '%".$email."%') ";
    if ($number && $email) $mainCond = " AND ((f".$accountFieldPhone."='".$number."' {$shortPhoneCond}) OR (f".$accountFieldEmail."='".$email."' OR f".$accountFieldEmail." LIKE '%".$email."%')) ";
    if ($someId) $idCond = " AND id!='".$someId."' ";
	if ($accountFieldDouble) $doubleCond = " AND f".$accountFieldDouble."='' ";
        // 1 попытка - прямое совпадение или LIKE
	$row = sql_fetch_assoc(data_select_field($accountTableId, 'id', "status=0 {$numberCond} {$emailCond} {$mainCond} {$idCond} {$doubleCond} ORDER BY add_time DESC LIMIT 1"));
    if ($row['id']) return $row['id'];
        // 2 попытка - поиск по номеру телефона
    if ($number) {
        $res = data_select_field($accountTableId, 'id, f'.$accountFieldPhone.' as phone', "status=0 AND f".$accountFieldPhone."!='' {$doubleCond} {$idCond} ORDER BY add_time DESC");
        while ($row=sql_fetch_assoc($res)) {
            $phones = explode(',', $row['phone']);
            foreach ($phones as $p) if (SetNumber($p)==$number) return $row['id'];
        }
    }
		// 3 попытка - поиск через контактное лицо
	if ($contactTableId && $contactFieldPhone && $contactFieldEmail && $contactFieldAccountId) {
		$contact = intval(GetContact($number,$email));
		if ($contact) {
			$row = sql_fetch_assoc(data_select_field($contactTableId, 'f'.$contactFieldAccountId.' AS accountId', "id='".$contact."' LIMIT 1"));
			if ($row['accountId']) return $row['accountId'];		
		}	
	}
    return false;
}


    // функция возвращает id контактного лица по номеру телефона $number или эл.почте $email (кроме $someId)
function GetContact($number='',$email='',$someId=0) {
        // проверка входных условий
    $number = SetNumber($number);
    if ((!$number && !$email) || (!$number && $email && !filter_var($email,FILTER_VALIDATE_EMAIL))) return 'invalid or empty input data';
		// проверка id таблиц и полей	
	$tableId = intval(CONTACT_TABLE);
	$fieldPhone = intval(CONTACT_FIELD_PHONE);
	$fieldEmail = intval(CONTACT_FIELD_EMAIL);
	$fieldAccountId = intval(CONTACT_FIELD_ACCOUNTID);
	if (!$tableId || !$fieldPhone || !$fieldEmail) return 'invalid table or fields settings';
        // набор условий для SQL-запросов
    $numberCond = $emailCond = $mainCond = $idCond = '';
    $shortPhoneCond = ($number<1000) ? "" : " OR f".$fieldPhone." LIKE '%".$number."%'";
    if ($number && !$email) $numberCond = " AND (f".$fieldPhone."='".$number."' {$shortPhoneCond}) ";
    if ($email && !$number) $emailCond = " AND (f".$fieldEmail."='".$email."' OR f".$fieldEmail." LIKE '%".$email."%') ";
    if ($number && $email) $mainCond = " AND ((f".$fieldPhone."='".$number."' {$shortPhoneCond}) OR (f".$fieldEmail."='".$email."' OR f".$fieldEmail." LIKE '%".$email."%')) ";
    if ($someId) $idCond = " AND id!='".$ID."' ";
        // 1 попытка - прямое совпадение или LIKE
	$row = sql_fetch_assoc(data_select_field($tableId, 'id', "status=0 {$numberCond} {$emailCond} {$mainCond} {$idCond} ORDER BY add_time DESC LIMIT 1"));
    if ($row['id']) return $row['id'];
        // 2 попытка - поиск по номеру телефона
    if ($number) {
        $res = data_select_field($tableId, 'id, f'.$fieldPhone.' as phone', "status=0 AND f".$fieldPhone."!=''  {$idCond} ORDER BY add_time DESC");
        while ($row=sql_fetch_assoc($res)) {
            $phones = explode(',', $row['phone']);
            foreach ($phones as $p) if (SetNumber($p)==$number) return $row['id'];
        }
    }
    return false;
}

    // функция обновляет контактные данные ($phone и $email) клиента $accountId
function UpdateAccount($accountId=0,$phone='',$email='') {
		// проверка входных данных
	$accountId = intval($accountId);
    $email = ($email && filter_var($email,FILTER_VALIDATE_EMAIL)) ? $email : '';
    $phone = ($phone=SetNumber($phone)) ? $phone : '';
    if (!$accountId || (!$email && !$phone)) return 'invalid or empty input data';
		// проверка id таблиц и полей
	$tableId = intval(ACCOUNT_TABLE);
	$fieldPhone = intval(ACCOUNT_FIELD_PHONE);
	$fieldEmail = intval(ACCOUNT_FIELD_EMAIL);
	if (!$tableId || !$fieldPhone || !$fieldEmail) return 'invalid table or fields settings';
    $row = sql_fetch_assoc(data_select_field($tableId, 'f'.$fieldPhone.' AS phone, f'.$fieldEmail.' AS email', "status=0 AND id='".$accountId."' LIMIT 1"));
        // добавление E-mail
    if ($email && false===strpos($row['email'],$email)) $upd['f'.$fieldEmail] = (($row['email'])?$row['email'].'; ':'').$email;
        // добавление телефона
    if ($phone) {
        $continue = 1;
        $phones = explode(',', $row['phone']);
        foreach ($phones as $p) if (SetNumber($p)==$phone) { $continue = 0; break; }
        if ($continue) $upd['f'.$fieldPhone] = (($row['phone'])?$row['phone'].', ':'').$phone;            
    }
        // обновляем контактную информацию клиента
    if ($upd) { data_update($tableId, EVENTS_ENABLE, $upd, "id='".$accountId."' LIMIT 1"); return $upd; }
    return 'no data to update';
}

  // функция возвращает массив геоданных по IP-адресу
function GetIPAddressData($IP='') {
    // начальная проверка данных
  if (!$IP || ($IP && !filter_var($IP,FILTER_VALIDATE_IP))) return false;
    // создание cURL
  $ch = curl_init('http://ipgeobase.ru:7020/geo?ip='.$IP);
  curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  $string = curl_exec($ch);
  curl_close($ch);
    // парсим ответ
  $xml = simplexml_load_string($string);
  $data = '';
  foreach ($xml as $key=>$value) {
    foreach ($value as $key2=>$value2) {
      if ('city'==$key2) $data['city'] = $value2;
	  elseif ('region'==$key2) $data['region'] = $value2;     
      else if ('country'==$key2) $data['country'] = $value2;
    }
  }
  if ($data) return $data;
  return false;  
}








    // функция обрабатывает задачу по синхронизации КБ и 1С
	// int $someTableId - id таблицы, в которой синхронизируются данные
	// json $someData - перечень данных к синхронизации в формате JSON, [{"GUID":"XXXXXXX","fieldId":"fieldValue"}], поиск строки для синхронизации выполняется по GUID
	// $prefix - префикс таблиц в КБ, по умолчанию "cb_". В КБ хранится в конфиге в $config['table_prefix']
	// int $updateLimit - лимит кол-ва обновляемых строк за 1 запуск задачи, по умолчанию 99
	// int $insertLimit - лимит кол-ва добавляемых строк за 1 запуск задачи, по умолчанию 99
	// $startTime - полное время обработки задачи на настоящий момент, формат ЧЧ:ММ:СС
function Sync1CTask(int $someTableId, $someData, $prefix="cb_", $updateLimit=99, $insertLimit=99, $startTime) {    
		// проверка необходимых входных данных
	if (!($someTableId=intval($someTableId)) || !$someData) return false;
		// перевод времени обработки задачи из ЧЧ:ММ:СС в секунды
	$startTime = explode(":", $startTime);
    $startTime = intval($startTime[2]) + 60*intval($startTime[1]) + 3600*intval($startTime[0]);
    $start = time();
        // массив типов полей КБ
    $fieldTypes_ = array(
        1 => 'integer',
        2 => 'date',
        3 => 'string',
        4 => 'list',
        5 => 'link'
    );
    $data = array();
    
        // 1. массив всех записей всех таблиц GUID=>ID
    $res = sql_query("SELECT id, table_id FROM ".$prefix."fields WHERE name_field='GUID' AND id>0 AND table_id>0 AND type_field IN (1,2,3,4,5)");
    while ($row=sql_fetch_assoc($res)) $GUIDs[$row['table_id']] = 'f'.$row['id'];
    foreach ($GUIDs as $tableId=>$GUIDFieldId) {
        $res = data_select_field($tableId, 'id, '.$GUIDFieldId.' AS GUID', $GUIDFieldId."!=''");
        while ($row=sql_fetch_assoc($res)) $GUIDData[$row['GUID']] = $row['id'];    
    }    
        // 2. массив типов полей fieldId=>fieldType
    $res = sql_query("SELECT id, type_field FROM ".$prefix."fields WHERE id>0 AND type_field IN (1,2,3,4,5)");
    while ($row=sql_fetch_assoc($res)) $fieldTypes['f'.$row['id']] = $fieldTypes_[$row['type_field']];   
    
        // 3. форматируем полученные данные $someData в ассоциативный массив $data (числа -> float, даты -> "Y-m-d H:i:s", поля связи -> ID связанной записи)
    $data_ = json_decode($someData, true);
    foreach ($data_ as $row) {
        $tmp = array();
        foreach ($row as $key_=>$value_) {
            if ('GUID'==$key_) $key = $value_;
            elseif ($ft=$fieldTypes[$key_]) {
                if ('integer'==$ft) $tmp[$key_] = floatval($value_);
                elseif ('date'==$ft) $tmp[$key_] = (($tmp[$key_]=date("Y-m-d H:i:s",strtotime($value_))) && '0001-01-01 00:00:00'!=$tmp[$key_]) ? $tmp[$key_] : NULL_DATETIME;
                elseif ('link'==$ft) $tmp[$key_] = $GUIDData[$value_];
                else $tmp[$key_] = $value_;
            }
            $tmp[$GUIDs[$someTableId]] = $key;
            $data[$key] = $tmp;
        }
    }
    $tmp = array();
    
        // 4. выборка записей текущей таблицы
        // перечень полей в текущей таблице $someTableId
    $res = sql_query("SELECT id FROM ".$prefix."fields WHERE id>0 AND type_field IN (1,2,3,4,5) AND table_id='".$someTableId."'");
    while ($row=sql_fetch_assoc($res)) $fields[] = 'f'.$row['id'];
        // записи текущей таблицы - проходим и собираем массив $upd для обновления, при этом удаляя из исходного $data инфо по обновляемым строкам
    $res = data_select_field($someTableId, 'id'.(($fields)?','.implode(',',$fields):'').', '.($g=$GUIDs[$someTableId]).' AS GUID', "status=0 AND ".$g."!=''");
    while ($row=sql_fetch_assoc($res)) {
        foreach ($fields as $fieldId)
            if (isset($data[$row['GUID']][$fieldId]) && $data[$row['GUID']][$fieldId]!=$row[$fieldId]) 
                $upd[$row['id']][$fieldId] = $data[$row['GUID']][$fieldId];
        unset($data[$row['GUID']]);
    }
     
        // 5. массив для обновления $upd - записываем в КБ
    $i = 0;
    foreach ($upd as $lineId=>$fieldsData) while ($i<$updateLimit) { data_update($someTableId, EVENTS_ENABLE, $fieldsData, "id='".$lineId."' LIMIT 1"); $i++; }
   
        // 6. массив новых записей (остался от $data) - добавляем в КБ
    $i = 0;
    foreach ($data as $GUID=>$row) while ($i<$insertLimit) { data_insert($someTableId, EVENTS_ENABLE, $row); $i++; }
    
        // 7. вывод данных
    $a  = array('upd'=>$upd, 'ins'=>$data);
    if (count($upd)<=$updateLimit && count($data)<=$insertLimit) $a['done'] = 1;	// признак, что задача в целом обработана
    $a['time'] = TimeFormat(time()-$start+$startTime);								// время обработки в секундах
    $a['startTime'] = TimeFormat($startTime);										// время начала обработки
    $a['thisTime'] = TimeFormat(time()-$start);										// время завершения обработки
    return $a;
}






?>
