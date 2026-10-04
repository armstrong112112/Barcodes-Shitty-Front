<?php
/*
include_once '/var/www/html/includes/db.inc.php';



$sql = "SELECT * FROM state where country = :country AND status = :status --";
$statement = $pdo->prepare($sql);
$statement->bindValue(':country', $country);
$statement->bindValue(':status', 'start');
$statement->execute();
$states = $statement->fetchAll();
*/

$massiv[] = array(1 => "DCQ", 2 => "DBD+DBA", 3 => "DCK", 4 => "DCF", 5 => "DAQ");

echo "<pre>";

//print_r($massiv);

foreach ($massiv as $m)
{
    //print_r($m);

    //if (in_array("DBD+DBA", $m)) { echo 'Найдено значение DCQ';}       
    //echo $mas."<br>";

}

//print_r($m);
?>