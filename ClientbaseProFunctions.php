<?php

	// файл с PHP функциями проекта ClientbasePro


	// преобразует кол-во секунд $sec в формат чч:мм:сс      
function TimeFormat2(int $sec) {
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








?>
