<?php

/*
 * Фронтенд для получения оплат от Монобанка в виде GET запроса
 */

// подключаем API OpenPayz
include ("../../libs/api.openpayz.php");

/**
 * Check for GET have needed variables
 *
 * @param  array $params array of GET variables to check
 * @return  bool
 *
 */
function mono_CheckGet($params) {
    $result = true;
    if (!empty($params)) {
        foreach ($params as $eachparam) {
            if (isset($_GET[$eachparam])) {
                if (empty($_GET[$eachparam])) {
                    $result = false;
                }
            } else {
                $result = false;
            }
        }
    }
    return ($result);
}

/**
 * Check is transaction unique?
 *
 * @param string $hash string hash to check
 *
 * @return bool
 */
function mono_CheckTransaction($hash) {
    $hash = mysql_real_escape_string($hash);
    $query = "SELECT `id` from `op_transactions` WHERE `hash`='" . $hash . "'";
    $data = simple_query($query);
    if (!empty($data)) {
        return (false);
    } else {
        return (true);
    }
}

/**
 * Get transaction id by its hash
 *
 * @param  string $tablename name of the table to extract last id
 * @return  string
 *
 */
function mono_getIdByHash($hash) {
    $hash = mysql_real_escape_string($hash);
    $query = "SELECT `id` from `op_transactions` WHERE `hash`='" . $hash . "'";
    $result = simple_query($query);
    return ($result['id']);
}

/**
 * Returns all user RealNames
 * 
 * @return array
 */
function mono_UserGetAllRealnames() {
    $query_fio = "SELECT * from `realname`";
    $allfioz = simple_queryall($query_fio);
    $fioz = array();
    if (!empty($allfioz)) {
        foreach ($allfioz as $ia => $eachfio) {
            $fioz[$eachfio['login']] = $eachfio['realname'];
        }
    }
    return($fioz);
}

//Выбираем все адреса в формате Ubilling
function mono_AddressGetFulladdresslist() {
$result=array();
$apts=array();
$builds=array();
//наглая заглушка
$alterconf['ZERO_TOLERANCE']=0;
$alterconf['CITY_DISPLAY']=0;
$city_q="SELECT * from `city`";
$adrz_q="SELECT * from `address`";
$apt_q="SELECT * from `apt`";
$build_q="SELECT * from build";
$streets_q="SELECT * from `street`";
$alladdrz=simple_queryall($adrz_q);
$allapt=simple_queryall($apt_q);
$allbuilds=simple_queryall($build_q);
$allstreets=simple_queryall($streets_q);
if (!empty ($alladdrz)) {
   
        foreach ($alladdrz as $io1=>$eachaddress) {
        $address[$eachaddress['id']]=array('login'=>$eachaddress['login'],'aptid'=>$eachaddress['aptid']);
        }
        foreach ($allapt as $io2=>$eachapt) {
        $apts[$eachapt['id']]=array('apt'=>$eachapt['apt'],'buildid'=>$eachapt['buildid']);
        }
        foreach ($allbuilds as $io3=>$eachbuild) {
        $builds[$eachbuild['id']]=array('buildnum'=>$eachbuild['buildnum'],'streetid'=>$eachbuild['streetid']);
        }
        foreach ($allstreets as $io4=>$eachstreet) {
        $streets[$eachstreet['id']]=array('streetname'=>$eachstreet['streetname'],'cityid'=>$eachstreet['cityid']);
        }

    foreach ($address as $io5=>$eachaddress) {
        $apartment=$apts[$eachaddress['aptid']]['apt'];
        $building=$builds[$apts[$eachaddress['aptid']]['buildid']]['buildnum'];
        $streetname=$streets[$builds[$apts[$eachaddress['aptid']]['buildid']]['streetid']]['streetname'];
        $cityid=$streets[$builds[$apts[$eachaddress['aptid']]['buildid']]['streetid']]['cityid'];
        // zero apt handle
        if ($alterconf['ZERO_TOLERANCE']) {
            if ($apartment==0) {
            $apartment_filtered='';
            } else {
            $apartment_filtered='/'.$apartment;
            }
        } else {
        $apartment_filtered='/'.$apartment;    
        }
    
        if (!$alterconf['CITY_DISPLAY']) {
        $result[$eachaddress['login']]=$streetname.' '.$building.$apartment_filtered;
        } else {
        $result[$eachaddress['login']]=$cities[$cityid].' '.$streetname.' '.$building.$apartment_filtered;
        }
    }
}

return($result);
}


/**
 * Get transaction datetime by its hash
 *
 * @param  string $tablename name of the table to extract last id
 * @return  string
 *
 */
function mono_getDateByHash($hash) {
    $hash = mysql_real_escape_string($hash);
    $query = "SELECT `date` from `op_transactions` WHERE `hash`='" . $hash . "'";
    $result = simple_query($query);
    return ($result['date']);
}

$required = array('command', 'txn_id', 'account', 'sum');

//если нас пнули объязательными параметрами
if (mono_CheckGet($required)) {

    //это нас monobank как-бы проверяет на вшивость
    if ($_GET['command'] == 'check') {
        $allcustomers = op_CustomersGetAll();
        $hashClean = trim($_GET['txn_id']);
        $customerid = trim($_GET['account']);

        //нашелся братиша!
        if (isset($allcustomers[$customerid])) {
            $userlogin = $allcustomers[$customerid];
            $alladdress = mono_AddressGetFulladdresslist();
            $allrealnames = mono_UserGetAllRealnames();
            $userData = simple_query("SELECT * from `users` WHERE `login`='" . $userlogin . "'");
            $userMail = simple_query("SELECT * from `emails` WHERE `login`='" . $userlogin . "'");
            $good_reply = '
                    <?xml version="1.0" encoding="UTF-16"?>
                    <response>
                       <mono_txn_id>' . $hashClean . '</mono_txn_id>
                           <result>0</result>
                           <fields>
                           <field1 name="balance">' . @$userData['Cash'] . '</field1>
                           <field3 name="name">' . @$allrealnames[$userlogin] . '</field3>
			   <field4 name="address">' . @$alladdress[$userlogin] . '</field4>
			   </fields>
                    </response>
                    ';
            $good_reply = trim($good_reply);
            die($good_reply);
        } else {

            $bad_reply = '
                  <?xml version="1.0" encoding="UTF-8"?>
                    <response>
                       <mono_txn_id>' . $hashClean . '</mono_txn_id>
                       <result>5</result>
                  </response>
                ';
            $bad_reply = trim($bad_reply);
            die($bad_reply);
        }
    }

    //Запрос на внесение платежа
    if ($_GET['command'] == 'pay') {

        $hash = 'MONOB_' . trim($_GET['txn_id']);
        $hashClean = trim($_GET['txn_id']);
        $summ = $_GET['sum'];
        $customerid = trim($_GET['account']);
        $paysys = 'MONOBANK';
        $note = 'some debug info';

        $allcustomers = op_CustomersGetAll();
        //опять ожидаем подляны и все-таки проверим хотя бы валидность кастомера
        if (isset($allcustomers[$customerid])) {

            //а также уникальность транзакции
            if (mono_CheckTransaction($hash)) {
                //регистрируем новую транзакцию
                op_TransactionAdd($hash, $summ, $customerid, $paysys, $note);
                //вызываем обработчики необработанных транзакций
                op_ProcessHandlers();

                $newTransactionId = mono_getIdByHash($hash);
                $newTransactionDate = mono_getDateByHash($hash);

                $good_reply = '
            <?xml version="1.0" encoding="UTF-8"?>
            <response>
            <mono_txn_id>' . $hashClean . '</mono_txn_id>
            <prv_txn>' . $newTransactionId . '</prv_txn>
            <prv_txn_date>' . $newTransactionDate . '</prv_txn_date>
            <sum>' . $summ . '</sum>
            <result>0</result>
            <comment>OK</comment>
            </response>
            ';
                $good_reply = trim($good_reply);
                die($good_reply);
            } else {
                //Если транзакция уже зарегистрирована
                $newTransactionId = mono_getIdByHash($hash);
                $newTransactionDate = mono_getDateByHash($hash);
                $transactionDoneReply = '
                    <?xml version="1.0" encoding="UTF-8"?>
                    <response>
                    <mono_txn_id>' . $hashClean . '</mono_txn_id>
                    <prv_txn>' . $newTransactionId . '</prv_txn>
                    <prv_txn_date>' . $newTransactionDate . '</prv_txn_date>
                    <sum>' . $summ . '</sum>
                    <result>0</result>
                    <comment>OK</comment>
                    </response>
                    ';

                $transactionDoneReply = trim($transactionDoneReply);
                die($transactionDoneReply);
            }
        } else {
            $bad_reply = '
                  <?xml version="1.0"?>
                    <response>
                       <mono_txn_id>' . $hashClean . '</mono_txn_id>
                       <result>5</result>
                  </response>
                ';
            $bad_reply = trim($bad_reply);
            die($bad_reply);
        }
    }
}
?>
