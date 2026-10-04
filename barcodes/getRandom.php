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
        $value = str_replace('-', '', $value);
        $name = mb_substr($razb, 0, 3);

        $json .= '"'.$name.'":"'.$value.'",';
    }


    /*
    foreach ($razbiv as $raz)
    {
        $raz = str_replace('-', '', $raz);
        $json .= '"'.$raz.'":"'.$raz.'",';
    }
    */
    $json = mb_substr($json, 0, -1);
    $json .= '}, "res": ["'.$RES.'"]';
    $json .= '}}';

    
    $arr = $json;
    //$arr = '{"values": {"req": {"DAJ": "'.$DAJ.'"},"res": ["'.$RES.'"]}}';
    
    
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $arr );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    $result = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($result,true);

    foreach ($result as $resa) { $message = $resa; }



    header('Content-Type: application/json');
    $result = array(
        'success'  => 'Успех',
        'arr' => $arr,
        'desc' => $message
    );
    echo json_encode($result);
}


?>