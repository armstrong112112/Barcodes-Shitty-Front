<?php include_once $_SERVER['DOCUMENT_ROOT'] .
    '/includes/helpers.inc.php'; ?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
	<link rel="stylesheet" href="../../../css/admin.css">
    <title>Доступ запрещен!</title>
  </head>
  <body style="background: url(../../img/back-main.png);">
  <a href="../" class="back"></a>
    <h1 style="text-align:center;color:white;">Доступ запрещен!</h1>
    <p style="text-align:center;color:white;"><?php htmlout($error); ?></p>
  </body>
</html>
