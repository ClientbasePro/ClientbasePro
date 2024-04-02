<?php

    // файл с PHP функциями проекта ClientbasePro

    // предопределённые константы
define('NULL_DATETIME', '0000-00-00 00:00:00');
define('NULL_DATE', '0000-00-00');
if (!defined('SMS_THREADS')) {
  define('SMS_GATES', $config['table_prefix'].'module_sms_gates');
  define('SMS_SETTINGS', $config['table_prefix'].'module_sms_settings');
  define('SMS_THREADS', $config['table_prefix'].'module_sms_threads');
  define('SMS_QUEUE', $config['table_prefix'].'module_sms_queue');
  define('SMS_ARCHIVE', $config['table_prefix'].'module_sms_archive');
}


    // преобразует кол-во секунд $sec в формат чч:мм:сс
function TimeFormat($sec=0) {
    if (!$sec || !is_numeric($sec)) return "00:00:00";
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
function GetCurrency($date='', $currency='EUR') {
        // проверка входных данных
    if (!$date) $date = date("d/m/Y");
    else $date = date("d/m/Y", strtotime($date));
        // ссылка на сайте ЦБ РФ
    $url = 'http://www.cbr.ru/scripts/XML_daily.asp?date_req='.$date;
        // запрос к сайту ЦБ РФ
    $curl = curl_init($url);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true]);
    if ($response=curl_exec($curl)) {
        $pattern = '/<Valute ID=\"R.{5}\".+?<CharCode>'.$currency.'<\/CharCode>(.*?)<\/Valute\>/is';
        preg_match($pattern, $response, $m);
        preg_match("/<Value>(.*?)<\/Value>/is", $m[1], $r);
    preg_match("/<Nominal>(.*?)<\/Nominal>/is", $m[1], $r2);
        return floatval(str_replace(",", ".", $r[1])/((1<$r2[1])?$r2[1]:1));
    }
    curl_close($curl);
    return false;
}


    // функция возвращает форматированный номер +7XXXXXXXXXXX
    // $number - номер телефона в любом формате
    // $code - код города по умолчанию, по умолчанию 495 Москва
    // $plus - отображать ли "+" перед выводимым номером
    // $format - формат вывода. Допустимы лат.буквы, цифры и звёздочки. Если длина формата меньше длины номера, то замена последних цифр будет по последнему символу формата
    // формат накладывается на номер как маска: звёздочка заменяет очередную цифру номера на звёздочку, буква/цифра не меняет
    // примеры для номера +74951234567: '+7ABC***00**'=>+7495***45**, '8495*'=>+7495*******, '8495N'=>+74951234567, '849*XXX*'=>+749*123****, '8499NNN*'=>+7495123****  
function SetNumber($number, $code='', $plus='+', $format=false) {
    // начальная проверка и корректировка входных данных
  if (!$plus && false!==$plus) $plus = '+';
  if (false!==$format) $format_ = true;
  if (!$code && defined('DEFAULT_PHONE_CODE')) $code = DEFAULT_PHONE_CODE;
  $result = '';
    // оставляем только цифры в $number
  $str = $str_ = strval($number);
  $str = preg_replace('/\D/i','',$str);
  $strlen = strlen($str);
  $strlen_ = strlen($str_);
    // сначала иностранные, начинающиеся на 810
  if (!$result && '810'==$str[0].$str[1].$str[2]) $result = $str;
    // номера РБ
  if (!$result && 0===strpos($str,'375') && 12==$strlen) $result = $plus.$str;
  if (!$result && defined('DEFAULT_COUNTRY_CODE') && 375==DEFAULT_COUNTRY_CODE && 9==$strlen) $result = $plus.DEFAULT_COUNTRY_CODE.$str;
    // Украина
  if (!$result && 0===strpos($str,'380') && 12==$strlen) $result = $plus.$str;
  if (!$result && defined('DEFAULT_COUNTRY_CODE') && 380==DEFAULT_COUNTRY_CODE && 9==$strlen) $result = $plus.DEFAULT_COUNTRY_CODE.$str;
    // Молдова
  if (!$result && 0===strpos($str,'373') && in_array($strlen,array(11,12))) $result = $plus.$str;
    // Таджикистан
  if (!$result && 0===strpos($str,'992') && 12==$strlen) $result = $plus.$str;
  if (!$result && defined('DEFAULT_COUNTRY_CODE') && 992==DEFAULT_COUNTRY_CODE && 9==$strlen) $result = $plus.DEFAULT_COUNTRY_CODE.$str;
    // Кыргызстан
  if (!$result && 0===strpos($str,'996') && 12==$strlen) $result = $plus.$str;
  if (!$result && defined('DEFAULT_COUNTRY_CODE') && 996==DEFAULT_COUNTRY_CODE && 9==$strlen) $result = $plus.DEFAULT_COUNTRY_CODE.$str;
    // далее короткие внутренние 3-хзначные
  if (!$result && 3==$strlen && 1000>$str) $result = $str;
    // далее российские 11-значные номера, начинающиеся на 7 или 8
  if (!$result && 11==$strlen && ('7'==$str[0] || '8'==$str[0])) { $str = substr($str,1); $result = $plus.'7'.$str; }
  if (!$result && 12==$strlen && '779'==$str[0].$str[1].$str[2]) { $str = substr($str,2); $result = $plus.'7'.$str; }
    // далее 10-значные, к ним дописываем 7
  if (!$result && 10==$strlen) $result = $plus.'7'.$str;
    // суммируем длину кода и длину номера
  $code = preg_replace('/\D/i', '', strval($code));
  if (!$result && $code && 10==($strlen+strlen($code))) $result = $plus.'7'.$code.$str;
    // если не нужно форматировать, сразу выводим  
  if (!$format_) return $result;
    // форматируем
    // 24.7.20 - выделяем текстовую часть после номера
  $i = -1;
  $tmp = $note = '';
  $result_ = preg_replace('/\D/i','',$result);
  while ((++$i)<=$strlen_) {
    $tmp .= preg_replace('/\D/i','',$str_[$i]);
    if ($tmp==$result_) { $note = trim(substr($str_,$i+1)); break; }
  }
    // оставляем в формате только цифры, латиницу и *
  $format = preg_replace('/[^a-zA-Z0-9\*]/', '', $format);
  if (!$format || false===strpos($format,'*')) return $result.(($note)?' '.$note:'');  
  $format = strval($format);
    // заменяем в номере цифры на звёздочки
  $i = -1;
  $tmp = '';
  $strlen_ = strlen($result_);
  while ((++$i)<$strlen_) {
    if (!isset($last)) $last = $format[$i];
    if (!$format[$i]) $format[$i] = $last;
    $tmp .= ('*'!=$format[$i]) ? $result_[$i] : '*';
    $last = $format[$i];
  }
  if ($tmp) return $plus.$tmp.(($note)?' '.$note:'');
  return false;
}


    // функция возвращает id клиента 
    // поиск по номеру телефона $number (1 номер, массив номеров, список номеров через запятую) 
    // или эл.почте $email (1 адрес, массив адресов, список адресов через запятую и точку с запятой) 
    // из поиска исключается контрагент с id $someId
    // $settings - массив настроек таблиц и полей для поиска
    // ключи ACCOUNT_TABLE, ACCOUNT_FIELD_PHONE, ACCOUNT_FIELD_EMAIL, ACCOUNT_FIELD_DOUBLE, CONTACT_TABLE, CONTACT_FIELD_PHONE, CONTACT_FIELD_EMAIL, CONTACT_FIELD_ACCOUNTID, CONTACT_FIELD_DOUBLE
function GetAccount($number='',$email='',$someId=0,$settings=[]) {
        // формируем массив телефонных номеров из $number
    if (is_array($number)) { foreach ($number as $num) if ($num=SetNumber($num)) $numbers[$num] = $num; }
    elseif ($number) foreach (explode(',',$number) as $num) if ($num=SetNumber($num)) $numbers[$num] = $num;
        // формируем массив адресов E-mail из $email
    if (is_array($email)) { foreach ($email as $mail) if (($mail=trim($mail)) && filter_var($mail,FILTER_VALIDATE_EMAIL)) $emails[$mail] = $mail; }
    elseif ($email) foreach (explode(',',$email) as $m1) foreach (explode(';',$m1) as $m2) if (($m2=trim($m2)) && filter_var($m2,FILTER_VALIDATE_EMAIL)) $emails[$m2] = $m2;
        // если нет ни одного отформатированного контакта, завершаем
    if (!$numbers && !$emails) return false;
        // проверка id таблиц и полей
        // сначала из переданных настроек
    if ($settings) {
        $accountTableId = intval($settings['ACCOUNT_TABLE']);
        $accountFieldPhone = intval($settings['ACCOUNT_FIELD_PHONE']);
        $accountFieldEmail = intval($settings['ACCOUNT_FIELD_EMAIL']);
        $accountFieldDouble = intval($settings['ACCOUNT_FIELD_DOUBLE']);
    }
        // далее проверка по константам
    if (!$accountTableId && defined('ACCOUNT_TABLE')) $accountTableId = intval(ACCOUNT_TABLE);
    if (!$accountFieldPhone && defined('ACCOUNT_FIELD_PHONE')) $accountFieldPhone = intval(ACCOUNT_FIELD_PHONE);
    if (!$accountFieldEmail && defined('ACCOUNT_FIELD_EMAIL')) $accountFieldEmail = intval(ACCOUNT_FIELD_EMAIL);
    if (!$accountFieldDouble && defined('ACCOUNT_FIELD_DOUBLE')) $accountFieldDouble = intval(ACCOUNT_FIELD_DOUBLE);
      // далее проверка по стандартным полям КБ
    if (!$accountTableId) {
        $accountTableId = 42;
        $accountFieldPhone = 441;
        $accountFieldEmail = 442;
    }
        // проверка наличия полей $accountFieldPhone,$accountFieldEmail в таблице $accountTableId
    $e = [];
    $res = sql_query("SELECT id FROM ".FIELDS_TABLE." WHERE id IN ('".$accountFieldPhone."','".$accountFieldEmail."') AND table_id='".$accountTableId."' LIMIT 2");
    while ($row=sql_fetch_assoc($res)) $e[$row['id']] = $row['id'];
    if (!$e[$accountFieldPhone] && !$e[$accountFieldEmail]) return false;   
    if (!$accountTableId || (!$accountFieldPhone && !$accountFieldEmail)) return false;
        // набор условий для SQL-запросов
    $mainCond = $idCond = '';
    if ($accountFieldPhone) foreach ($numbers as $num) $mainCond .= ((!$mainCond)?"":" OR ")."f".$accountFieldPhone."='".$num."' ".((1000<$num)?" OR f".$accountFieldPhone." LIKE '%".$num."%' ":"");
    if ($accountFieldEmail) foreach ($emails as $mail) $mainCond .= ((!$mainCond)?"":" OR ")."f".$accountFieldEmail."='".$mail."' OR f".$accountFieldEmail." LIKE '%".$mail."%' ";
    if ($someId) {
        if (is_array($someId)) { 
            $someIds = [];
            foreach ($someId as $id_) if ($id_=intval($id_)) $someIds[$id_] = $id_;
            if ($someIds) $idCond = " AND id NOT IN (".implode(',',$someIds).") ";
        }
        else $idCond = " AND id<>'".$someId."' ";
    }
    if ($accountFieldDouble && !$settings) $doubleCond = " AND f".$accountFieldDouble."='' ";
        // 1 попытка - прямое совпадение или LIKE
    $e = sql_fetch_assoc(data_select_field($accountTableId, 'id', "status=0 AND ({$mainCond}) {$idCond} {$doubleCond} ORDER BY id DESC LIMIT 1"));
    if ($e['id']) return $e['id'];
        // 2 попытка - поиск по номеру телефона, кроме совпадения по шаблонам [0-9]{3} и +7[0-9]{10} (чтобы повторно не искать среди одиночных номеров по формату)
    if ($number && $accountFieldPhone) {
        $patternCond = " AND f".$accountFieldPhone." NOT RLIKE '^[0-9]{3}$' AND f".$accountFieldPhone." NOT RLIKE '^[+]7[0-9]{10}$' ";
        $res = data_select_field($accountTableId, 'id, f'.$accountFieldPhone.' as phone', "status=0 AND f".$accountFieldPhone."<>'' {$doubleCond} {$idCond} {$patternCond} ORDER BY id DESC");
        while ($row=sql_fetch_assoc($res)) {
            $phones = explode(',', $row['phone']);
            foreach ($phones as $p) if (in_array(SetNumber($p),$numbers)) return $row['id'];
        }
    }
        // 3 попытка - поиск через контактное лицо 
    if ($settings) {
        $contactTableId = intval($settings['CONTACT_TABLE']);
        $contactFieldAccountId = intval($settings['CONTACT_FIELD_ACCOUNTID']);
        $contactFieldDouble = intval($settings['CONTACT_FIELD_DOUBLE']);
    }
    if (!$contactTableId && defined('CONTACT_TABLE')) $contactTableId = intval(CONTACT_TABLE);
    if (!$contactFieldAccountId && defined('CONTACT_FIELD_ACCOUNTID')) $contactFieldAccountId = intval(CONTACT_FIELD_ACCOUNTID);
    if (!$contactFieldDouble && defined('CONTACT_FIELD_DOUBLE')) $contactFieldDouble = intval(CONTACT_FIELD_DOUBLE);
    if (!$contactTableId && 42==$accountTableId) {
        $contactTableId = 51;
        $contactFieldAccountId = 545;
    }   
    if ($contactTableId && $contactFieldAccountId) {
        $e = sql_fetch_assoc(sql_query("SELECT id FROM ".FIELDS_TABLE." WHERE id='".$contactFieldAccountId."' AND table_id='".$contactTableId."' LIMIT 1"));
        if (!$e['id']) return false;    
        $cids = [];
        if ($idCond) {
          $idCond_ = str_replace(["NOT IN","<>"], ["IN","="], $idCond);
          $cids = GetArrayFromTable($contactTableId, $contactFieldAccountId, "f".$contactFieldAccountId." IN (SELECT id FROM ".DATA_TABLE.$accountTableId." WHERE 1 {$idCond_})");
        }
        if ($cids) $cids = array_keys($cids);
        if ($contact=intval(GetContact($number,$email,$cids,$settings))) {
            if ($contactFieldDouble) {
                $e = sql_fetch_assoc(sql_query("SELECT id FROM ".FIELDS_TABLE." WHERE id='".$contactFieldDouble."' AND table_id='".$contactTableId."' LIMIT 1"));
                if ($e['id']) $cc = " AND f".$contactFieldDouble."='' ";
            }
            $e = sql_fetch_assoc(data_select_field($contactTableId, 'f'.$contactFieldAccountId.' AS accountId', "id='".$contact."' {$cc} LIMIT 1"));
            if ($e['accountId']) return $e['accountId'];        
        }
    }
    return false;
}


    // функция возвращает id контактного лица 
    // поиск по номеру телефона $number (1 номер, массив номеров, список номеров через запятую) 
    // или эл.почте $email (1 адрес, массив адресов, список адресов через запятую и точку с запятой) 
    // из поиска исключается контрагент с id $someId
    // $settings - массив настроек таблиц и полей для поиска, ключи CONTACT_TABLE, CONTACT_FIELD_PHONE, CONTACT_FIELD_EMAIL, CONTACT_FIELD_DOUBLE
function GetContact($number='',$email='',$someId=0,$settings=[]) {
        // формируем массив телефонных номеров из $number
    if (is_array($number)) { foreach ($number as $num) if ($num=SetNumber($num)) $numbers[$num] = $num; }
    elseif ($number) foreach (explode(',',$number) as $num) if ($num=SetNumber($num)) $numbers[$num] = $num;
        // формируем массив адресов E-mail из $email
    if (is_array($email)) { foreach ($email as $mail) if ($mail && filter_var($mail,FILTER_VALIDATE_EMAIL)) $emails[$mail] = $mail; }
    elseif ($email) foreach (explode(',',$email) as $m1) foreach (explode(';',$m1) as $m2) if ($m2 && filter_var($m2,FILTER_VALIDATE_EMAIL)) $emails[$m2] = $m2;
        // если нет ни одного отформатированного контакта, завершаем
    if (!$numbers && !$emails) return false;
        // проверка id таблиц и полей   
    if ($settings) {
        $tableId = intval($settings['CONTACT_TABLE']);
        $fieldPhone = intval($settings['CONTACT_FIELD_PHONE']);
        $fieldEmail = intval($settings['CONTACT_FIELD_EMAIL']);
        $fieldDouble = intval($settings['CONTACT_FIELD_DOUBLE']);
    }
            // доп.проверка для полей контактов
    if (!$tableId && defined('CONTACT_TABLE')) $tableId = intval(CONTACT_TABLE);
    if (!$fieldPhone && defined('CONTACT_FIELD_PHONE')) $fieldPhone = intval(CONTACT_FIELD_PHONE);
    if (!$fieldEmail && defined('CONTACT_FIELD_EMAIL')) $fieldEmail = intval(CONTACT_FIELD_EMAIL);
    if (!$fieldDouble && defined('CONTACT_FIELD_DOUBLE')) $fieldDouble = intval(CONTACT_FIELD_DOUBLE);  
        // проверка по стандартным полям КБ
    if (!$tableId) {
        $tableId = 51;
        $fieldPhone = 548;
        $fieldEmail = 549;
    }
    $e = [];
    $res = sql_query("SELECT id FROM ".FIELDS_TABLE." WHERE id IN ('".$fieldPhone."','".$fieldEmail."') AND table_id='".$tableId."' LIMIT 2");
    while ($row=sql_fetch_assoc($res)) $e[$row['id']] = $row['id'];
    if (!$e[$fieldPhone] || !$e[$fieldEmail]) return false;
    if (!$tableId || (!$fieldPhone && !$fieldEmail)) return false;
        // набор условий для SQL-запросов
    $mainCond = $idCond = '';
    if ($fieldPhone) foreach ($numbers as $num) $mainCond .= ((!$mainCond)?"":" OR ")."f".$fieldPhone."='".$num."' ".((1000<$num)?" OR f".$fieldPhone." LIKE '%".$num."%' ":"");
    if ($fieldEmail) foreach ($emails as $mail) $mainCond .= ((!$mainCond)?"":" OR ")."f".$fieldEmail."='".$mail."' OR f".$fieldEmail." LIKE '%".$mail."%' ";
    if ($fieldDouble && !$settings) {
        $e = sql_fetch_assoc(sql_query("SELECT id FROM ".FIELDS_TABLE." WHERE id='".$fieldDouble."' AND table_id='".$tableId."' LIMIT 2"));
        if ($e['id']) $doubleCond = " AND f".$fieldDouble."='' ";
    }
    if ($someId) {
        if (is_array($someId)) { 
            $someIds = [];
            foreach ($someId as $id_) if ($id_=intval($id_)) $someIds[$id_] = $id_;
            if ($someIds) $idCond = " AND id NOT IN (".implode(',',$someIds).") ";
        }
        else $idCond = " AND id<>'".$someId."' ";
    }
        // 1 попытка - прямое совпадение или LIKE
    $e = sql_fetch_assoc(data_select_field($tableId, 'id', "status=0 AND ({$mainCond}) {$idCond} {$doubleCond} ORDER BY add_time DESC LIMIT 1"));
    if ($e['id']) return $e['id'];
        // 2 попытка - поиск по номеру телефона
    if ($number && $fieldPhone) {
        $patternCond = " AND f".$fieldPhone." NOT RLIKE '^[0-9]{3}$' AND f".$fieldPhone." NOT RLIKE '^[+]7[0-9]{10}$' ";
        $res = data_select_field($tableId, 'id, f'.$fieldPhone.' as phone', "status=0 AND f".$fieldPhone."<>''  {$idCond} {$patternCond} ORDER BY add_time DESC");
        while ($row=sql_fetch_assoc($res)) {
            $phones = explode(',', $row['phone']);
            foreach ($phones as $p) if (in_array(SetNumber($p),$numbers)) return $row['id'];
        }
    }
    return false;
}


    // функция обновляет контактные данные клиента с id = $accountId
    // добавляет номер(-а) телефона $number (1 номер, массив номеров, список номеров через запятую) 
    // или эл.почте $email (1 адрес, массив адресов, список адресов через запятую и точку с запятой) 
    // $settings - массив настроек таблиц и полей для поиска, ключи ACCOUNT_TABLE, ACCOUNT_FIELD_PHONE, ACCOUNT_FIELD_EMAIL
function UpdateAccount($accountId=0,$number='',$email='',$settings=[]) {
        // проверка входных данных
    $accountId = intval($accountId);
    if (!$accountId) return false;
        // формируем массив телефонных номеров из $number
    if (is_array($number)) { foreach ($number as $num) if ($num=SetNumber($num)) $numbers[$num] = $num; }
    elseif ($number) foreach (explode(',',$number) as $num) if ($num=SetNumber($num)) $numbers[$num] = $num;
        // формируем массив адресов E-mail из $email
    if (is_array($email)) { foreach ($email as $mail) if ($mail && filter_var($mail,FILTER_VALIDATE_EMAIL)) $emails[$mail] = $mail; }
    elseif ($email) foreach (explode(',',$email) as $m1) foreach (explode(';',$m1) as $m2) if ($m2 && filter_var($m2,FILTER_VALIDATE_EMAIL)) $emails[$m2] = $m2;
        // если нет ни одного отформатированного контакта, завершаем
    if (!$emails && !$numbers) return false;
        // проверка id таблиц и полей    
    if ($settings) {
        $tableId = intval($settings['ACCOUNT_TABLE']);
        $fieldPhone = intval($settings['ACCOUNT_FIELD_PHONE']);
        $fieldEmail = intval($settings['ACCOUNT_FIELD_EMAIL']);
    }
        // далее проверка по константам
    if (!$tableId && defined('ACCOUNT_TABLE')) $tableId = intval(ACCOUNT_TABLE);
    if (!$fieldPhone && defined('ACCOUNT_FIELD_PHONE')) $fieldPhone = intval(ACCOUNT_FIELD_PHONE);
    if (!$fieldEmail && defined('ACCOUNT_FIELD_EMAIL')) $fieldEmail = intval(ACCOUNT_FIELD_EMAIL);
      // далее проверка по стандартным полям КБ
    if (!$tableId) {
        $tableId = 42;
        $fieldPhone = 441;
        $fieldEmail = 442;
    }
        // проверка наличия полей $fieldPhone,$fieldEmail в таблице $tableId
    $e = [];
    $res = sql_query("SELECT id FROM ".FIELDS_TABLE." WHERE id IN ('".$fieldPhone."','".$fieldEmail."') AND table_id='".$tableId."' LIMIT 2");
    while ($row=sql_fetch_assoc($res)) $e[$row['id']] = $row['id']; 
    if (!$e[$fieldPhone] || !$e[$fieldEmail]) return false;
    $row = sql_fetch_assoc(data_select_field($tableId, 'f'.$fieldPhone.' AS phone, f'.$fieldEmail.' AS email', "id='".$accountId."' LIMIT 1"));
        // добавление E-mail
    foreach ($emails as $email) if (false===strpos($row['email'],$email)) $upd['f'.$fieldEmail] = (($row['email'])?$row['email'].'; ':'').$email;
        // добавление телефона
    foreach (explode(',',$row['phone']) as $phone) if ($phone=SetNumber($phone)) unset($numbers[$phone]);   
    if ($numbers) $upd['f'.$fieldPhone] = (($row['phone'])?$row['phone'].', ':'').implode(', ',$numbers);    
        // обновляем контактную информацию клиента
    if ($upd) { data_update($tableId, EVENTS_ENABLE, $upd, "id='".$accountId."' LIMIT 1"); return $upd; }
    return false;
}


    // функция обновляет контактные данные контактного лица с id = $contactId
    // добавляет номер(-а) телефона $number (1 номер, массив номеров, список номеров через запятую) 
    // или эл.почте $email (1 адрес, массив адресов, список адресов через запятую и точку с запятой) 
    // $settings - массив настроек таблиц и полей для поиска, ключи CONTACT_TABLE, CONTACT_FIELD_PHONE, CONTACT_FIELD_EMAIL
function UpdateContact($contactId=0,$number='',$email='',$settings=[]) {
        // проверка входных данных
    $contactId = intval($contactId);
    if (!$contactId) return false;
        // формируем массив телефонных номеров из $number
    if (is_array($number)) { foreach ($number as $num) if ($num=SetNumber($num)) $numbers[$num] = $num; }
    elseif ($number) foreach (explode(',',$number) as $num) if ($num=SetNumber($num)) $numbers[$num] = $num;
        // формируем массив адресов E-mail из $email
    if (is_array($email)) { foreach ($email as $mail) if ($mail && filter_var($mail,FILTER_VALIDATE_EMAIL)) $emails[$mail] = $mail; }
    elseif ($email) foreach (explode(',',$email) as $m1) foreach (explode(';',$m1) as $m2) if ($m2 && filter_var($m2,FILTER_VALIDATE_EMAIL)) $emails[$m2] = $m2;
        // если нет ни одного отформатированного контакта, завершаем
    if (!$emails && !$numbers) return false;
        // проверка id таблиц и полей
    if ($settings) {
        $tableId = intval($settings['CONTACT_TABLE']);
        $fieldPhone = intval($settings['CONTACT_FIELD_PHONE']);
        $fieldEmail = intval($settings['CONTACT_FIELD_EMAIL']);
    }
    if (!$tableId && defined('CONTACT_TABLE')) $tableId = intval(CONTACT_TABLE);
    if (!$fieldPhone && defined('CONTACT_FIELD_PHONE')) $fieldPhone = intval(CONTACT_FIELD_PHONE);
    if (!$fieldEmail && defined('CONTACT_FIELD_EMAIL')) $fieldEmail = intval(CONTACT_FIELD_EMAIL);
      // далее проверка по стандартным полям КБ
    if (!$tableId) {
        $tableId = 51;
        $fieldPhone = 548;
        $fieldEmail = 549;
    }
        // проверка наличия полей $fieldPhone,$fieldEmail в таблице $tableId
    $e = [];
    $res = sql_query("SELECT id FROM ".FIELDS_TABLE." WHERE id IN ('".$fieldPhone."','".$fieldEmail."') AND table_id='".$tableId."' LIMIT 2");
    while ($row=sql_fetch_assoc($res)) $e[$row['id']] = $row['id']; 
    if (!$e[$fieldPhone] || !$e[$fieldEmail]) return false;
    $row = sql_fetch_assoc(data_select_field($tableId, 'f'.$fieldPhone.' AS phone, f'.$fieldEmail.' AS email', "status=0 AND id='".$contactId."' LIMIT 1"));
        // добавление E-mail
    foreach ($emails as $email) if (false===strpos($row['email'],$email)) $upd['f'.$fieldEmail] = (($row['email'])?$row['email'].'; ':'').$email;
        // добавление телефона
    foreach (explode(',',$row['phone']) as $phone) if ($phone=SetNumber($phone)) unset($numbers[$phone]);   
    if ($numbers) $upd['f'.$fieldPhone] = (($row['phone'])?$row['phone'].', ':'').implode(', ',$numbers);
        // обновляем контактную информацию контакта
    if ($upd) { data_update($tableId, EVENTS_ENABLE, $upd, "id='".$contactId."' LIMIT 1"); return $upd; }
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


    // функция добавляет $arrayToAdd и убирает $arrayToDelete значения в поле $fieldId в таблице $tableId в записи по условиям $cond (если $cond число, то считается id=$cond)
    // $arrayToAdd может быть строкой или массивом
    // $arrayToDelete может быть строкой или массивом или строкой 'delete', в этом случае будет удалён массив $arrayToAdd (пережиток предыдущей версии функции)
function SetCheckList($tableId, $fieldId, $cond, $arrayToAdd=[], $arrayToDelete=[]) {
    // проверка входных данных
  if (!$fieldId || !$cond || (!$arrayToAdd && !$arrayToDelete)) return false;
    // приводим $fieldId к числовому виду
  $fieldId = preg_replace('/\D/i', '', $fieldId);
  if (!$fieldId) return false;
    // ищем таблицу 
  if (!$tableId) {
    $e = sql_fetch_assoc(sql_query("SELECT table_id AS t FROM ".FIELDS_TABLE." WHERE id='".$fieldId."' LIMIT 1"));
    if ($e['t']) $tableId = $e['t'];
    else return false;
  }  
    // если $cond 1 число, то приводим к условию id=$cond
  if (is_numeric($cond)) $cond = "id='".$cond."' LIMIT 1";
    // если $arrayToAdd строка, переводим в массив
  if (!is_array($arrayToAdd)) $arrayToAdd = [$arrayToAdd];
  $arrayToAdd = array_filter($arrayToAdd);
    // если $arrayToDelete строка, переводим в массив
  if (!is_array($arrayToDelete) && 'delete'!=$arrayToDelete) $arrayToDelete = [$arrayToDelete];
  elseif ('delete'==$arrayToDelete) { $arrayToDelete = $arrayToAdd; $arrayToAdd = []; }
  $arrayToDelete = array_filter($arrayToDelete);
    // получаем список всех галочек из настроек поля
  $e = sql_fetch_assoc(sql_select_field(FIELDS_TABLE, "type_value", "id='".$fieldId."' LIMIT 1", $tableId));
  $all = explode("\r\n", $e['type_value']);
    // проходим по всем записям по условию $cond
  $res = sql_query("SELECT id, f".$fieldId." AS field FROM ".DATA_TABLE.$tableId." WHERE ".$cond);
  while ($row=sql_fetch_assoc($res)) {
      // список отмеченных галочек в текущей строке
    $checked = explode("\r\n", $row['field']);
      // проходим по списку всех галочек и добавляем/убираем значение в итоговый массив $data
    $data = [];
    foreach ($all as $current) if ((in_array($current,$checked) && !in_array($current,$arrayToDelete)) || in_array($current,$arrayToAdd)) $data[] = $current;
      // обновляем текущую строку
    data_update($tableId, EVENTS_ENABLE, ['f'.$fieldId=>implode("\r\n",$data)], "id='".$row['id']."' LIMIT 1");  
  }
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
  $row = sql_fetch_assoc(data_select_field($tableId, 'f'.$fieldId.' AS files', "id=$lineId LIMIT 1"));
  if (!$row['files']) return true;
  $files = explode("\r\n", $row['files']);
  foreach ($files as $file) @unlink(get_file_path($fieldId, $lineId, $file));
    // очищаем поле с файлами
  data_update($tableId, EVENTS_ENABLE, ['f'.$fieldId=>''], "id=$lineId LIMIT 1");
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
    if (!in_array($file,$destinationFiles)) {
        // создаём папку
      create_data_file_dirs($destination['fieldId'], $destination['lineId'], $file);
        // копируем файл и добавляем его имя в $destination['files']
      $file1 = get_file_path($source['fieldId'], $source['lineId'], $file);
      $file2 = get_file_path($destination['fieldId'], $destination['lineId'], $file);
      if (copy($file1,$file2)) $destinationFiles[] = $file;
    }
  }
    // удаляем пустые элементы $destinationFiles
  $destinationFiles = array_filter($destinationFiles);
  $destinationFiles = array_unique($destinationFiles);
    // обновляем получателя
  data_update($destination['tableId'], EVENTS_ENABLE, array('f'.$destination['fieldId']=>implode("\r\n",$destinationFiles)), "id='".$destination['lineId']."' LIMIT 1");
  return true;
}


  // функция прибавляет $pause рабочих дней к дате $start с учётом массива выходных дней $holidays ('Y-m-d')
  // $pause может быть положительным и отрицательным
  // $holidays может быть равно -1, в этом случае не он не учитывается
function AddWorkDays($start='',$pause=0,$holidays=[]) {
    // проверка входных данных
  $start = ($start && NULL_DATETIME<$start && NULL_DATE<=$start) ? date('Y-m-d',strtotime($start)) : date('Y-m-d');
  if (!($pause=intval($pause))) return $start;
    // промежуточная функция для форматирования элементов $holidays
  $tmp = function($date) { return date('Y-m-d',strtotime($date)); };
    // массив выходных дней
  if (-1!=$holidays) {
    if (!$holidays || !is_array($holidays)) { if (intval(HOLIDAYS_TABLE) && intval(HOLIDAYS_FIELD_DATE)) $holidays = GetArrayFromTable(HOLIDAYS_TABLE,HOLIDAYS_FIELD_DATE, 0, $tmp); }
    else $holidays = array_map($tmp,$holidays);   
  }
    // расчёт итоговой даты
  $j = 0;
  $index = (0>$pause) ? -1 : 1;
  $pause = abs($pause);
  $str = strtotime($start);
  for ($i=1; $i<100000; $i++) {
    $y = date('Y-m-d', $s=$str+$i*$index*86400);
    $w = date('w', $s);
    if (($w>0 && $w<6) && !in_array($y,$holidays)) $j++;
    if  ($pause<=$j) return $y;
  }
  return $start;
}


  // функция вычисляет кол-во рабочих дней между $start и $end с учётом массива выходных дней $holidays ('Y-m-d')
  // $holidays может быть равно -1, в этом случае не он не учитывается
function GetWorkDaysDiff($start='',$end='',$holidays=[]) {
    // проверка входных данных
  $start = ($start && NULL_DATETIME<$start && NULL_DATE<$start) ? date('Y-m-d',strtotime($start)) : date('Y-m-d');
  $end = ($end && NULL_DATETIME<$end && NULL_DATE<$end) ? date('Y-m-d',strtotime($end)) : date('Y-m-d');
  if ($start==$end) return 0;
    // промежуточная функция для форматирования элементов $holidays
  $tmp = function($date) { return date('Y-m-d',strtotime($date)); };
    // массив выходных дней
  if (-1!=$holidays) {
    if (!$holidays || !is_array($holidays)) { if (intval(HOLIDAYS_TABLE) && intval(HOLIDAYS_FIELD_DATE)) $holidays = GetArrayFromTable(HOLIDAYS_TABLE,HOLIDAYS_FIELD_DATE, 0, $tmp); }
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
function GetArrayFromTable($tableId=0,$fields='',$cond='',$function='') {
  $tmp = [];
    // проверка количества входных параметров
  $argumentsCnt = func_num_args();
    // установка условия
  $cond = ($cond) ? $cond : 1;
    // если нет ни $tableId, ни $fields - считаем это запросом к таблице пользователей с условием $cond
  if (!$argumentsCnt || (!$tableId && !$fields && 2<$argumentsCnt)) {
      // если сортировка не указана, сортируем по ФИО
    if (false===strpos($cond,'ORDER BY')) {
      if (false!==strpos($cond,' LIMIT ')) $cond = str_replace(' LIMIT ', ' ORDER BY fio LIMIT ', $cond);
      else $cond .= " ORDER BY fio";
    }
      // заполняем результирующий массив $tmp
    $res = sql_query("SELECT id, fio FROM ".USERS_TABLE." WHERE ".$cond);
    while ($row=sql_fetch_assoc($res)) $tmp[$row['id']] = $row['fio'];
      // mapping
    if ($function) $tmp = array_map($function, $tmp);
    return $tmp;
  }
    // если получили 1 входной параметр, считаем, что это id поля или массив полей
  if (1==$argumentsCnt) {
    $fields = $tableId;
    $fieldId = 0;
      // если получили массив - ищем таблицу по 1-му полю из массива
    if (is_array($fields)) {
      $field_ = array_keys($fields)[0];
      if (is_numeric($field_)) $fieldId = $field_;
      elseif (is_string($field_)) {
        $field_ = substr($field_,1);
        if (is_numeric($field_)) $fieldId = $field_;
        else return false;
      }
      else return false;
    }
      // получили число - его и берём
    elseif (is_numeric($fields)) $fieldId = $fields;
      // получили строку - убираем первый символ и проверяем, число ли это
    elseif (is_string($fields)) {
      $fields_ = substr($fields,1);
      if (is_numeric($fields_)) $fieldId = $fields_;
      else return false;
    }
    else return false;
      // ищем таблицу
    $e = sql_fetch_assoc(sql_query("SELECT table_id AS t FROM ".FIELDS_TABLE." WHERE id=$fieldId LIMIT 1"));
    if ($e['t']) $tableId = $e['t'];
    else return false;
  }
  $tableId = intval($tableId);
    // форматируем $fields, когда это не число и не массив (например, строка 'f12345')
  if (!is_numeric($fields) && !is_array($fields)) {
    $fields_ = substr($fields,1);
    if (is_numeric($fields_)) $fields = $fields_;
  }
    // ищем таблицу
  if (!$tableId && is_numeric($fields)) {
    $e = sql_fetch_assoc(sql_query("SELECT table_id AS t FROM ".FIELDS_TABLE." WHERE id='".$fields."' LIMIT 1"));
    if ($e['t']) $tableId = $e['t'];
    else return false;
  } 
    // если $fields - число, формируем массив id=>поле
  if (is_numeric($fields)) {
    $res = sql_query("SELECT id, f".$fields." AS value FROM ".DATA_TABLE.$tableId." WHERE ".$cond);
    while ($row=sql_fetch_assoc($res)) $tmp[$row['id']] = $row['value'];
      // mapping
    if ($function) $tmp = array_map($function, $tmp);
    return $tmp;
  }
    // если $fields - массив, формируем результирующий массив id=>array('field'=>'value')
  elseif (is_array($fields)) {
    foreach ($fields as $field=>$name) {
      if (is_numeric($field)) $field = 'f'.$field;
      $f[] = $field.' AS '.$name;
    }
    $fields = implode(', ', $f);
    $res = sql_query("SELECT id, ".$fields." FROM ".DATA_TABLE.$tableId." WHERE ".$cond);
    while ($row=sql_fetch_assoc($res)) {
      $id = $row['id'];
      unset($row['id']);
      if ($function) $row = array_map($function, $row); 
      $tmp[$id] = $row;
    }
    return $tmp;
  }
  return false;
}

    // функция генерирует текст рандомный длиной $length из строки $chars
function MakeRandom($length=4,$chars='abcdef1234567890') {
  $size = max(0,strlen($chars)-1);
  $password = ''; 
  if (intval($length)) while($length--) $password .= $chars[rand(0,$size)];
  elseif (in_array($length,['syncId','UID','UUID','uuid','uid','guid','GUID'])) $password = MakeRandom(8).'-'.MakeRandom().'-'.MakeRandom().'-'.MakeRandom().'-'.MakeRandom(12);
  return $password;
}

    // функция возвращает bool, если дата $date не установлена
function IsNullDate($date='') {  
  if (!$date || NULL_DATETIME==$date || !intval(preg_replace('/\D/i','',$date)) || 0>=strtotime($date)) return true;
  $date = date('Y-m-d H:i:s', strtotime($date));
  return (!$date || NULL_DATETIME==$date) ? true : false;
}


  // 1 - 'integer',
  // 2 - 'date',
  // 3 - 'string',
  // 4 - 'list',
  // 5 - 'link'
  // 6 - 'file'
  // 7 - 'user'
  // 8 - 'group'
  // 9 - 'image'
  // 10 - 'id'
  // 11 - 'user_id'
  // 12 - 'add_time'
  // 13 - 'status'
    
  // служебная функция, выводит массив полей в таблице $tableId, в т.ч. полей из полей связи - для создания списка полей для подстановки в шаблонах
  // формат вывода $format ('names' | 'ids' | 'both'), 'names' по умолчанию
    // 'names' - пример {$Заявка}
    // 'ids' - пример {$f123456}
    // 'both' - будут выведены оба варианта
  // для пакетной обработки может передаваться массив $fields[table_id][name_field] = ['id'=>'f***', 'type'=>type_field, 'values'=>type_value], образец создания см.внутри функции
function GetTableFields($tableId=0, $format='names', $fields=[]) {
    // проверка входных данных
  if (!$tableId) return false;
  if ($format && !in_array($format,['names','ids','both'])) $format = 'names';
  if (!$fields || !is_array($fields)) {
    $fields = [];
    $res = sql_query("SELECT id, table_id, name_field, type_field, type_value FROM ".FIELDS_TABLE." ORDER BY field_num");
    while ($row=sql_fetch_assoc($res)) {
      if ('ids'!==$format) $fields[$row['table_id']][$row['name_field']] = ['id'=>'f'.$row['id'], 'type'=>$row['type_field'], 'values'=>$row['type_value']];
      if ('names'!=$format && !$fields[$row['table_id']]['f'.$row['id']]) $fields[$row['table_id']]['f'.$row['id']] = ['name'=>$row['name_field'], 'type'=>$row['type_field'], 'values'=>$row['type_value']];
    }
  }
    // составляем массив полей 
  $data = [];
  $t = get_table($tableId);
  if (!$t['id']) return false;
  $f = get_table_fields($t);
  foreach ($fields[$tableId] as $key=>$field) { 
    $id = ($field['id']) ? intval(substr($field['id'],1)) : intval(substr($key,1));
    if ($f[$id]['read']) {
      $e = '{$'.$key.'}';
      if (5==$field['type']) {
        $fld = explode("|",$field['values']);
        $table = $fld[0];
        $show = $fld[1];
        $t_ = get_table($table);
        if ($t_['id']) {
          $f_ = get_table_fields($t_);
          if ($table && $show) $data[$e] = $e;
          foreach ($fields[$table] as $key_=>$field_) {
            $id_ = ($field_['id']) ? intval(substr($field_['id'],1)) : intval(substr($key_,1));
            if ($f_[$id_]['read']) {
              $e_ = '{$'.$key.'.'.$key_.'}';
              $data[$e_] = $e_;
            }
          }
        }
      }
      else $data[$e] = $e;
    }
  }
  return ($data) ? array_values($data) : false;
}



  // функция возвращает форматированное значение $value из поля $field (массив ['id','name','type','values'])
  // для пакетной обработки могут передаваться $users (массив пользователей) и $groups (массив групп доступа) (в обоих массивах ['id'=>name])
function GetFormattedFieldData($value='', $field=[], $users=[], $groups=[]) {
  if ((!$value && 13!=$field['type']) || !$field || !is_array($field)) return $value;
  if (!$users || !is_array($users)) $users = GetArrayFromTable();
  if (!$groups || !is_array($groups)) {
    $groups = [];
    $res = sql_query("SELECT id, name FROM ".GROUPS_TABLE);
    while ($row=sql_fetch_assoc($res)) $groups[$row['id']] = $row['name'];
  }  
  if (1==$field['type']) {
    $format = '0/10';
    if ($tmp=explode("/",explode("|",$field['values'])[0])) $format = intval($tmp[1]).'/'.min(10,intval($tmp[0]));
    $value = form_local_number($value,$format);
  }
  elseif (2==$field['type'] || 12==$field['type']) {
    $format = (1==$field['values']) ? 'd.m.Y H:i' : 'd.m.Y';
    $value = (!IsNullDate($value)) ? date($format,strtotime($value)) : '';
  }
  elseif (7==$field['type'] || 11==$field['type']) {
    $value_ = [];
    foreach (explode('-',$value) as $id) if ($id) $value_[] = $users[$id];
    $value = implode(', ', $value_);
  }
  elseif (8==$field['type']) {
    $value_ = [];
    foreach (explode('-',$value) as $id) if ($id) $value_[] = $groups[$id];
    $value = implode(', ', $value_);
  }
  elseif (4==$field['type'] || 6==$field['type'] || 9==$field['type']) $value = str_replace("\r\n", ", ", $value);
  elseif (13==$field['type']) {
    $e = ['активные','архивные','удаленные','временные'];
    $value = $e[$value];
  }
  return $value;
}


  // функция формирует массив ['field'=>'value'] полей строки $lineId в таблице $tableId
  // для пакетной обработки может передаваться массив $fields[table_id][name_field] = ['id'=>'f***', 'type'=>type_field, 'values'=>type_value], образец создания см.внутри функции, $users, $groups
function GetTableDataToReplace($tableId=0, $lineId=0, $fields=[], $users=[], $groups=[]) {
  if (!$tableId || !$lineId) return false;
  $tableId = intval($tableId);
  $lineId = intval($lineId);
  if (!$tableId || !$lineId) return false;
  if (!$fields || !is_array($fields)) {
    $fields = [];
    $res = sql_query("SELECT id, table_id, name_field, type_field, type_value FROM ".FIELDS_TABLE."");
    while ($row=sql_fetch_assoc($res)) {
      $fields[$row['table_id']][$row['name_field']] = ['id'=>'f'.$row['id'], 'type'=>$row['type_field'], 'values'=>$row['type_value']];
      if (!$fields[$row['table_id']]['f'.$row['id']]) $fields[$row['table_id']]['f'.$row['id']] = ['name'=>$row['name_field'], 'type'=>$row['type_field'], 'values'=>$row['type_value']];
    }
  }
  if (!$users || !is_array($users)) $users = GetArrayFromTable();
  if (!$groups || !is_array($groups)) {
    $groups = [];
    $res = sql_query("SELECT id, name FROM ".GROUPS_TABLE);
    while ($row=sql_fetch_assoc($res)) $groups[$row['id']] = $row['name'];
  }
  $data = [];
  $current = sql_fetch_assoc(data_select($tableId, "id=$lineId LIMIT 1"));
  $systemFields = [10=>'id', 'user_id', 'add_time', 'status'];
  foreach ($fields[$tableId] as $key=>$field) if ($field['id']) {
    if ($id_=$systemFields[$field['type']]) $field['id'] = $id_;
    $value = $current[$field['id']];
    if (is_array($value) || 5==$field['type']) {
      $f = explode("|",$field['values']);
      $table = $f[0];
      $show = $f[1];
      $id = (is_array($value)) ? intval($value['raw']) : intval($value);
      if ($table) {
        if ($id) {
          $line_ = sql_fetch_assoc(data_select($table, "id='".$id."' LIMIT 1"));
          foreach ($line_ as $name2=>$value2) if ($fields[$table][$name2]['name']) {
            if (5==$fields[$table][$name2]['type']) {
              $f2 = explode("|",$fields[$table][$name2]['values']);
              $table2 = $f2[0];
              $show2 = $f2[1];
              if ($table2 && $show2) {
                if ($id_=$systemFields[$fields[$table2]['f'.$show2]['type']]) $f2_ = $id_;
                else $f2_ = 'f'.$show2;
                $line2_ = sql_fetch_assoc(data_select_field($table2, $f2_, "id='".$value2."' LIMIT 1"));
                $data[$key.'.'.$fields[$table][$name2]['name']] = GetFormattedFieldData($line2_[$f2_],$fields[$table2]['f'.$show2],$users,$groups);
              }
            }
            else {
              $data[$key.'.'.$fields[$table][$name2]['name']] = GetFormattedFieldData($value2,$fields[$table][$name2],$users,$groups);
              if ('f'.$show==$name2) $data[$key] = GetFormattedFieldData($value2,$fields[$table][$name2],$users,$groups);
            }
          }
        }
        else foreach ($fields[$table] as $name2=>$field2) if ($field2['id']) $data[$key.'.'.$name2] = '';
      }
    }
    else {
      $value = GetFormattedFieldData($value,$field,$users,$groups);
      $data[$key] = $value;
    }
  }  
  return ($data) ? $data : false;
}


  // функция выполняет в тексте $textToReplace замену по массиву $data (результат функции GetTableDataToReplace) и (или) подстановку defined констант
function ReplaceInTemplate($textToReplace='', $data=[]) {
  if (!$textToReplace) return false;
  if (false===strpos($textToReplace,'{')) return $textToReplace;
    // сначала определяем defined переменные и заменяем на их значения
  $pattern = '/\{(.+?)\}/u';
  preg_match_all($pattern, $textToReplace, $tmp);
  if ($tmp) foreach ($tmp[1] as $field) if (defined($field)) $textToReplace = str_replace('{'.$field.'}', constant($field), $textToReplace);
  $pattern = '/\{\$(.+?)\}/u';
  preg_match_all($pattern, $textToReplace, $tmp);
  if ($tmp) { 
    foreach ($tmp[1] as $field) {
      if (defined($field)) $textToReplace = str_replace('{$'.$field.'}', constant($field), $textToReplace);
      if (isset($data[$field])) $textToReplace = str_replace('{$'.$field.'}', $data[$field], $textToReplace);
    }
    return $textToReplace;
  }
  return false;
}


  // функция возвращает данные из сообщения (массив ['header', 'body'=>['html','plain'], 'charset', 'attachments'])
  // $mbox - IMAP stream
  // $mid - message id
function GetEmailMessage($mbox, $mid) {
  if (!$mbox || !$mid) return false;
  $result = [];
    // получаем и декодируем/преобразуем заголовки сообщения
  if ($h=imap_header($mbox,$mid)) {
    $result['header'] = array_filter(json_decode(json_encode($h), true));
    foreach ($result['header'] as $key=>$value) {
      if (($key_=mb_strtolower($key)) && $key_!=$key && ($result['header'][$key_]==$value || Header2utf8($result['header'][$key_])==Header2utf8($value))) unset($result['header'][$key]);
      else $result['header'][$key] = Header2utf8($value);
    }
  }
    // тело сообщения и аттачи
  $s = imap_fetchstructure($mbox, $mid);
    // simple
  if (!($s->parts)) $result['body'] = GetPart($mbox, $mid, $s, 0);
    // multipart: cycle through each part
  else {  
    $html = $plain = $charset = '';
    $attachments = [];
    foreach ($s->parts as $index=>$p) {
      $part = GetPart($mbox, $mid, $p, ($index+1));
      $html .= $part['html'];
      $plain .= $part['plain'];
      if (!$charset && $part['charset']) $charset = $part['charset'];
      if ($part['attachments']) $attachments = array_merge($attachments, $part['attachments']);
    }
    if ($html) {
      if ($charset && $charset!='utf-8') $html = iconv($charset, 'utf-8', $html);
      $result['body']['html'] = $html;
    }
    if ($plain) {
      if ($charset && $charset!='utf-8') $plain = iconv($charset, 'utf-8', $plain);
      $result['body']['plain'] = $plain;
    }
    if ($charset) $result['charset'] = $charset;
    if ($attachments) {
      foreach ($attachments as $index=>$attach) $attachments[$index]['name'] = Header2utf8($attach['name']);
      $result['attachments'] = $attachments;
    }
  }
  return $result;
}


  // функция читает часть E-mail сообщения
  // $mbox - IMAP stream
  // $mid - id сообщения
  // $p - часть E-mail сообщения
  // $partno - '1', '2', '2.1', '2.1.3', etc for multipart, 0 if simple
function GetPart($mbox, $mid, $p, $partno=0) {
  $html = $plain = $charset = '';
  $attachments = $result = [];
    // DECODE DATA
  $data = ($partno) ?
      imap_fetchbody($mbox, $mid, $partno) :  // multipart
      imap_body($mbox, $mid);  // simple
  if (4==$p->encoding) $data = quoted_printable_decode($data);
  //else if (1==$p->encoding) $data = imap_8bit($data);
  elseif (3==$p->encoding) $data = base64_decode($data);
    // PARAMETERS
  $params = [];
  if ($p->parameters) foreach ($p->parameters as $x) $params[strtolower($x->attribute)] = $x->value;
  if ($p->dparameters) foreach ($p->dparameters as $x) $params[strtolower($x->attribute)] = $x->value;
    // ATTACHMENT
  if ($params['filename'] || $params['name']) {
    $filename = ($params['filename']) ? $params['filename'] : $params['name'];
    $name = basename($filename);
    if ($name && $data) $attachments[] = ['name'=>$filename, 'data'=>$data];
  }
    // TEXT
  if (0==$p->type && $data) {
    if ('plain'==strtolower($p->subtype)) $plain .= trim($data)."\n\n";
    else $html .= $data . "<br><br>";
    if (!$charset && $params['charset']) $charset = $params['charset'];
  }
    // EMBEDDED MESSAGE
  elseif (2==$p->type && $data) $plain .= $data."\n\n";
    // SUBPART RECURSION
  if ($p->parts) {
    foreach ($p->parts as $index=>$p2) {
      $part = GetPart($mbox, $mid, $p2, $partno.'.'.($index+1));  // 1.2, 1.2.1, etc.
      $html .= $part['html'];
      $plain .= $part['plain'];
      if (!$charset && $part['charset']) $charset = $part['charset'];
      $attachments = array_merge($attachments, $part['attachments']);
    }
  }
  $pattern = '/<base.+?>/ui';
  if ($html) $result['html'] = preg_replace($pattern, '', $html);
  if ($plain) $result['plain'] = preg_replace($pattern, '', $plain);
  if ($charset) $result['charset'] = $charset;
  if ($attachments) $result['attachments'] = $attachments;
  return $result;
}

  // применяет функцию imap_utf8 к $header
  // imap_utf8 - https://www.php.net/manual/ru/function.imap-utf8.php, преобразует MIME-кодированный текст в UTF-8
  // если $header - строка, то вызов Header2utf8 идентичен вызову imap_utf8
  // если $header - массив, то функция рекурсивно применяется ко всем элементам массива и возвращает преобразованный массив
function Header2utf8($header) {
  if (!$header) return false;
  if (!is_array($header)) {
    $text = imap_utf8($header);
    $pattern = '/<base.+?>/ui';
    $text = preg_replace($pattern, '', $text);
    if (false!==mb_stripos($text,'=?utf-8?')) { 
      $e = imap_mime_header_decode($text); 
      if ($e) {
        $text_ = '';
        $charsets = ['default','utf-8'];
        foreach ($e as $el) if (in_array(strtolower($el->charset),$charsets)) $text_ .= $el->text; 
        if ($text_) $text = $text_; 
      }
    }
    return $text;
  }
  else foreach ($header as $key=>$value) $header[$key] = Header2utf8($value);
  return $header;
}
