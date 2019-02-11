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
    curl_close($curl);
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
    if ((!$number && !$email) || (!$number && $email && !filter_var($email,FILTER_VALIDATE_EMAIL))) return false;
        // проверка id таблиц и полей
    $accountTableId = intval(ACCOUNT_TABLE);
    $accountFieldPhone = intval(ACCOUNT_FIELD_PHONE);
    $accountFieldEmail = intval(ACCOUNT_FIELD_EMAIL);
    $accountFieldDouble = intval(ACCOUNT_FIELD_DOUBLE);
    if (!$accountTableId || !$accountFieldPhone || !$accountFieldEmail) return false;
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
    if ((!$number && !$email) || (!$number && $email && !filter_var($email,FILTER_VALIDATE_EMAIL))) return false;
        // проверка id таблиц и полей   
    $tableId = intval(CONTACT_TABLE);
    $fieldPhone = intval(CONTACT_FIELD_PHONE);
    $fieldEmail = intval(CONTACT_FIELD_EMAIL);
    $fieldAccountId = intval(CONTACT_FIELD_ACCOUNTID);
    $contactFieldDouble = intval(CONTACT_FIELD_DOUBLE);
    if (!$tableId || !$fieldPhone || !$fieldEmail) return false;
        // набор условий для SQL-запросов
    $numberCond = $emailCond = $mainCond = $idCond = '';
    $shortPhoneCond = ($number<1000) ? "" : " OR f".$fieldPhone." LIKE '%".$number."%'";
    if ($number && !$email) $numberCond = " AND (f".$fieldPhone."='".$number."' {$shortPhoneCond}) ";
    if ($email && !$number) $emailCond = " AND (f".$fieldEmail."='".$email."' OR f".$fieldEmail." LIKE '%".$email."%') ";
    if ($number && $email) $mainCond = " AND ((f".$fieldPhone."='".$number."' {$shortPhoneCond}) OR (f".$fieldEmail."='".$email."' OR f".$fieldEmail." LIKE '%".$email."%')) ";
    if ($contactFieldDouble) $doubleCond = " AND f".$contactFieldDouble."='' ";
    if ($someId) $idCond = " AND id!='".$ID."' ";
        // 1 попытка - прямое совпадение или LIKE
    $row = sql_fetch_assoc(data_select_field($tableId, 'id', "status=0 {$numberCond} {$emailCond} {$mainCond} {$idCond} {$doubleCond} ORDER BY add_time DESC LIMIT 1"));
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


    // функция обновляет контактные данные ($phone и $email) контрагента $accountId
function UpdateAccount($accountId=0,$phone='',$email='') {
        // проверка входных данных
    $accountId = intval($accountId);
    $email = ($email && filter_var($email,FILTER_VALIDATE_EMAIL)) ? $email : '';
    $phone = ($phone=SetNumber($phone)) ? $phone : '';
    if (!$accountId || (!$email && !$phone)) return false;
        // проверка id таблиц и полей
    $tableId = intval(ACCOUNT_TABLE);
    $fieldPhone = intval(ACCOUNT_FIELD_PHONE);
    $fieldEmail = intval(ACCOUNT_FIELD_EMAIL);
    if (!$tableId || !$fieldPhone || !$fieldEmail) return false;
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
    return false;
}


    // функция обновляет контактные данные ($phone и $email) контакта $accountId
function UpdateContact($accountId=0,$phone='',$email='') {
        // проверка входных данных
    $accountId = intval($accountId);
    $email = ($email && filter_var($email,FILTER_VALIDATE_EMAIL)) ? $email : '';
    $phone = ($phone=SetNumber($phone)) ? $phone : '';
    if (!$accountId || (!$email && !$phone)) return false;
        // проверка id таблиц и полей
    $tableId = intval(CONTACT_TABLE);
    $fieldPhone = intval(CONTACT_FIELD_PHONE);
    $fieldEmail = intval(CONTACT_FIELD_EMAIL);
    if (!$tableId || !$fieldPhone || !$fieldEmail) return false;
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
        // обновляем контактную информацию контакта
    if ($upd) { data_update($tableId, EVENTS_ENABLE, $upd, "id='".$accountId."' LIMIT 1"); return $upd; }
    return false;
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
    if (count($upd)<=$updateLimit && count($data)<=$insertLimit) $a['done'] = 1;    // признак, что задача в целом обработана
    $a['time'] = TimeFormat(time()-$start+$startTime);                              // время обработки в секундах
    $a['startTime'] = TimeFormat($startTime);                                       // время начала обработки
    $a['thisTime'] = TimeFormat(time()-$start);                                     // время завершения обработки
    return $a;
}


    // функция добавляет или убирает (по $type = add/delete) значение $value (или массив значений) в список значений из поля $fieldId в таблицу $tableId в запись $lineId
function SetCheckList($tableId, $fieldId, $lineId, $value, $type='add') {
    // список возможных значений $type
  $types = array('add','delete');
    // проверка входных данных
  if (!in_array($type,$types) || !$value || !$fieldId || !$lineId || !$tableId) return false;
    // если $value 1 значение, переводим его в вид массива
  $values = (!is_array($value)) ? array($value) : $value;
    // получаем список всех галочек из настроек поля
  $row = sql_fetch_assoc(sql_select_field(FIELDS_TABLE, "type_value", "id='".$fieldId."' LIMIT 1", $tableId));
  $all_list = explode("\r\n", $row['type_value']);
    // получаем список отмеченных галочек из записи $lineId
  $row = sql_fetch_assoc(data_select_field($tableId, 'f'.$fieldId.' AS field', "status=0 AND id='".$lineId."' LIMIT 1"));
  $checked_list = explode("\r\n", $row['field']);
    // проходим по списку всех галочек и добавляем/убираем значение $value в итоговый массив $all_array
  foreach ($all_list as $current) {
    if ('add'==$type) $cond = (in_array($current,$checked_list) || in_array($current,$values));
    if ('delete'==$type) $cond = (in_array($current,$checked_list) && !in_array($current,$values));
    if ($cond) $all_array[] = $current;
  }
    // массив для обновления записи $lineId
  $upd['f'.$fieldId] = implode("\r\n", $all_array);
  data_update($tableId, EVENTS_ENABLE, $upd ,"id='".$lineId."' LIMIT 1");
  return true;
}


  // функция очищает поле $fieldId строки $lineId таблицы $tableID и удаляет привязанные файлы
function DeleteFiles($tableId, $fieldId, $lineId) {
    // проверка входных данных
  if (!$tableId || !$fieldId || !$lineId) return false;
  $tableId = intval($tableId);
  $fieldId = intval($fieldId);
  $lineId = intval($lineId);
  if (!$tableId || !$fieldId || !$lineId) return false;
    // получаем значение поля, проходим по каждому элементу и удаляем соответствующий файл с диска
  $row = sql_fetch_assoc(data_select_field($tableId, 'f'.$fieldId.' AS files', "id='".$lineId."' LIMIT 1"));
  $files = explode("\r\n", $row['files']);
  foreach ($files as $file) @unlink(get_file_path($fieldId, $lineId, $file));
    // очищаем поле с файлами
  data_update($tableId, array('f'.$fieldId=>''), "id='".$lineId."' LIMIT 1");
  return true;
}


  // функция копирует файлы из $source в $destination (оба - массивы ('tableId', 'lineId', 'fieldId'))
function CopyFiles($source, $destination) {
    // проверка входных данных
  if (!$source || !is_array($source) || !$destination || !is_array($destination)) return false;
  $source['tableId'] = intval($source['tableId']);
  $source['lineId'] = intval($source['lineId']);
  $source['fieldId'] = intval($source['fieldId']);
  $destination['tableId'] = intval($destination['tableId']);
  $destination['lineId'] = intval($destination['lineId']);
  $destination['fieldId'] = intval($destination['fieldId']);
  if (!$source['tableId'] || !$source['lineId'] || !$source['fieldId'] || !$destination['tableId'] || !$destination['lineId'] || !$destination['fieldId']) return false;
    // получаем список файлов в источнике
  $source['files'] = sql_fetch_assoc(data_select_field($source['tableId'], 'f'.$source['fieldId'].' AS files', "id='".$source['lineId']."' LIMIT 1"));
  $sourceFiles = explode("\r\n", trim($source['files']['files']));
    // получаем список файлов в получателе
  $destination['files'] = sql_fetch_assoc(data_select_field($destination['tableId'], 'f'.$destination['fieldId'].' AS files', "id='".$destination['lineId']."' LIMIT 1"));
  $destinationFiles = explode("\r\n", $destination['files']['files']);
    // проходим по всем файлам источника и копируем их, имена файлов добавляем в $destinationFiles
  foreach ($sourceFiles as $file) {
      // создаём папку
    create_data_file_dirs($destination['fieldId'], $destination['lineId'], $file);
      // копируем файл и добавляем его имя в $destination['files']
    $file1 = get_file_path($source['fieldId'], $source['lineId'], $file);
    $file2 = get_file_path($destination['fieldId'], $destination['lineId'], $file);
    if (copy($file1,$file2)) $destinationFiles[] = $file;
  }
    // удаляем пустые элементы $destinationFiles
  foreach ($destinationFiles as $key=>$value) if (!$value) unset($destinationFiles[$key]);
    // обновляем получателя
  data_update($destination['tableId'], EVENTS_ENABLE, array('f'.$destination['fieldId']=>implode("\r\n",$destinationFiles)), "id='".$destination['lineId']."' LIMIT 1");
  return true;
}


  // функция прибавляет $pause рабочих дней к дате $start с учётом массива выходных дней $holidays ('Y-m-d')
  // $pause может быть положительным и отрицательным
  // $holidays может быть равно -1, в этом случае не он не учитывается
function AddWorkDays($start='',$pause=0,$holidays='') {
    // проверка входных данных
  $start = ($start && NULL_DATETIME<$start && NULL_DATE<=$start) ? date('Y-m-d',strtotime($start)) : date('Y-m-d');
  if (!($pause=intval($pause))) return $start;
    // промежуточная функция для форматирования элементов $holidays
  $tmp = function($date) { return date('Y-m-d',strtotime($date)); };
    // массив выходных дней
  if (-1!=$holidays) {
    if (!$holidays || !is_array($holidays)) {
      if (HOLIDAYS_TABLE && HOLIDAYS_FIELD_DATE) {
        $res = data_select_field(HOLIDAYS_TABLE, 'DATE_FORMAT(f'.HOLIDAYS_FIELD_DATE.',"%Y-%m-%d") AS date', "status=0");
        while ($row=sql_fetch_assoc($res)) $holidays[] = $row['date'];
      }
    }
    else $holidays = array_map($tmp,$holidays);   
  }
    // расчёт итоговой даты
  $j = 0;
  $index = (0>$pause) ? -1 : 1;
  $pause = abs($pause);
  $str = strtotime($start);
  for ($i=1; $i<1000; $i++) {
    $y = date('Y-m-d', $s=$str+$i*$index*86400);
    $w = date('w', $s);
    if (($w>0 && $w<6) && !in_array($y,$holidays)) $j++;
    if  ($pause<=$j) return $y;
  }
  return $start;
}


  // функция вычисляет кол-во рабочих дней между $start и $end с учётом массива выходных дней $holidays ('Y-m-d')
  // $holidays может быть равно -1, в этом случае не он не учитывается
function GetWorkDaysDiff($start='',$end='',$holidays='') {
    // проверка входных данных
  $start = ($start && NULL_DATETIME<$start && NULL_DATE<$start) ? date('Y-m-d',strtotime($start)) : date('Y-m-d');
  $end = ($end && NULL_DATETIME<$end && NULL_DATE<$end) ? date('Y-m-d',strtotime($end)) : date('Y-m-d');
  if ($start==$end) return 0;
    // промежуточная функция для форматирования элементов $holidays
  $tmp = function($date) { return date('Y-m-d',strtotime($date)); };
    // массив выходных дней
  if (-1!=$holidays) {
    if (!$holidays || !is_array($holidays)) { if (HOLIDAYS_TABLE && HOLIDAYS_FIELD_DATE) $holidays[] = GetArrayFromTable(HOLIDAYS_TABLE,HOLIDAYS_FIELD_DATE); }
    else $holidays = array_map($tmp,$holidays);   
  }
    // расчёт разницы
  $start = strtotime($start);
  $end = strtotime($end);
  $diff = ($end-$start)/86400;
  $j = 0;
  $index = (0>$diff) ? -1 : 1;
  $diff = abs($diff);
  for ($i=1;$i<=$diff;$i++) {
    $y = date('Y-m-d', $s=$start+$i*$index*86400);
    $w = date('w', $s);
    if (($w>0 && $w<6) && !in_array($y,$holidays)) $j+=$index;
  }
  return $j;
}


  // функция возвращаем массив id=>$value таблицы $tableId по полю $fieldId, с доп.условием $cond
  // к итоговому массиву применяется функция $function через array_map
function GetArrayFromTable($tableId=0,$fieldId=0,$cond='',$function='') {
    // проверка входных данных
  if (!$tableId || !$fieldId) return false;
  $tableId = intval($tableId);
  $fieldId = intval($fieldId);
  if (!$tableId || !$fieldId) return false;
    // установка условия
  $cond = ($cond) ? $cond : 1;
    // цикл
  $tmp = '';
  $res = data_select_field($tableId, 'id,f'.$fieldId.' AS value', $cond);
  while ($row=sql_fetch_assoc($res)) $tmp[$row['id']] = $row['value'];
    // маппинг
  if ($function) $tmp = array_map($function, $tmp);
  return $tmp;
}


    // возвращает select, построенный по данным массива $selectData с выделенным значением $someValue
    // структура $selectData: $value=>array('color'=>$color,'short'=>$shortValue)
function GetSelect($someValue, array $selectData) {
  $sel = '<option value=""></option>';
  foreach ($selectData as $option=>$data) $sel .= '<option style="background-color:'.$data['color'].';" value="'.$option.'" '.(($option==$someValue)?"selected":"").'>'.$data['short'].'</option>';
  return $sel;
}


    // функция генерирует текст рандомный длиной $length из строки $chars
function MakeRandom($length=4,$chars='abcdef1234567890') {
  $size = max(0,strlen($chars)-1); 
  $password = ''; 
  if (intval($length)) while($length--) $password .= $chars[rand(0,$size)];
  elseif ('syncId'==$length) $password = MakeRandom(8).'-'.MakeRandom().'-'.MakeRandom().'-'.MakeRandom().'-'.MakeRandom(12);
  return $password;
}



?>
