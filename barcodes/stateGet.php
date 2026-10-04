<?php

if(isset($_POST['chooseCountry']))
{

    include_once '/var/www/html/includes/db.inc.php';

    $country = $_POST['chooseCountry'];

    $sql = "SELECT * FROM state where country = :country AND status = :status --";
    $statement = $pdo->prepare($sql);
    $statement->bindValue(':country', $country);
    $statement->bindValue(':status', 'start');
    $statement->execute();
    $states = $statement->fetchAll();

    unset($states[1]); // AZ Arizona Old

    unset($states[8]); // FL
    unset($states[10]); // GA
    unset($states[27]); // NV
    unset($states[32]); // NY
    unset($states[44]); // TX
    unset($states[48]); // VA

    $message  = "<select name='DAJ' id='select-box1' class='select ".$country."'>";
    $message .= "<option value='-'> - </option>";

    foreach ($states as $state)
    {
        $message .= "<option atribute='".$state['name']."' value='".$state['state']."'>".$state['state']." (".$state['name'].") </option>";
    }
    $message .= "</select>";

    header('Content-Type: application/json');
    $result = array(
        'success'  => 'Успех',
        'desc' => $message,
        'status' => $states
    );
    echo json_encode($result);

}


if(isset($_POST['state']))
{

    include_once '/var/www/html/includes/db.inc.php';

    $state = $_POST['state'];
    $country = $_POST['country'];


    $sql = "SELECT * FROM state where country = :country AND status = :status AND state = :state --";
    $statement = $pdo->prepare($sql);
    $statement->bindValue(':country', $country);
    $statement->bindValue(':state', $state);
    $statement->bindValue(':status', 'start');
    $statement->execute();
    $states = $statement->fetchAll();

    $message2  = "<label for='select-box2' class='label select-box2'><span class='label-desc'>Select Revision Date</span> </label>";
    $message2 .= "<select name='DDB' id='select-box2' class='select'>";
    $message2 .= "<option value='-'> - </option>";

    foreach ($states as $state)
    {
        $message2 .= "<option value='".$state['revDate']."'>".$state['revDate']." - ".$state['name']."</option>";
    }
    $message2 .= "</select>";


    $message3  = "<label for='select-box3' class='label select-box3'><span class='label-desc'>Select Type Documentation</span> </label>";
    $message3 .= "<select name='QQQ' id='select-box3' class='select'>";
    $message3 .= "<option value='-'> - </option>";
    $message3 .= "<option value='DL'>Driving License</option>";
    $message3 .= "<option value='ID'>Identification Card</option>";
    $message3 .= "<option value='CDL'>Commercial Driving License</option>";
    $message3 .= "</select>";

    header('Content-Type: application/json');
    $result2 = array(
        'success'  => 'Успех',
        'desc' => $message2,
        'desc2' => $message3,
    );
    echo json_encode($result2);

}






if(isset($_POST['modeBar']))
{

    $modeBar = $_POST['modeBar'];
    $DAJ     = $_POST['DAJ'];
    $DDB     = $_POST['DDB'];
    $DDB     = str_replace('/', '', $DDB);
    $QQQ     = $_POST['QQQ'];
    $DCG     = $_POST['country']; // Если нужно DSG



    $url = "http://188.225.46.139/api/v1/barcodes";
    $arr = '{"values": ["'.$DCG.'","'.$DAJ.'","'.$DDB.'","'.$QQQ.'",true]}';

    $ch = curl_init( $url );
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $arr );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    $bars = curl_exec($ch);
    curl_close($ch);
    $bars = json_decode($bars,true);

    // ADVANCED
    if ($modeBar == 'Advanced')
    {
        $res = "
        <script>$('#birth').datepicker();</script>
        <input name='DAJ' type='hidden' class='input dop-box' value='".$DAJ."' placeholder='DAJ'/>
        <input name='DDB' type='hidden' class='input dop-box' value='".$DDB."' placeholder='DDB'/>
        <input name='QQQ' type='hidden' class='input dop-box' value='".$QQQ."' placeholder='QQQ'/>
        <input name='DCG' type='hidden' class='input dop-box' value='".$DCG."' placeholder='DCG'/>

        <input id='validateDCS' name='DCS' type='text' class='input dop-box' value='' placeholder='DCS' title='Surname' />
        <input id='validateDAC' name='DAC' type='text' class='input dop-box' value='' placeholder='DAC' title='Name' />
        <input id='validateDAD' name='DAD' type='text' class='input dop-box' value='' placeholder='DAD' title='Middle Name' />
        <input name='DBB' type='text' class='input dop-box' value='' placeholder='DBB' title='Date of Birth' id='birth'/>

        <select style='background: url(img/select.png);background-repeat: no-repeat;background-position: 95%;' name='DBC' type='text' class='input dop-box DBC' value='' placeholder='DBC' title='DBC'>
            <option value='-'> - </option>
            <option value='1'>Male</option>
            <option value='2'>Female</option>
        </select>

        <input id='validateDAG' name='DAG' type='text' class='input dop-box' value='' placeholder='DAG' title='Street Address' />
        <input id='validateDAI' name='DAI' type='text' class='input dop-box' value='' placeholder='DAI' title='City' />
        <input id='validateDAK' name='DAK' type='text' class='input dop-box' value='' placeholder='DAK' title='ZIP' />
        
        <label class='checkbox-ios1'>
                    <input type='hidden' name='DDA' value='0' title='The REAL ID mark'>
                    <span class='checkbox-ios-switch1 switchDDA'>
                        <div class='krug'></div>
                    </span>
                    <p class='textforR'>The REAL ID mark</br><img src='img/star.png'/><span class='tooltip' name='RealID' style='color:white;font-size:20px;'> Learn more</span></p>
                </label>

                <script>
                    $('.switchDDA').on('click' , function() {

                        if ( $('[name=DDA]').val() == 0 )
                        {
                            $('[name=DDA]').val('N');
                            $('.krug').css('left', '32px');
                            $('.switchDDA').css('background', 'limegreen');
                        }
                        else if ( $('[name=DDA]').val() == 'N' )
                        {
                            $('[name=DDA]').val('F');
                            $('.krug').css('left', '60px');
                            $('.switchDDA').css('background', 'yellow');
                        }
                        else if ( $('[name=DDA]').val() == 'F' )
                        {
                            $('[name=DDA]').val(0);
                            $('.krug').css('left', '6px');
                            $('.switchDDA').css('background', 'transparent');
                        }

                    });
                </script>

        <script>document.getElementById('validateDCS').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        <script>document.getElementById('validateDAC').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        <script>document.getElementById('validateDAD').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        <script>document.getElementById('validateDAG').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        <script>document.getElementById('validateDAI').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        <script>document.getElementById('validateDAK').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        <script>document.getElementById('validateDDA').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        ";

        
        // Data barcodes
        foreach ($bars as $resa)
        {

            foreach ($resa as $r)
            {

                if($r['abr'] == 'QQQ') { continue;}
                if($r['abr'] == 'DAJ') { continue;}
                if($r['abr'] == 'DDB') { continue;}
                
                if ( $r['type'] == 'choiser' )
                {
                $res .= "<select class='input dop-box' name='".$r['abr']."' title='".$r['title']."'>";
                $res .= "<option value='-'> - </option>";
                foreach ($r['values'] as $val) { $res .= "<option value='".$val."'>".$val."</option>"; }
                $res .= "</select>";
                }

                else if ( $r['type'] == 'input' )
                {
                    $res .= "<input class='input dop-box' name='".$r['abr']."' type='text' placeholder='".$r['abr']."' title='".$r['title']."' />";
                }

                else if ( $r['type'] == 'select' )
                {
                    $res .= "<select style='background: url(img/select.png);background-repeat: no-repeat;background-position: 95%;' class='input dop-box' name='".$r['abr']."' title='".$r['title']."'>";
                    $res .= "<option value='-'> - </option>";
                    foreach ($r['values'] as $val) { $res .= "<option value='".$val."'>".$val."</option>"; }
                    $res .= "</select>";
                }



                else if ( $r['type'] == 'icon' AND $r['abr'] == 'DDK')
                {
                    $res .=
                    "
                    <label class='checkbox-ios'>
                        <input type='hidden' name='DDK' value='".$r['value']."' title='".$r['title']."'/>
                        <input type='checkbox' name='DDK-check' />
                        <span class='checkbox-ios-switch switch2'></span>
                        <p style='display:inline-block; color:#888888;font-size:24px;margin: 10px'>Donor</p>
                    </label>
                    <script>
                        $('.switch2').on('click' , function() {
                            if ( $('[name=DDK-check]').is(':checked') )
                            {
                                $('[name=DDK]').val(0);					
                            }
                            else
                            {
                                $('[name=DDK]').val(1);
                            }
                        });
                    </script>
                    "; 
                }



                else if ( $r['type'] == 'icon' AND $r['abr'] == 'DDL')
                {
                    $res .=
                    "
                    <label class='checkbox-ios'>
                        <input type='hidden' name='DDL' value='".$r['value']."' title='".$r['title']."'/>
                        <input type='checkbox' name='DDL-check' />
                        <span class='checkbox-ios-switch switch1'></span>
                        <p style='display:inline-block; color:#888888;font-size:24px;margin: 10px'>Veteran</p>
                    </label>
                    <script>
                        $('.switch1').on('click' , function() {
                            if ( $('[name=DDL-check]').is(':checked') )
                            {
                                $('[name=DDL]').val(0);					
                            }
                            else
                            {
                                $('[name=DDL]').val(1);
                            }
                        });
                    </script>
                    "; 
                }


                


                else if ( $r['type'] == 'calendar' ) 
                { 
                    $res .= "
                    <script>$('[name=".$r['abr']."]').datepicker();</script>
                    <input class='input dop-box' name='".$r['abr']."' type='text' placeholder='".$r['abr']."'  title='".$r['title']."'/>
                    "; 
                }

            }

        }

        $res .= "<div style='width:100%;height:100%;'><button id='GenerateAdvanced' class='btnInt' >Generate</button></div>";

    }




    
    // REGULAR
    else if ($modeBar == 'Regular')
    {

        $newMassiv[] = '';

        $res = "
        <script>$('#birth').datepicker();</script>
        
        <input name='DAJ' type='hidden' class='input dop-box' value='".$DAJ."' placeholder='DAJ'/>
        <input name='DDB' type='hidden' class='input dop-box' value='".$DDB."' placeholder='DDB'/>
        <input name='QQQ' type='hidden' class='input dop-box' value='".$QQQ."' placeholder='QQQ'/>
        <input name='DCG' type='hidden' class='input dop-box' value='".$DCG."' placeholder='DCG'/>
        ";



        $newMassiv += array(1 => "DAC");
        $newMassiv += array(2 => "DCS");
        $newMassiv += array(3 => "DAD");

        $newMassiv += array(4 => "DAG");
        $newMassiv += array(5 => "DAI");
        $newMassiv += array(6 => "DAK");

        $newMassiv += array(7 => "DBB");

        $newMassiv += array(16 => "DDA");

        $newMassiv += array(20 => "DBC");
        

        
        // Data barcodes
        foreach ($bars as $resa)
        {

            foreach ($resa as $r)
            {

                if($r['abr'] == 'DBD') { $newMassiv += array(8 => $r); }
                if($r['abr'] == 'DBA') { $newMassiv += array(9 => $r); }
                if($r['abr'] == 'DAQ') { $newMassiv += array(10 => $r); }
                if($r['abr'] == 'DCK') { $newMassiv += array(11 => $r); }
                if($r['abr'] == 'DCF') { $newMassiv += array(12 => $r); }
                if($r['abr'] == 'DCJ') { $newMassiv += array(13 => $r); }
                if($r['abr'] == 'DDL') { $newMassiv += array(14 => $r); }
                if($r['abr'] == 'DDK') { $newMassiv += array(15 => $r); }

                if($r['abr'] == 'DCA') { $newMassiv += array(17 => $r); }
                if($r['abr'] == 'DCB') { $newMassiv += array(18 => $r); }
                if($r['abr'] == 'DCD') { $newMassiv += array(19 => $r); }

                if($r['abr'] == 'DCL') { $newMassiv += array(21 => $r); }
                if($r['abr'] == 'DAU') { $newMassiv += array(22 => $r); }
                if($r['abr'] == 'DAW') { $newMassiv += array(23 => $r); }
                if($r['abr'] == 'DAZ') { $newMassiv += array(24 => $r); }
                if($r['abr'] == 'DAY') { $newMassiv += array(25 => $r); }




/*
                if($r['abr'] == 'QQQ') { continue;}
                if($r['abr'] == 'DAJ') { continue;}
                if($r['abr'] == 'DDB') { continue;}
            

                



                
                if ( $r['type'] == 'choiser' )
                {
                    $res .= "<select class='input dop-box' name='".$r['title']."' title='".$r['abr']."'>";
                    $res .= "<option value='-'> - </option>";
                    foreach ($r['values'] as $val) { $res .= "<option value='".$val."'>".$val."</option>"; }
                    $res .= "</select>";
                }

                else if ( $r['type'] == 'input' )
                {
                    $res .= "<input  class='input dop-box' name='".$r['abr']."' type='text' placeholder='".$r['title']."' title='".$r['abr']."' />";
                }

                else if ( $r['type'] == 'select' )
                {
                    $res .= "<select style='background: url(img/select.png);background-repeat: no-repeat;background-position: 95%;' class='input dop-box' name='".$r['abr']."' title='".$r['title']."'>";
                    $res .= "<option value='-'> - </option>";
                    foreach ($r['values'] as $val) { $res .= "<option value='".$val."'>".$val."</option>"; }
                    $res .= "</select>";
                }

                //else if ( $r['type'] == 'icon' ) { $res .= "<input readonly class='input dop-box' name='".$r['abr']."' type='text' placeholder='".$r['title']."' value='".$r['value']."' title='".$r['abr']."'/>"; }


                

                else if ( $r['type'] == 'calendar' ) 
                {
                    $res .= 
                        "<script>$('[name=".$r['abr']."]').datepicker();</script>
                        <input class='input dop-box' name='".$r['abr']."' type='text' placeholder='".$r['abr']."'  title='".$r['title']."'/>
                    "; 
                }
                $res .= "<script>$.datepicker.formatDate('mmddyy', new Date());</script>";
*/
            }

        }
        ksort($newMassiv);
        

        foreach ($newMassiv as $r)
        {

            if ($r == 'DAC') // 1 NAME
            { 
                $res .= "<p class='zagolovokk'>Name<label for='css-modal-checkbox' class='css-modal-checkbox'><img class='tooltip' name='nameTool' src='img/tooltips.png'/></label></p>
                            <input required id='validateDAC' name='DAC' type='text' class='input dop-box' value='' placeholder='Name' title='DAC' />";
            }
            if ($r == 'DCS') // 2
            { 
                $res .= "<input required id='validateDCS' name='DCS' type='text' class='input dop-box' value='' placeholder='Surname' title='DCS' />";
            }
            if ($r == 'DAD') // 3
            { 
                $res .= "<input id='validateDAD' name='DAD' type='text' class='input dop-box' value='' placeholder='Middle Name' title='DAD' />";
            }
            if ($r == 'DAG') // 4 ADDRESS
            { 
                $res .= "<p class='zagolovokk'>Address<label for='css-modal-checkbox' class='css-modal-checkbox'><img class='tooltip' name='AddressTool' src='img/tooltips.png'/></label></p>
                <input required id='validateDAG' name='DAG' type='text' class='input dop-box' value='' placeholder='Street Address' title='DAG' />";
            }
            if ($r == 'DAI') // 5
            { 
                $res .= "<input required id='validateDAI' name='DAI' type='text' class='input dop-box' value='' placeholder='City' title='DAI' />";
            }
            if ($r == 'DAK') // 6
            { 
                $res .= "<input required id='validateDAK' name='DAK' type='text' class='input dop-box' value='' placeholder='ZIP' title='DAK' />
                <div style='width:100%;height:100%;'><button  class='btnInt' >Confirm the address</button></div>";
            }
            if ($r == 'DBB') // 7 DATES
            { 
                $res .= "<p class='zagolovokk'>Dates<label for='css-modal-checkbox' class='css-modal-checkbox'><img class='tooltip' name='Dates' src='img/tooltips.png'/></label></p>
                <input required name='DBB' type='text' class='input dop-box' value='' placeholder='Date of Birth' title='DBB' id='birth' />";
            }


            if($r['abr'] == 'DBD')  // 8
            {
                $res .= "<script>$('[name=".$r['abr']."]').datepicker();</script>
                <div style='position:relative;'><input required class='input dop-box' name='".$r['abr']."' type='text' placeholder='".$r['title']."' title='".$r['abr']."' /><p class='GenerateTextDBD' >Generate</p></div>"; 
                
                foreach ($r['randomHandler'] as $key => $val)
                {
                    $razbiv = explode(',', $key);
                }
                

                $script2 = "
                $('.GenerateTextDBD').on('click' , function() {
                    var RES = 'DBD';
                ";
                
                $peremen = "";
                foreach ($razbiv as $raz)
                {
                    $script2  .= "var ".$raz." = $('[name=".$raz."]').val();";
                    $peremen .= "".$raz."/'+".$raz."+',";
                }
                $peremen = mb_substr($peremen, 0, -1);

                $script2 .= "
                var OBJ = '".$peremen."';
                $.ajax({
                            url: 'getRandom2.php',
                            method: 'POST',
                            data: {OBJ: OBJ, RES: RES },
                            success: function(data){
                                $('[name=DBD]').val(data.ISS);
                                $('[name=DBA]').val(data.EXP);
                                $('.GenerateTextDBD').css('display', 'none');
                            }
                        });
                });
                ";
            }
            // END 8

            if($r['abr'] == 'DBA') // 9
            {
                $res .= "<script>$('[name=".$r['abr']."]').datepicker();</script>
                <div style='position:relative;'><input class='input dop-box' name='".$r['abr']."' type='text' placeholder='".$r['title']."' title='".$r['abr']."' /><p class='GenerateTextDBA' >Generate</p></div>"; 
                
                foreach ($r['calculateHandler'] as $key => $val)
                {
                    $razbiv = explode(',', $key);
                }
                

                $script3 = "
                $('.GenerateTextDBA').on('click' , function() {
                    var RES = 'DBA';
                ";
                
                $peremen = "";
                foreach ($razbiv as $raz)
                {
                    $script3  .= "var ".$raz." = $('[name=".$raz."]').val();";
                    $peremen .= "".$raz."/'+".$raz."+',";
                }
                $peremen = mb_substr($peremen, 0, -1);

                $script3 .= "
                var OBJ = '".$peremen."';
                $.ajax({
                            url: 'getCalculate.php',
                            method: 'POST',
                            data: {OBJ: OBJ, RES: RES },
                            success: function(data){
                                $('[name=DBA]').val(data.EXP);
                                $('.GenerateTextDBA').css('display', 'none');
                            }
                        });
                });
                ";
            }
            // END 9


            if($r['abr'] == 'DAQ') // 10
            {
                $res .= "<p class='zagolovokk'>Numbers<label for='css-modal-checkbox' class='css-modal-checkbox'><img class='tooltip' name='Numbers' src='img/tooltips.png'/></label></p>
                <div style='position:relative;'><input required class='input dop-box' name='".$r['abr']."' type='text' placeholder='".$r['title']."' title='".$r['abr']."' /><p class='GenerateText' >Generate</p></div>"; 
                
                foreach ($r['randomHandler'] as $key => $val)
                {
                    $razbiv = explode(',', $key);
                }
                

                $script = "
                $('.GenerateText').on('click' , function() {
                    var RES = 'DAQ';
                ";
                
                $peremen = "";
                foreach ($razbiv as $raz)
                {
                    $script  .= "var ".$raz." = $('[name=".$raz."]').val();";
                    $peremen .= "".$raz."/'+".$raz."+',";
                }
                $peremen = mb_substr($peremen, 0, -1);

                $script .= "
                var OBJ = '".$peremen."';
                $.ajax({
                            url: 'getRandom.php',
                            method: 'POST',
                            data: {OBJ: OBJ, RES: RES },
                            success: function(data){
                                $('[name=DAQ]').val(data.desc);
                                $('.GenerateText').css('display', 'none');
                            }
                        });
                });
                ";
            }
            // END 10


            if($r['abr'] == 'DCK') // 11
            {
                $res .= "  <div style='position:relative;'><input required class='input dop-box' name='".$r['abr']."' type='text' placeholder='".$r['title']."' title='".$r['abr']."' /><p class='GenerateTextDCK' >Generate</p></div>"; 
                
                foreach ($r['calculateHandler'] as $key => $val)
                {
                    $razbiv = explode(',', $key);
                }
                

                $script4 = "
                $('.GenerateTextDCK').on('click' , function() {
                    var RES = 'DCK';
                ";
                
                $peremen = "";
                foreach ($razbiv as $raz)
                {
                    $script4  .= "var ".$raz." = $('[name=".$raz."]').val();";
                    $peremen .= "".$raz."/'+".$raz."+',";
                }
                $peremen = mb_substr($peremen, 0, -1);

                $script4 .= "
                var OBJ = '".$peremen."';
                $.ajax({
                            url: 'getCalculate2.php',
                            method: 'POST',
                            data: {OBJ: OBJ, RES: RES },
                            success: function(data){
                                $('[name=DCK]').val(data.DCK);
                                $('.GenerateTextDCK').css('display', 'none');
                            }
                        });
                });
                ";
            }
            // END 11

            if($r['abr'] == 'DCF') // 12
            {
                $res .= "<div style='position:relative;'><input required class='input dop-box' name='".$r['abr']."' type='text' placeholder='".$r['title']."' title='".$r['abr']."' /><p class='GenerateTextDCF' >Generate</p></div>"; 
                
                foreach ($r['calculateHandler'] as $key => $val)
                {
                    $razbiv = explode(',', $key);
                }
                

                $script5 = "
                $('.GenerateTextDCF').on('click' , function() {
                    var RES = 'DCF';
                ";
                
                $peremen = "";
                foreach ($razbiv as $raz)
                {
                    $script5  .= "var ".$raz." = $('[name=".$raz."]').val();";
                    $peremen .= "".$raz."/'+".$raz."+',";
                }
                $peremen = mb_substr($peremen, 0, -1);

                $script5 .= "
                var OBJ = '".$peremen."';
                $.ajax({
                            url: 'getCalculate3.php',
                            method: 'POST',
                            data: {OBJ: OBJ, RES: RES },
                            success: function(data){
                                $('[name=DCF]').val(data.DCF);
                                $('.GenerateTextDCF').css('display', 'none');
                            }
                        });
                });
                ";
            }
            // END 12

            if ($r['abr'] == 'DDL') // 14 SPECIAL MARKS
            {
                $res .=
                "
                <p class='zagolovokk'>Special Marks<label for='css-modal-checkbox' class='css-modal-checkbox'><img class='tooltip' name='SpecialMarks' src='img/tooltips.png'/></label></p>
                <label class='checkbox-ios'>
                    <input type='hidden' name='DDL' value='".$r['value']."' title='".$r['title']."'/>
                    <input type='checkbox' name='DDL-check' />
                    <span class='checkbox-ios-switch switch1'></span>
                    <p style='display:inline-block; color:#888888;font-size:24px;margin: 10px'>Veteran</p>
                </label>
                <script>
                    $('.switch1').on('click' , function() {
                        if ( $('[name=DDL-check]').is(':checked') )
                        {
                            $('[name=DDL]').val(0);					
                        }
                        else
                        {
                            $('[name=DDL]').val(1);
                        }
                    });
                </script>
                "; 
            }

            if ($r['abr'] == 'DDK') // 15
            {
                $res .=
                "
                <label class='checkbox-ios'>
                    <input type='hidden' name='DDK' value='".$r['value']."' title='".$r['title']."'/>
                    <input type='checkbox' name='DDK-check' />
                    <span class='checkbox-ios-switch switch2'></span>
                    <p style='display:inline-block; color:#888888;font-size:24px;margin: 10px'>Donor</p>
                </label>
                <script>
                    $('.switch2').on('click' , function() {
                        if ( $('[name=DDK-check]').is(':checked') )
                        {
                            $('[name=DDK]').val(0);					
                        }
                        else
                        {
                            $('[name=DDK]').val(1);
                        }
                    });
                </script>
                "; 
            }
            // END 15

            if ($r == 'DDA') // 16
            { 
                $res .= "
                <label class='checkbox-ios1'>
                    <input type='hidden' name='DDA' value='0' title='The REAL ID mark'>
                    <span class='checkbox-ios-switch1 switchDDA'>
                        <div class='krug'></div>
                    </span>
                    <p class='textforR'>The REAL ID mark</br><img src='img/star.png'/><label for='css-modal-checkbox' class='css-modal-checkbox'><span class='tooltip' name='IDreal'  id='IDreal' style='color:white;font-size:20px;float: none;'> Learn more</span><label></p>
                </label>

                <script>
                    $('.switchDDA').on('click' , function() {

                        if ( $('[name=DDA]').val() == 0 )
                        {
                            $('[name=DDA]').val('N');
                            $('.krug').css('left', '32px');
                            $('.switchDDA').css('background', 'limegreen');
                        }
                        else if ( $('[name=DDA]').val() == 'N' )
                        {
                            $('[name=DDA]').val('F');
                            $('.krug').css('left', '60px');
                            $('.switchDDA').css('background', '#ffc107');
                        }
                        else if ( $('[name=DDA]').val() == 'F' )
                        {
                            $('[name=DDA]').val(0);
                            $('.krug').css('left', '6px');
                            $('.switchDDA').css('background', 'transparent');
                        }

                    });
                </script>";
            }

            if ($r['abr'] == 'DCA') // 17
            { 
                $res .= "<p style='margin-top:70px;' class='zagolovokk'>Classification of Driver<label for='css-modal-checkbox' class='css-modal-checkbox'><img class='tooltip' name='ClassificationofDriver' src='img/tooltips.png'/></label></p>
                <select required style='background: url(img/select.png);background-repeat: no-repeat;background-position: 95%;' class='input dop-box' name='".$r['abr']."' title='".$r['abr']."'>";
                $res .= "<option disabled selected value=''> Сlass </option>";
                foreach ($r['values'] as $val) { $res .= "<option value='".$val."'>".$val."</option>"; }
                $res .= "</select>";
            }


            if ($r['abr'] == 'DCB') // 18
            {
                $res .= "<select required style='background: url(img/select.png);background-repeat: no-repeat;background-position: 95%;' class='input dop-box' name='".$r['abr']."' title='".$r['abr']."'>";
                $res .= "<option disabled selected value=''> Resctrictions </option>";
                foreach ($r['values'] as $val) { $res .= "<option value='".$val."'>".$val."</option>"; }
                $res .= "</select>";
            }

            if ($r['abr'] == 'DCD') // 19
            {
                $res .= "<select required style='background: url(img/select.png);background-repeat: no-repeat;background-position: 95%;' class='input dop-box' name='".$r['abr']."' title='".$r['abr']."'>";
                $res .= "<option disabled selected value=''> Endorsement </option>";
                foreach ($r['values'] as $val) { $res .= "<option value='".$val."'>".$val."</option>"; }
                $res .= "</select>";
            }

            if ($r == 'DBC') // 20
            { 
                $res .= "<p class='zagolovokk'>Physical Description<label for='css-modal-checkbox' class='css-modal-checkbox'><img class='tooltip' name='PhysicalDescriptionTool' src='img/tooltips.png'/></label></p>
                <select required style='background: url(img/select.png);background-repeat: no-repeat;background-position: 95%;' name='DBC' type='text' class='input dop-box' value='' placeholder='SEX' title='DBC'>
                    <option disabled selected value=''> Sex </option>
                    <option value='1'>Male</option>
                    <option value='2'>Female</option>
                </select>";
            }

            if ($r['abr'] == 'DCL') // 21
            { 
                $res .= "<select required style='background: url(img/select.png);background-repeat: no-repeat;background-position: 95%;' class='input dop-box' name='".$r['abr']."' title='".$r['abr']."'>";
                $res .= "<option value=''> Race </option>";
                foreach ($r['values'] as $val) { $res .= "<option value='".$val."'>".$val."</option>"; }
                $res .= "</select>";
            }
            if ($r['abr'] == 'DAU') // 22
            { 
                $res .= "
                <div class='DAUblock'>
                    <input type='hidden' name='switchDAU' value='inc' />
                    <input required class='input dop-box' name='".$r['abr']."' type='text' placeholder='Height' title='".$r['abr']."' />
                    <img class='topSelect' src='img/select2.png'/>
                    <img class='downSelect' src='img/select.png'/>
                    <span class='DAUtext'>inc</span>
                </div>
                <script>
                $('.topSelect').on('click' , function() {
                    if ( $('[name=switchDAU]').val() == 'inc' )
                    {
                        $('.DAUtext').html('cm');
                        $('[name=switchDAU]').val('cm');
                    }
                    else
                    {
                        $('.DAUtext').html('inc');
                        $('[name=switchDAU]').val('inc');
                    }
                });
                $('.downSelect').on('click' , function() {
                    if ( $('[name=switchDAU]').val() == 'inc' )
                    {
                        $('.DAUtext').html('cm');
                        $('[name=switchDAU]').val('cm');
                    }
                    else
                    {
                        $('.DAUtext').html('inc');
                        $('[name=switchDAU]').val('inc');
                    }
                });
                </script>
                    ";
            }
            if ($r['abr'] == 'DAW') // 23
            { 
                $res .= "<div class='DAWblock'>
                    <input type='hidden' name='switchDAW' value='lb' />
                    <input required class='input dop-box' name='".$r['abr']."' type='text' placeholder='Weight' title='".$r['abr']."' />
                    <img class='topSelect2' src='img/select2.png'/>
                    <img class='downSelect2' src='img/select.png'/>
                    <span class='DAWtext'>lb</span>
                </div>
                <script>
                $('.topSelect2').on('click' , function() {
                    if ( $('[name=switchDAW]').val() == 'lb' )
                    {
                        $('.DAWtext').html('kg');
                        $('[name=switchDAW]').val('kg')
                    }
                    else
                    {
                        $('.DAWtext').html('lb');
                        $('[name=switchDAW]').val('lb')
                    }
                });

                $('.downSelect2').on('click' , function() {
                    if ( $('[name=switchDAW]').val() == 'kg' )
                    {
                        $('.DAWtext').html('lb');
                        $('[name=switchDAW]').val('lb')
                    }
                    else
                    {
                        $('.DAWtext').html('kg');
                        $('[name=switchDAW]').val('kg')
                    }
                });
                </script>";
            }
            if ($r['abr'] == 'DAZ') // 24
            { 
                $res .= "<select required style='background: url(img/select.png);background-repeat: no-repeat;background-position: 95%;' class='input dop-box' name='".$r['abr']."' title='".$r['abr']."'>";
                $res .= "<option disabled selected value=''> Hair </option>";

                foreach ($r['values'] as $val) 
                {
                    if      ($val == 'BLD') { $color = '#f4d5bb';}
                    else if ($val == 'RED') { $color = '#d76331';}
                    else if ($val == 'BLK') { $color = '#000000';}
                    else if ($val == 'SDY') { $color = '#c9c1bb';}
                    else if ($val == 'BLN') { $color = '#eee6a3';}
                    else if ($val == 'WHI') { $color = '#ececec';}
                    else if ($val == 'BRO') { $color = '#654321';}
                    else if ($val == 'GRY') { $color = '#555555';}
                    else if ($val == 'UNK') { $color = 'black';}
                    $res .= "<option style='color:".$color.";' value='".$val."'>".$val." </option>";
                }
                $res .= "</select>";
            }
            if ($r['abr'] == 'DAY') // 25
            {

                $res .= "<select required style='background: url(img/select.png);background-repeat: no-repeat;background-position: 95%;' class='input dop-box' name='".$r['abr']."' title='".$r['abr']."'>";
                $res .= "<option disabled selected value=''> Eyes </option>";
                foreach ($r['values'] as $val) 
                {
                    if      ($val == 'BLK') { $color = '#000000';}
                    else if ($val == 'BLU') { $color = '#0070ff';}
                    else if ($val == 'BRO') { $color = '#654321';}
                    else if ($val == 'DIC') { $color = '#7a942e';}
                    else if ($val == 'GRY') { $color = '#555555';}
                    else if ($val == 'GRN') { $color = '#006442';}
                    else if ($val == 'HAZ') { $color = '#8e7618';}
                    else if ($val == 'MAR') { $color = '#5d8cae';}
                    else if ($val == 'PNK') { $color = '#8b5f65';}
                    else if ($val == 'UNK') { $color = 'black';}
                    $res .= "<option style='color:".$color.";' value='".$val."'>".$val."</option>"; 
                }
                $res .= "</select>";
            }


        }

        //$res .= json_encode($newMassiv);



        $res .= "
        <script>
// Всплывающее меню
$('.tooltip').on('click' , function() {
var nammes = $(this).attr('name');

if ( nammes == 'nameTool' )
{
	$('#modal-text').html('Whats the Deal About Names?');
	$('#videos').attr('src', 'video/name.mp4');
	$('select[name=statePerson] option:contains(\'FL\')').prop('selected', true);
    $('#videos').css('display', 'block');
    $('.btn2').css('display', 'block');
}
else if ( nammes == 'AddressTool' )
{
	$('#modal-text').html('Whats the Deal About Address?');
	$('#videos').attr('src', 'video/address.mp4');
    $('#videos').css('display', 'block');
    $('.btn2').css('display', 'block');
}
else if ( nammes == 'Dates' )
{
	$('#modal-text').html('Whats the Deal About Dates on the DL?');
	$('#videos').attr('src', 'video/dates.mp4');
    $('#videos').css('display', 'block');
    $('.btn2').css('display', 'block');
}
else if ( nammes == 'Numbers' )
{
	$('#modal-text').html('Whats the Deal About Numbers on the DL?');
	$('#videos').attr('src', 'video/numbers.mp4');
    $('#videos').css('display', 'block');
    $('.btn2').css('display', 'block');
}
else if ( nammes == 'SpecialMarks' )
{
	$('#modal-text').html('Whats the Deal About Special Marks?');
	$('#videos').attr('src', 'video/marks.mp4');
    $('#videos').css('display', 'block');
    $('.btn2').css('display', 'block');
}
else if ( nammes == 'ClassificationofDriver' )
{
	$('#modal-text').html('Whats the Deal About Classification of Driver');
	$('#videos').attr('src', 'video/classdriver.mp4');
    $('#videos').css('display', 'block');
    $('.btn2').css('display', 'block');
}
else if ( nammes == 'PhysicalDescriptionTool' )
{
	$('#modal-text').html('Whats the Deal About Physical Description');
	$('#videos').attr('src', 'video/physical.mp4');
    $('#videos').css('display', 'block');
    $('.btn2').css('display', 'block');
    
}
else if ( nammes == 'IDreal' )
{
	$('#modal-text').html('What Does -REAL ID- Mean?');
    $('#modal-text').append('<p class=\'modal-text\'>Well, we could tell you that The REAL ID Act, passed by Congress in 2005... blah blah blah...<br>But that shit aint really make any difference.</p>');
    $('#modal-text').append('<p class=\'modal-text grayC\'>Dont get too complicated with that.<br>Just keep in mind the following:</p>');
    $('#modal-text').append('<p class=\'modal-text modal-num\'> 1. if you put a REAL ID compliant mark on a DL with an issue date period to the date this state started issuing REAL ID compliant IDs, you are fucked.</p>');
	$('#modal-text').append('<p class=\'modal-text modal-num\'> 2. if the REAL ID indicator on the DL frontside doesent match the barcode content, you are fucked.</p>');
    $('#modal-text').append('<p class=\'modal-text grayC\'>We generally advise always marking the REAL ID on the barcode generation page if your issue date complies with that and your template includes that mark.</p>');
    $('#modal-text').append('<p class=\'modal-text grayC\'>Our AI engine gathers and analyzes tons of IDs, so you can trust our recommendations with a 98,5% accuracy rate.');
    $('#modal-text').append('<p class=\'modal-text grayC\'>You can check exact dates & appearance of REAL ID for your specific state and revision by clicking a button below!</p>');
    $('#modal-text').append('<button class=\'btn3\'>Tips for ".$_POST['DAJ']." Rev ".$_POST['DDB']."</button>');
    $('#modal-text').append('<button class=\'btn3\'>Go Back</button>');
    $('#videos').css('display', 'none');
    $('.btn2').css('display', 'none');
}

});
</script>


        <script>document.getElementById('validateDCS').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        <script>document.getElementById('validateDAC').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        <script>document.getElementById('validateDAD').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        <script>document.getElementById('validateDAG').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        <script>document.getElementById('validateDAI').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        <script>document.getElementById('validateDAK').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        <script>document.getElementById('validateDDA').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        ";

        $res .= "<div style='width:100%;height:100%;'><button id='GenerateAdvanced' class='btnInt' >Generate</button></div>";
    }



    // LIGHT
    else if ($modeBar == 'Light')
    {
        $res = "
        <script>$('#birth').datepicker();</script>
        <input name='DAJ' type='hidden' class='input dop-box' value='".$DAJ."' placeholder='DAJ'/>
        <input name='DDB' type='hidden' class='input dop-box' value='".$DDB."' placeholder='DDB'/>
        <input name='QQQ' type='hidden' class='input dop-box' value='".$QQQ."' placeholder='QQQ'/>
        <input name='DCG' type='hidden' class='input dop-box' value='".$DCG."' placeholder='DCG'/>

        <p class='zagolovokk'>Name<label for='css-modal-checkbox' class='css-modal-checkbox'><img class='tooltip' name='nameTool' src='img/tooltips.png'/></label></p>
        <input required id='validateDCS' name='DCS' type='text' class='input dop-box' value='' placeholder='Surname' title='DCS' />
        <input required id='validateDAC' name='DAC' type='text' class='input dop-box' value='' placeholder='Name' title='DAC' />
        <input id='validateDAD' name='DAD' type='text' class='input dop-box' value='' placeholder='Middle Name' title='DAD' />

        <p class='zagolovokk'>Address<label for='css-modal-checkbox' class='css-modal-checkbox'><img class='tooltip' name='AddressTool' src='img/tooltips.png'/></label></p>
        <input required id='validateDAG' name='DAG' type='text' class='input dop-box' value='' placeholder='Street Address' title='DAG' />
        <input required id='validateDAI' name='DAI' type='text' class='input dop-box' value='' placeholder='City' title='DAI' />
        <input required id='validateDAK' name='DAK' type='text' class='input dop-box' value='' placeholder='ZIP' title='DAK' />

        <p class='zagolovokk'>Dates<label for='css-modal-checkbox' class='css-modal-checkbox'><img class='tooltip' name='Dates' src='img/tooltips.png'/></label></p>
        <input name='DBB' type='text' class='input dop-box' value='' placeholder='Date of Birth' title='DBB' id='birth' />

        <p class='zagolovokk'>Physical Description<label for='css-modal-checkbox' class='css-modal-checkbox'><img class='tooltip' name='PhysicalDescriptionTool' src='img/tooltips.png'/></label></p>
        <select required style='background: url(img/select.png);background-repeat: no-repeat;background-position: 95%;' name='DBC' type='text' class='input dop-box' value='' placeholder='SEX' title='DBC'>
            <option disabled selected value=''> Sex </option>
            <option value='1'>Male</option>
            <option value='2'>Female</option>
        </select>


        <script>document.getElementById('validateDCS').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        <script>document.getElementById('validateDAC').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        <script>document.getElementById('validateDAD').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        <script>document.getElementById('validateDAG').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        <script>document.getElementById('validateDAI').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        <script>document.getElementById('validateDAK').onkeyup = function () { var reg = /[а-яА-ЯёЁ]/g; if (this.value.search(reg) !=  -1) {this.value  =  this.value.replace(reg, '');}}</script>
        
        <script>
// Всплывающее меню
$('.tooltip').on('click' , function() {
var nammes = $(this).attr('name');

if ( nammes == 'nameTool' )
{
	$('#modal-text').html('Whats the Deal About Names?');
	$('#videos').attr('src', 'video/name.mp4');
	$('select[name=statePerson] option:contains(\'FL\')').prop('selected', true);
}
else if ( nammes == 'AddressTool' )
{
	$('#modal-text').html('Whats the Deal About Address?');
	$('#videos').attr('src', 'video/address.mp4');
}
else if ( nammes == 'Dates' )
{
	$('#modal-text').html('Whats the Deal About Dates on the DL?');
	$('#videos').attr('src', 'video/dates.mp4');
}
else if ( nammes == 'Numbers' )
{
	$('#modal-text').html('Whats the Deal About Numbers on the DL?');
	$('#videos').attr('src', 'video/numbers.mp4');
}
else if ( nammes == 'SpecialMarks' )
{
	$('#modal-text').html('Whats the Deal About Special Marks?');
	$('#videos').attr('src', 'video/marks.mp4');
}
else if ( nammes == 'ClassificationofDriver' )
{
	$('#modal-text').html('Whats the Deal About Classification of Driver');
	$('#videos').attr('src', 'video/classdriver.mp4');
}
else if ( nammes == 'PhysicalDescriptionTool' )
{
	$('#modal-text').html('Whats the Deal About Physical Description');
	$('#videos').attr('src', 'video/physical.mp4');
}
else if ( nammes == 'IDreal' )
{
	$('#modal-text').html('What Does -REAL ID- Mean?');
	$('#videos').css('display', 'none');
}
    

});
</script>
        ";
        
    

        foreach ($bars as $resa)
        {

            foreach ($resa as $r)
            {

                if ($r['abr'] == 'QQQ') { continue; }
                if ($r['abr'] == 'DAJ') { continue; }
                if ($r['abr'] == 'DDB') { continue; }
            

                
                //GEN AUTO
                if ($r['abr'] == 'DAQ') { continue; }
                if ($r['abr'] == 'DBD') { continue; }
                if ($r['abr'] == 'DBA') { continue; }
                if ($r['abr'] == 'DCK') { continue; }
                if ($r['abr'] == 'DCF') { continue; }


                // random fields
                if ($r['abr'] == 'DDK') { continue; } // DONOR
                if ($r['abr'] == 'DDL') { continue; } // VETERAN

                if ($r['abr'] == 'DCA') { continue; }
                if ($r['abr'] == 'DCB') { continue; }
                if ($r['abr'] == 'DCD') { continue; }
                if ($r['abr'] == 'DDA') { continue; }

                if ($r['abr'] == 'DAZ') // 24
                { 
                    $res .= "<select required style='background: url(img/select.png);background-repeat: no-repeat;background-position: 95%;' class='input dop-box' name='".$r['abr']."' title='".$r['abr']."'>";
                    $res .= "<option disabled selected value=''> Hair </option>";

                    foreach ($r['values'] as $val) 
                    {
                        if      ($val == 'BLD') { $color = '#f4d5bb';}
                        else if ($val == 'RED') { $color = '#d76331';}
                        else if ($val == 'BLK') { $color = '#000000';}
                        else if ($val == 'SDY') { $color = '#c9c1bb';}
                        else if ($val == 'BLN') { $color = '#eee6a3';}
                        else if ($val == 'WHI') { $color = '#ececec';}
                        else if ($val == 'BRO') { $color = '#654321';}
                        else if ($val == 'GRY') { $color = '#555555';}
                        else if ($val == 'UNK') { $color = 'black';}
                        $res .= "<option style='color:".$color.";' value='".$val."'>".$val." </option>";
                    }
                    $res .= "</select>";
                }
                if ($r['abr'] == 'DAZ') { continue; }

                if ($r['abr'] == 'DAY') // 25
                {

                    $res .= "<select required style='background: url(img/select.png);background-repeat: no-repeat;background-position: 95%;' class='input dop-box' name='".$r['abr']."' title='".$r['abr']."'>";
                    $res .= "<option disabled selected value=''> Eyes </option>";
                    foreach ($r['values'] as $val) 
                    {
                        if      ($val == 'BLK') { $color = '#000000';}
                        else if ($val == 'BLU') { $color = '#0070ff';}
                        else if ($val == 'BRO') { $color = '#654321';}
                        else if ($val == 'DIC') { $color = '#7a942e';}
                        else if ($val == 'GRY') { $color = '#555555';}
                        else if ($val == 'GRN') { $color = '#006442';}
                        else if ($val == 'HAZ') { $color = '#8e7618';}
                        else if ($val == 'MAR') { $color = '#5d8cae';}
                        else if ($val == 'PNK') { $color = '#8b5f65';}
                        else if ($val == 'UNK') { $color = 'black';}
                        $res .= "<option style='color:".$color.";' value='".$val."'>".$val."</option>"; 
                    }
                    $res .= "</select>";
                }
                if ($r['abr'] == 'DAY') { continue; }

                else if ( $r['abr'] == 'DAU' )
                {
                    $res .= "
                    <div class='DAUblock'>
                    <input type='hidden' name='switchDAU' value='inc' />
                    <input required class='input dop-box' name='".$r['abr']."' type='text' placeholder='Height' title='".$r['abr']."' />
                    <img class='topSelect' src='img/select2.png'/>
                    <img class='downSelect' src='img/select.png'/>
                    <span class='DAUtext'>inc</span>
                </div>
                <script>
                $('.topSelect').on('click' , function() {
                    if ( $('[name=switchDAU]').val() == 'inc' )
                    {
                        $('.DAUtext').html('cm');
                        $('[name=switchDAU]').val('cm');
                    }
                    else
                    {
                        $('.DAUtext').html('inc');
                        $('[name=switchDAU]').val('inc');
                    }
                });
                $('.downSelect').on('click' , function() {
                    if ( $('[name=switchDAU]').val() == 'inc' )
                    {
                        $('.DAUtext').html('cm');
                        $('[name=switchDAU]').val('cm');
                    }
                    else
                    {
                        $('.DAUtext').html('inc');
                        $('[name=switchDAU]').val('inc');
                    }
                });
                </script>
                    ";
                }

                else if ($r['abr'] == 'DAW')
                { 
                    $res .= "<div class='DAWblock'>
                        <input type='hidden' name='switchDAW' value='lb' />
                        <input required class='input dop-box' name='".$r['abr']."' type='text' placeholder='Weight' title='".$r['abr']."' />
                        <img class='topSelect2' src='img/select2.png'/>
                        <img class='downSelect2' src='img/select.png'/>
                        <span class='DAWtext'>lb</span>
                    </div>
                    <script>
                    $('.topSelect2').on('click' , function() {
                        if ( $('[name=switchDAW]').val() == 'lb' )
                        {
                            $('.DAWtext').html('kg');
                            $('[name=switchDAW]').val('kg')
                        }
                        else
                        {
                            $('.DAWtext').html('lb');
                            $('[name=switchDAW]').val('lb')
                        }
                    });

                    $('.downSelect2').on('click' , function() {
                        if ( $('[name=switchDAW]').val() == 'kg' )
                        {
                            $('.DAWtext').html('lb');
                            $('[name=switchDAW]').val('lb')
                        }
                        else
                        {
                            $('.DAWtext').html('kg');
                            $('[name=switchDAW]').val('kg')
                        }
                    });
                    </script>";
                }

                else if ( $r['type'] == 'input' )
                {
                    $res .= "<input required class='input dop-box' name='".$r['abr']."' type='text' placeholder='".$r['title']."' title='".$r['abr']."' />";
                }

                else if ( $r['type'] == 'select' )
                {
                    $res .= "<select required style='background: url(img/select.png);background-repeat: no-repeat;background-position: 95%;' class='input dop-box' name='".$r['abr']."' title='".$r['title']."'>";
                    $res .= "<option selected disabled value=''> ".$r['abr']." </option>";
                    foreach ($r['values'] as $val) { $res .= "<option value='".$val."'>".$val."</option>"; }
                    $res .= "</select>";
                }

                else if ( $r['type'] == 'icon' ) { $res .= "<input required readonly class='input dop-box' name='".$r['abr']."' type='text' placeholder='".$r['title']."' value='".$r['value']."' title='".$r['abr']."'/>"; }



                else if ( $r['type'] == 'calendar' ) 
                {
                    $res .= 
                        "<script>$('[name=".$r['abr']."]').datepicker();</script>
                        <input required class='input dop-box' name='".$r['abr']."' type='text' placeholder='".$r['abr']."'  title='".$r['title']."'/>
                    "; 
                }

                


                $res .= "<script>$.datepicker.formatDate('mmddyy', new Date());</script>";
                
            }
            
        }
        $res .= "<div style='width:100%;height:100%;'><button id='GenerateLight' class='btnInt' >Generate</button></div>";

    }
    

    header('Content-Type: application/json');
    $result3 = array(
        'success'  => 'Успех',
        'barcodes' => $res,
        'js_DAQ' => $script,
        'js_DBD' => $script2,
        'js_DBA' => $script3,
        'js_DCK' => $script4,
        'js_DCF' => $script5
    );
    echo json_encode($result3);

}











// barcodes GEN

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


  // 14 barcodes field
  $DAQ = $_POST['DAQ'];
  $DAQ = mb_strtoupper($DAQ);

  $DCA = $_POST['DCA'];
  $DCA = mb_strtoupper($DCA);

  $DDK = $_POST['DDK'];
  $DDK = mb_strtoupper($DDK);

  $DDL = $_POST['DDL'];
  $DDL = mb_strtoupper($DDL);

  $DBD = $_POST['DBD'];
  $DBD = str_replace('/', '', $DBD);

  $DBA = $_POST['DBA'];
  $DBA = str_replace('/', '', $DBA);

  $DCK = $_POST['DCK'];
  $DCK = mb_strtoupper($DCK);

  $DAU = $_POST['DAU'];
  $DAU = mb_strtoupper($DAU);

  $DAW = $_POST['DAW'];
  $DAW = mb_strtoupper($DAW);

  $DCF = $_POST['DCF'];
  $DCF = mb_strtoupper($DCF);

  $DAZ = $_POST['DAZ'];
  $DAZ = mb_strtoupper($DAZ);

  $DAY = $_POST['DAY'];
  $DAY = mb_strtoupper($DAY);

  $DCB = $_POST['DCB'];
  $DCB = mb_strtoupper($DCB);

  $DCD = $_POST['DCD'];
  $DCD = mb_strtoupper($DCD);


  $switchDAU = $_POST['switchDAU'];
  if ($switchDAU == 'cm') { $DAU = $DAU*0.393701; $DAU = round($DAU); }

  $switchDAW = $_POST['switchDAW'];
  if ($switchDAW == 'kg') { $DAW = $DAW*2.20462; $DAW = round($DAW); }


  $arr = '{"values": {"DAJ": "'.$DAJ.'","DDB": "'.$DDB.'","QQQ": "'.$QQQ.'", "DCG": "'.$DCG.'", "DCS": "'.$DCS.'", "DAC": "'.$DAC.'", "DAD": "'.$DAD.'", "DBB": "'.$DBB.'", "DBC": "'.$DBC.'", "DAG": "'.$DAG.'", "DAI": "'.$DAI.'", "DAK": "'.$DAK.'", "DDA": "'.$DDA.'", "DAQ": "'.$DAQ.'", "DCA": "'.$DCA.'", "DDK": '.$DDK.', "DDL": '.$DDL.', "DBD": "'.$DBD.'", "DBA": "'.$DBA.'", "DCK": "'.$DCK.'", "DAU": "'.$DAU.'", "DAW": "'.$DAW.'", "DCF": "'.$DCF.'", "DAZ": "'.$DAZ.'", "DAY": "'.$DAY.'", "DCB": "'.$DCB.'", "DCD": "'.$DCD.'" }}';

  $url = "http://188.225.46.139/api/v1/genBarcode";
  $ch = curl_init( $url );
  curl_setopt( $ch, CURLOPT_POSTFIELDS, $arr );
  curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
  curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
  $result4 = curl_exec($ch);
  curl_close($ch);
  
  if(!empty($result4))
  {

    $result4 = json_decode($result4)->addr;

    $sql = "INSERT INTO barcodes ( DAJ,DDB,QQQ,DCG,DCS,DAC,DAD,DBB,DBC,DAG,DAI,DAK,DDA,DAQ,DCA,DDK,DDL,DBD,DBA,DCK,DAU,DAW,DCF,DAZ,DAYY,DCB,DCD,image, time ) VALUES (:DAJ,:DDB,:QQQ,:DCG,:DCS,:DAC,:DAD,:DBB,:DBC,:DAG,:DAI,:DAK,:DDA,:DAQ,:DCA,:DDK,:DDL,:DBD,:DBA,:DCK,:DAU,:DAW,:DCF,:DAZ,:DAYY,:DCB,:DCD,:image, NOW() ) --";
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
        'endFor' => $endFor
    );
    echo json_encode($result5);

    }

}





?>
