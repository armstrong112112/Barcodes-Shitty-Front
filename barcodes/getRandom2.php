<?php

if(isset($_POST['RES']))
{

    $url = "http://188.225.46.139/api/v1/getRandom";
    
    $RES = $_POST['RES'];
    $OBJ = $_POST['OBJ'];


    $razbiv = explode(',', $OBJ);

    $json = '{"values": {"req": {';

    foreach ($razbiv as $razb)
    {
        $value = substr($razb, strpos($razb, "/") + 1);
        $value = str_replace('/', '', $value);
        $name = mb_substr($razb, 0, 3);

        $json .= '"'.$name.'":"'.$value.'",';
    }


    $json = mb_substr($json, 0, -1);
    $json .= '}, "res": ["DBD", "DBA"]';
    $json .= '}}';
    
    $arr = $json;
    
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $arr );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    $result = curl_exec($ch);
    curl_close($ch);



    
    $result = json_decode($result);
    $n = 1;
    foreach ($result as $resa) 
    { 
        if ($n == 1) { $ISS = $resa;}
        else if ($n == 2) {$EXP = $resa;}
        $n++;
    }

    /*
    $month = mb_substr($ISS, 0, 2);
    $day = mb_substr($ISS, 2, 2);
    $year = mb_substr($ISS, 4, 4);
    $date_ISS = $year."-".$month."-".$day;
    $date_ISS = strtotime($date_ISS);
    $date_ISS = date('Y-m-d', $date_ISS);

    $month2 = mb_substr($EXP, 0, 2);
    $day2 = mb_substr($EXP, 2, 2);
    $year2 = mb_substr($EXP, 4, 4);
    $date_EXP = $year2."-".$month2."-".$day2;
    $date_EXP = strtotime($date_EXP);
    $date_EXP = date('Y-m-d', $date_EXP);
*/

    header('Content-Type: application/json');
    $result = array(
        'success'  => 'Успех',
        'ISS' => $ISS,
        'res' => $arr,
        'EXP' => $EXP   
    );
    echo json_encode($result);
}


?>