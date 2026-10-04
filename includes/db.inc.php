<?php
try
{
$pdo = new PDO('mysql:host=localhost;dbname=barcodes', 'barcodde', 'Zds8kh3Cs!' ) ;
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('SET NAMES "utf8"');
}
catch (PDOException $e)
{
$error = 'He удалось подключиться к серверу баз данных.';
exit();
}
