<?php


if(isset($_POST['DCG']))
{

    
  include_once '/var/www/html/includes/db.inc.php';

  $DAJ = $_POST['DAJ'];
  $DAJ = mb_strtoupper($DAJ);

  $DDB = $_POST['DDB'];
  $DDB = str_replace('/', '', $DDB);

  $QQQ = $_POST['QQQ'];
  $QQQ = mb_strtoupper($QQQ);

  $DCG = $_POST['DCG'];
  $DCG = mb_strtoupper($DCG);

  //static field 9
  $DCS = $_POST['DCS'];
  $DCS = mb_strtoupper($DCS);

  $DAC = $_POST['DAC'];
  $DAC = mb_strtoupper($DAC);

  $DAD = $_POST['DAD'];
  $DAD = mb_strtoupper($DAD);

  $DBB = $_POST['DBB'];
  $DBB = str_replace('/', '', $DBB);

  $DBC = $_POST['DBC'];
  $DBC = mb_strtoupper($DBC);

  $DAG = $_POST['DAG'];
  $DAG = mb_strtoupper($DAG);

  $DAI = $_POST['DAI'];
  $DAI = mb_strtoupper($DAI);

  $DAK = $_POST['DAK'];
  $DAK = mb_strtoupper($DAK);

  $DDA = $_POST['DDA'];
  $DDA = mb_strtoupper($DDA);


  $DCA = $_POST['DCA'];
  $DCA = mb_strtoupper($DCA);
  //$DDK = $_POST['DDK'];
  //$DDL = $_POST['DDL'];
  $DDK = 0;
  $DDL = 0;

  $DAU = $_POST['DAU'];
  $DAU = mb_strtoupper($DAU);
  
  $DAW = $_POST['DAW'];
  $DAW = mb_strtoupper($DAW);

  $DAZ = $_POST['DAZ'];
  $DAZ = mb_strtoupper($DAZ);

  $DAY = $_POST['DAY'];
  $DAY = mb_strtoupper($DAY);

  $DCB = $_POST['DCB'];
  $DCB = mb_strtoupper($DCB);

  $DCD = $_POST['DCD'];
  $DCD = mb_strtoupper($DCD);

  $DBD = "";
  $DAQ = "";
  $DBA = "";
  $DCK = "";
  $DCF = "";

  $switchDAU = $_POST['switchDAU'];
  if ($switchDAU == 'cm') { $DAU = $DAU*0.393701; $DAU = round($DAU); }

  $switchDAW = $_POST['switchDAW'];
  if ($switchDAW == 'kg') { $DAW = $DAW*2.20462; $DAW = round($DAW); }

  $Msearch = array("DAJ" => $DAJ, "DDB" => $DDB, "QQQ" => $QQQ, "DCG" => $DCG, "DCS" => $DCS, "DAC" => $DAC, "DAD" => $DAD, "DBB" => $DBB, "DBC" => $DBC, "DAG" => $DAG, "DAI" => $DAI, "DAK" => $DAK, "DDA" => $DDA, "DCA" => $DCA, "DDK" => $DDK, "DDL" => $DDL, "DAU" => $DAU, "DAW" => $DAW, "DAZ" => $DAZ, "DAY" => $DAY, "DCB" => $DCB, "DCD" => $DCD, "DBD" => $DBD, "DAQ" => $DAQ, "DBA" => $DBA, "DCK" => $DCK, "DCF" => $DCF );


  $url = "http://188.225.46.139/api/v1/barcodes";
  $arr = '{"values": ["'.$DCG.'","'.$DAJ.'","'.$DDB.'","'.$QQQ.'",true]}';

  $ch = curl_init( $url );
  curl_setopt( $ch, CURLOPT_POSTFIELDS, $arr );
  curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
  $bars = curl_exec($ch);
  curl_close($ch);
  $bars = json_decode($bars,true);

    $newMassiv[] = '';
    // Data barcodes
    foreach ($bars as $resa)
    {

        
            
        
        foreach ($resa as $r)
        {
            if($r['abr'] == 'DAQ') { $newMassiv += array(2 => $r); }
            if($r['abr'] == 'DBD') { $newMassiv += array(1 => $r); }
            if($r['abr'] == 'DCK') { $newMassiv += array(4 => $r); }
            if($r['abr'] == 'DCF') { $newMassiv += array(3 => $r); }


            /*
            if($r['abr'] == 'DAQ') 
            {
                
                foreach ($r['randomHandler'] as $key => $val) { $nMas = explode(',', $key); }
                
                $json = '{"values": {"req": {';

                foreach ($nMas as $mas)
                {
                    foreach ($Msearch as $key => $value)
                    {
                        if ($mas == $key) { $json .= '"'.$mas.'":"'.$value.'",'; }
                    }
                }
                
                $json = mb_substr($json, 0, -1);
                $json .= '}, "res": ["DAQ"]';
                $json .= '}}';
                
                $arr = $json;

                $url = "http://188.225.46.139/api/v1/getRandom";

                $ch = curl_init( $url );
                curl_setopt( $ch, CURLOPT_POSTFIELDS, $arr );
                curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                $mDAQ = curl_exec($ch);
                curl_close($ch);

                $mDAQ = json_decode($mDAQ,true);

                foreach ($mDAQ as $vDAQ) { $DAQ = $vDAQ; }


            }
            if ($r['abr'] == 'DAQ') {continue;}
            //DAQ
            

            

            if($r['abr'] == 'DBD') 
            {

                foreach ($r['randomHandler'] as $key => $val) { $nMas2 = explode(',', $key); }
                
                $json = '{"values": {"req": {';

                foreach ($nMas2 as $mas)
                {
                    foreach ($Msearch as $key => $value)
                    {
                        if ($mas == $key) { $json .= '"'.$mas.'":"'.$value.'",'; }
                    }
                }
                
                $json = mb_substr($json, 0, -1);
                $json .= '}, "res": ["DBD", "DBA"]';
                $json .= '}}';
                
                $arr = $json;

                $url = "http://188.225.46.139/api/v1/getRandom";

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
                    if ($n == 1)        { $DBD = $resa; }
                    else if ($n == 2)   { $DBA = $resa; }
                    $n++;
                } 
            }
            if ($r['abr'] == 'DBD') {continue;}
            //DBD
            if ($r['abr'] == 'DBA') {continue;}
            //DBA
            

            

            if($r['abr'] == 'DCK') 
            {
                
                foreach ( $r['calculateHandler'] as $key => $val ) { $nMas = explode(',', $key); }
                
                $json = '{"values": {"req": {';

                foreach ($nMas as $mas)
                {
                    foreach ($Msearch as $key => $value)
                    {
                        if ($mas == $key) { $json .= '"'.$mas.'":"'.$value.'",'; }
                    }
                }
                
                $json = mb_substr($json, 0, -1);
                $json .= '}, "res": ["DCK"]';
                $json .= '}}';
                
                $arr3 = $json;

                $url2 = "http://188.225.46.139/api/v1/getCalculate";

                $ch2 = curl_init( $url2 );
                curl_setopt( $ch2, CURLOPT_POSTFIELDS, $arr3 );
                curl_setopt( $ch2, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                curl_setopt( $ch2, CURLOPT_RETURNTRANSFER, true );
                $result = curl_exec($ch2);
                curl_close($ch2);

                $result = json_decode($result);

                foreach ($result as $resa) 
                { 
                    $DCK = $resa;
                }
                
            }
            if ($r['abr'] == 'DCK') {continue;}
            //DCK
            

            

            if($r['abr'] == 'DCF') 
            {
                
                foreach ( $r['calculateHandler'] as $key => $val ) { $nMas = explode(',', $key); }
                
                $json = '{"values": {"req": {';

                foreach ($nMas as $mas)
                {
                    foreach ($Msearch as $key => $value)
                    {
                        if ($mas == $key) { $json .= '"'.$mas.'":"'.$value.'",'; }
                    }
                }
                
                $json = mb_substr($json, 0, -1);
                $json .= '}, "res": ["DCF"]';
                $json .= '}}';
                
                $arr = $json;

                $url2 = "http://188.225.46.139/api/v1/getCalculate";

                $ch2 = curl_init( $url2 );
                curl_setopt( $ch2, CURLOPT_POSTFIELDS, $arr );
                curl_setopt( $ch2, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                curl_setopt( $ch2, CURLOPT_RETURNTRANSFER, true );
                $result = curl_exec($ch2);
                curl_close($ch2);

                $result = json_decode($result);

                foreach ($result as $resa) 
                { 
                    $DCF = $resa;
                }

            }
            if ($r['abr'] == 'DCF') {continue;}
            //DCF
            */

        }

        

    }

    ksort($newMassiv);

    foreach ($newMassiv as $r)
    {
        if($r['abr'] == 'DAQ') 
            {
                
                foreach ($r['randomHandler'] as $key => $val) { $nMas = explode(',', $key); }
                
                $json = '{"values": {"req": {';

                foreach ($nMas as $mas)
                {
                    foreach ($Msearch as $key => $value)
                    {
                        if ($mas == $key) { $json .= '"'.$mas.'":"'.$value.'",'; }
                    }
                }
                
                $json = mb_substr($json, 0, -1);
                $json .= '}, "res": ["DAQ"]';
                $json .= '}}';
                
                $arr = $json;

                $url = "http://188.225.46.139/api/v1/getRandom";

                $ch = curl_init( $url );
                curl_setopt( $ch, CURLOPT_POSTFIELDS, $arr );
                curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
                $mDAQ = curl_exec($ch);
                curl_close($ch);

                $mDAQ = json_decode($mDAQ,true);

                foreach ($mDAQ as $vDAQ) { $DAQ = $vDAQ; }

                $Msearch = array("DAJ" => $DAJ, "DDB" => $DDB, "QQQ" => $QQQ, "DCG" => $DCG, "DCS" => $DCS, "DAC" => $DAC, "DAD" => $DAD, "DBB" => $DBB, "DBC" => $DBC, "DAG" => $DAG, "DAI" => $DAI, "DAK" => $DAK, "DDA" => $DDA, "DCA" => $DCA, "DDK" => $DDK, "DDL" => $DDL, "DAU" => $DAU, "DAW" => $DAW, "DAZ" => $DAZ, "DAY" => $DAY, "DCB" => $DCB, "DCD" => $DCD, "DBD" => $DBD, "DAQ" => $DAQ, "DBA" => $DBA, "DCK" => $DCK, "DCF" => $DCF );
            }
            //DAQ
            

            

            if($r['abr'] == 'DBD') 
            {

                foreach ($r['randomHandler'] as $key => $val) { $nMas2 = explode(',', $key); }
                
                $json = '{"values": {"req": {';

                foreach ($nMas2 as $mas)
                {
                    foreach ($Msearch as $key => $value)
                    {
                        if ($mas == $key) { $json .= '"'.$mas.'":"'.$value.'",'; }
                    }
                }
                
                $json = mb_substr($json, 0, -1);
                $json .= '}, "res": ["DBD", "DBA"]';
                $json .= '}}';
                
                $arr = $json;

                $url = "http://188.225.46.139/api/v1/getRandom";

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
                    if ($n == 1)        { $DBD = $resa; }
                    else if ($n == 2)   { $DBA = $resa; }
                    $n++;
                }

                $Msearch = array("DAJ" => $DAJ, "DDB" => $DDB, "QQQ" => $QQQ, "DCG" => $DCG, "DCS" => $DCS, "DAC" => $DAC, "DAD" => $DAD, "DBB" => $DBB, "DBC" => $DBC, "DAG" => $DAG, "DAI" => $DAI, "DAK" => $DAK, "DDA" => $DDA, "DCA" => $DCA, "DDK" => $DDK, "DDL" => $DDL, "DAU" => $DAU, "DAW" => $DAW, "DAZ" => $DAZ, "DAY" => $DAY, "DCB" => $DCB, "DCD" => $DCD, "DBD" => $DBD, "DAQ" => $DAQ, "DBA" => $DBA, "DCK" => $DCK, "DCF" => $DCF );
            }

            

            if($r['abr'] == 'DCK') 
            {
                
                foreach ( $r['calculateHandler'] as $key => $val ) { $nMas = explode(',', $key); }
                
                $json = '{"values": {"req": {';

                foreach ($nMas as $mas)
                {
                    foreach ($Msearch as $key => $value)
                    {
                        if ($mas == $key) { $json .= '"'.$mas.'":"'.$value.'",'; }
                    }
                }
                
                $json = mb_substr($json, 0, -1);
                $json .= '}, "res": ["DCK"]';
                $json .= '}}';
                
                $arr3 = $json;

                $url2 = "http://188.225.46.139/api/v1/getCalculate";

                $ch2 = curl_init( $url2 );
                curl_setopt( $ch2, CURLOPT_POSTFIELDS, $arr3 );
                curl_setopt( $ch2, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                curl_setopt( $ch2, CURLOPT_RETURNTRANSFER, true );
                $result = curl_exec($ch2);
                curl_close($ch2);

                $result = json_decode($result);

                foreach ($result as $resa) 
                { 
                    $DCK = $resa;
                }
                
                $Msearch = array("DAJ" => $DAJ, "DDB" => $DDB, "QQQ" => $QQQ, "DCG" => $DCG, "DCS" => $DCS, "DAC" => $DAC, "DAD" => $DAD, "DBB" => $DBB, "DBC" => $DBC, "DAG" => $DAG, "DAI" => $DAI, "DAK" => $DAK, "DDA" => $DDA, "DCA" => $DCA, "DDK" => $DDK, "DDL" => $DDL, "DAU" => $DAU, "DAW" => $DAW, "DAZ" => $DAZ, "DAY" => $DAY, "DCB" => $DCB, "DCD" => $DCD, "DBD" => $DBD, "DAQ" => $DAQ, "DBA" => $DBA, "DCK" => $DCK, "DCF" => $DCF );
            }

            if($r['abr'] == 'DCF') 
            {
                
                foreach ( $r['calculateHandler'] as $key => $val ) { $nMas = explode(',', $key); }
                
                $json = '{"values": {"req": {';

                foreach ($nMas as $mas)
                {
                    foreach ($Msearch as $key => $value)
                    {
                        if ($mas == $key) { $json .= '"'.$mas.'":"'.$value.'",'; }
                    }
                }
                
                $json = mb_substr($json, 0, -1);
                $json .= '}, "res": ["DCF"]';
                $json .= '}}';
                
                $arr = $json;

                $url2 = "http://188.225.46.139/api/v1/getCalculate";

                $ch2 = curl_init( $url2 );
                curl_setopt( $ch2, CURLOPT_POSTFIELDS, $arr );
                curl_setopt( $ch2, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
                curl_setopt( $ch2, CURLOPT_RETURNTRANSFER, true );
                $result = curl_exec($ch2);
                curl_close($ch2);

                $result = json_decode($result);

                foreach ($result as $resa) 
                { 
                    $DCF = $resa;
                }

                $Msearch = array("DAJ" => $DAJ, "DDB" => $DDB, "QQQ" => $QQQ, "DCG" => $DCG, "DCS" => $DCS, "DAC" => $DAC, "DAD" => $DAD, "DBB" => $DBB, "DBC" => $DBC, "DAG" => $DAG, "DAI" => $DAI, "DAK" => $DAK, "DDA" => $DDA, "DCA" => $DCA, "DDK" => $DDK, "DDL" => $DDL, "DAU" => $DAU, "DAW" => $DAW, "DAZ" => $DAZ, "DAY" => $DAY, "DCB" => $DCB, "DCD" => $DCD, "DBD" => $DBD, "DAQ" => $DAQ, "DBA" => $DBA, "DCK" => $DCK, "DCF" => $DCF );
            }
    }

/*
    header('Content-Type: application/json');
    $result5 = array(
        'success'  => 'Успех',
        'DBD' => $DBD,
        'DBA' => $DBA,
        'DAQ' => $DAQ,
        'DCK' => $DCK,
        'DCK2' => $arr3,
        'nMas2' => $nMas2,
        'Msearch' => $Msearch,
        'bars' => $bars,
        'DCF' => $DCF

    );
    echo json_encode($result5);
*/




  $checkDAJ = $_POST['DAJ'];
  $checkDDB = $_POST['DDB'];

  $checkDDB = substr_replace($checkDDB, '/', 2, 0);
  $checkDDB = substr_replace($checkDDB, '/', 5, 0);

  

  $sql = "SELECT * FROM light_auto where DAJ = :DAJ AND DDB = :DDB --";
  $statement = $pdo->prepare($sql);
  $statement->bindValue(':DAJ', $checkDAJ);
  $statement->bindValue(':DDB', $checkDDB);
  $statement->execute();
  $fields = $statement->fetchAll();

  foreach ($fields as $field)
  {
    $DCA = $field['DCA'];
    $DCA = mb_strtoupper($DCA);
    $DCB = $field['DCB'];
    $DCB = mb_strtoupper($DCB);
    $DCD = $field['DCD'];
    $DCD = mb_strtoupper($DCD);
    $DDA = $field['DDA'];
    $DDA = mb_strtoupper($DDA);
  }

  
  $arr = '{"values": {"DAJ": "'.$DAJ.'","DDB": "'.$DDB.'","QQQ": "'.$QQQ.'", "DCG": "'.$DCG.'", "DCS": "'.$DCS.'", "DAC": "'.$DAC.'", "DAD": "'.$DAD.'", "DBB": "'.$DBB.'", "DBC": "'.$DBC.'", "DAG": "'.$DAG.'", "DAI": "'.$DAI.'", "DAK": "'.$DAK.'", "DDA": "'.$DDA.'", "DAQ": "'.$DAQ.'", "DCA": "'.$DCA.'", "DDK": '.$DDK.', "DDL": '.$DDL.', "DBD": "'.$DBD.'", "DBA": "'.$DBA.'", "DCK": "'.$DCK.'", "DAU": "'.$DAU.'", "DAW": "'.$DAW.'", "DCF": "'.$DCF.'", "DAZ": "'.$DAZ.'", "DAY": "'.$DAY.'", "DCB": "'.$DCB.'", "DCD": "'.$DCD.'" }}';

  $url3 = "http://188.225.46.139/api/v1/genBarcode";
  $ch3 = curl_init( $url3 );
  curl_setopt( $ch3, CURLOPT_POSTFIELDS, $arr );
  curl_setopt( $ch3, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
  curl_setopt( $ch3, CURLOPT_RETURNTRANSFER, true );
  $result4 = curl_exec($ch3);
  curl_close($ch3);

  

  
  if(!empty($result4))
  {

    $result4 = json_decode($result4)->addr;

    $sql = "INSERT INTO barcodes ( DAJ,DDB,QQQ,DCG,DCS,DAC,DAD,DBB,DBC,DAG,DAI,DAK,DDA,DAQ,DCA,DDK,DDL,DBD,DBA,DCK,DAU,DAW,DCF,DAZ,DAYY,DCB,DCD,image,time ) VALUES (:DAJ,:DDB,:QQQ,:DCG,:DCS,:DAC,:DAD,:DBB,:DBC,:DAG,:DAI,:DAK,:DDA,:DAQ,:DCA,:DDK,:DDL,:DBD,:DBA,:DCK,:DAU,:DAW,:DCF,:DAZ,:DAYY,:DCB,:DCD,:image, NOW() ) --";
    $sth = $pdo->prepare($sql);
    $sth->bindValue(':DAJ', $DAJ);
    $sth->bindValue(':DDB', $DDB);
    $sth->bindValue(':QQQ', $QQQ);
    $sth->bindValue(':DCG', $DCG);
    $sth->bindValue(':DCS', $DCS);
    $sth->bindValue(':DAC', $DAC);
    $sth->bindValue(':DAD', $DAD);
    $sth->bindValue(':DBB', $DBB);
    $sth->bindValue(':DBC', $DBC);
    $sth->bindValue(':DAG', $DAG);
    $sth->bindValue(':DAI', $DAI);
    $sth->bindValue(':DAK', $DAK);
    $sth->bindValue(':DDA', $DDA);
    $sth->bindValue(':DAQ', $DAQ);
    $sth->bindValue(':DCA', $DCA);
    $sth->bindValue(':DDK', $DDK);
    $sth->bindValue(':DDL', $DDL);
    $sth->bindValue(':DBD', $DBD);
    $sth->bindValue(':DBA', $DBA);
    $sth->bindValue(':DCK', $DCK);
    $sth->bindValue(':DAU', $DAU);
    $sth->bindValue(':DAW', $DAW);
    $sth->bindValue(':DCF', $DCF);
    $sth->bindValue(':DAZ', $DAZ);
    $sth->bindValue(':DAYY', $DAY);
    $sth->bindValue(':DCB', $DCB);
    $sth->bindValue(':DCD', $DCD);
    $sth->bindValue(':image', $result4);
    $sth->execute();
    $catalog = $pdo->lastInsertId();


    if(!is_dir($_SERVER['DOCUMENT_ROOT']. "/downloads/". $catalog)) { mkdir($_SERVER['DOCUMENT_ROOT']. "/downloads/" . $catalog, 0755); }

    //echo "<pre>".$result."</pre>";

    $image_url = "http://188.225.46.139/barcodes/".$result4.".png";
    $image = file_get_contents($image_url);
    file_put_contents("/var/www/html/downloads/".$catalog."/".$result4.".png", $image);

    $image_url = "http://188.225.46.139/barcodes/".$result4.".svg";
    $image = file_get_contents($image_url);
    file_put_contents("/var/www/html/downloads/".$catalog."/".$result4.".svg", $image);


    $url = "http://188.225.46.139/api/v1/genInventory";
    $inv = $DAJ;
    $arr = '{"value": "'.$inv.'"}';
    
    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $arr );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    $InvCode = curl_exec($ch);
    curl_close($ch);

    $InvCode = json_decode($InvCode)->addr;

    $image_url = "http://188.225.46.139/invNumbers/".$InvCode.".png";
    $image2 = file_get_contents($image_url);
    file_put_contents("/var/www/html/downloads/".$catalog."/".$InvCode.".png", $image2);

    $image_url = "http://188.225.46.139/invNumbers/".$InvCode.".svg";
    $image2 = file_get_contents($image_url);
    file_put_contents("/var/www/html/downloads/".$catalog."/".$InvCode.".svg", $image2);
    
    if (!empty($InvCode))
    {

        $zip = new ZipArchive();
        $zip->open("/var/www/html/downloads/archive".$catalog.".zip", ZIPARCHIVE::CREATE);

        $papka = '/var/www/html/downloads/'.$catalog;

        $files = scandir($papka);
        foreach($files as $file){
            if ($file == '.' || $file == '..' ){continue;}
            $f = $papka.DIRECTORY_SEPARATOR.$file;
            $zip->addFile($f);
        }
        $zip->close();


        $sql = "UPDATE barcodes SET  image2 = :image2 where id = :id --";
        $sth = $pdo->prepare($sql);
        $sth->bindValue(':id', $catalog);
        $sth->bindValue(':image2', $InvCode);
        $sth->execute();
    }


    $endFor =
    "
    <p class='barcodesText'>Your barcode is ready for downloading</p>
	<div style='width: 100%;height:100px;margin: 30px auto;position: relative;'>
		<a href='http://92.255.110.71/downloads/archive".$catalog.".zip' target='_blank' class='butSaveBar' name='saveBarcode'  class='dop-box'  >Save Barcodes</a>
		<button class='butSaveHis' name='history'  class='dop-box' >Check History</button>
	</div>
    ";

    $data = "<img style='width:100%;height: 200px;' src='../downloads/".$catalog."/".$result4.".png' />";
    $data2 = "<img style='width: 50%;height: 100px;margin: 40px;border-top: 10px solid white;border-bottom: 10px solid white;' src='../downloads/".$catalog."/".$InvCode.".png' />";

    $data3 = "<div name='open-close'  class='dop-box open-close open'>Hide Full PDF417 Barcode Data</div>
    <table id='scroll'>
			<tr><td class='td-left'>First Name</td><td class='td-right'>".$DAC."</td></tr>
			<tr><td class='td-left'>Surname</td><td class='td-right'>".$DCS."</td></tr>
            <tr><td class='td-left'>Middle Name</td><td class='td-right'>".$DAD."</td></tr>
			<tr><td class='td-left'>Street Address</td><td class='td-right'>".$DAG."</td></tr>
			<tr><td class='td-left'>City</td><td class='td-right'>".$DAI."</td></tr>
			<tr><td class='td-left'>Zip-Code</td><td class='td-right'>".$DAK."</td></tr>
			<tr><td class='td-left'>County</td><td class='td-right'>".$ZGD."</td></tr>
			<tr><td class='td-left'>Date of Birth</td><td class='td-right'>".$DBB."</td></tr>
			<tr><td class='td-left'>Issue Date</td><td class='td-right'>".$DBD."</td></tr>
			<tr><td class='td-left'>Expire Date</td><td class='td-right'>".$DBA."</td></tr>
			<tr><td class='td-left'>DL or ID Number</td><td class='td-right'>".$DAQ."</td></tr>
			<tr><td class='td-left'>Document Discriminator</td><td class='td-right'>".$DCF."</td></tr>
			<tr><td class='td-left'>Inventory Number</td><td class='td-right'>".$DCK."</td></tr>
			<tr><td class='td-left'>Audit Number</td><td class='td-right'>".$DCJ."</td></tr>
			<tr><td class='td-left'>Veteran</td><td class='td-right'>".$DDL."</td></tr>
			<tr><td class='td-left'>Donor</td><td class='td-right'>".$DDK."</td></tr>
			<tr><td class='td-left'>Real ID</td><td class='td-right'>".$DDA."</td></tr>
			<tr><td class='td-left'>Sex</td><td class='td-right'>".$DBC."</td></tr>
			<tr><td class='td-left'>Race</td><td class='td-right'>".$DCL."</td></tr>
			<tr><td class='td-left'>Height</td><td class='td-right'>".$DAU."</td></tr>
			<tr><td class='td-left'>Weight</td><td class='td-right'>".$DAW."</td></tr>
			<tr><td class='td-left'>Hair Color</td><td class='td-right'>".$DAZ."</td></tr>
			<tr><td class='td-left'>Eyes Color</td><td class='td-right'>".$DAY."</td></tr>
			<tr><td class='td-left'>DL Class</td><td class='td-right'>".$DCA."</td></tr>
			<tr><td class='td-left'>Restrictions</td><td class='td-right'>".$DCB."</td></tr>
			<tr><td class='td-left'>Endorsements</td><td class='td-right'>".$DCD."</td></tr>
		</table>
        <script>
        $('.open-close').on('click' , function() {
            if ( $('.open-close').hasClass('open') )
            {
                $('.open-close').html('Open Full PDF417 Barcode Data');
                $('.open-close').removeClass('open');
                $('.open-close').addClass('close');
                $('#scroll').toggle();
            }
            else if ( $('.open-close').hasClass('close') )
            {
                $('.open-close').html('Hide Full PDF417 Barcode Data');
                $('.open-close').removeClass('close');
                $('.open-close').addClass('open');
                $('#scroll').toggle();
            }
        });
        </script>

        <div name='open-close2' class='dop-box open-close2 open2'>Hide Full 128 Code Data</div>
        <table id='scroll2'>
			<tr><td class='td-left'>Inventory Number</td><td class='td-right'>".$DCK."</td></tr>
        </table>

        <script>
        $('.open-close2').on('click' , function() {
            if ( $('.open-close2').hasClass('open2') )
            {
                $('.open-close2').html('Open Full 128 Code Data');
                $('.open-close2').removeClass('open2');
                $('.open-close2').addClass('close2');
                $('#scroll2').toggle();
            }
            else if ( $('.open-close2').hasClass('close2') )
            {
                $('.open-close2').html('Hide Full 128 Code Data');
                $('.open-close2').removeClass('close2');
                $('.open-close2').addClass('open2');
                $('#scroll2').toggle();
            }
        });
        </script>
    ";

    header('Content-Type: application/json');
    $result5 = array(
        'success'  => 'Успех',
        'images' => $data,
        'images2' => $data2,
        'table' => $data3,
        'endFor' => $endFor,

        'DBD' => $DBD,
        'DBA' => $DBA,
        'DAQ' => $DAQ,
        'DCK' => $DCK,
        'DCK2' => $arr3,
        'nMas2' => $nMas2,
        'Msearch' => $Msearch,
        'bars' => $bars,
        'DCF' => $DCF
    );
    echo json_encode($result5);

    }

}





?>
