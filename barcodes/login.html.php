<?php include_once $_SERVER['DOCUMENT_ROOT'] .
    '/includes/helpers.inc.php'; ?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход</title>
	<link rel="stylesheet" href="../../css/admin.css">
	<style>
.divchik {margin-top:10%;}
@media screen and (max-width: 600px) {
	.heading h1, .heading h2 {font-size:30px;margin-top:35px;}
	.login {width:80%;}
	.right-input-text input[type="text"], .search input {font-size:16px;}
	.right-input-text input[type="text"], .right-kompl , .right-input-text , .right-input-text , .search input, .right-input-text input[type="password"] {font-size:16px;}
}
  </style>
  </head>
  <body class="main">
	
		<div class="heading divchik">
			<h1>Панель администратора</h1>
			<h1>Вход</h1>
		</div>
	<div class="main">
		<div class="manual-text  login">
		<div class="manual-line" id="p-enter"><p>Введите данные для входа:</p></div>
		<?php if (isset($loginError)): ?>
		  <p><?php htmlout($loginError); ?></p>
		<?php endif; ?>
		<form action="" method="post" autocomplete="off">
		  <div class="manual-line">
			<div class="left no-white-border">
				<p>Логин:</p>
			</div>
			<div class="right">
				<div class="right-input-text">
					<label for="email"><input placeholder="Введите логин..." type="text" name="email" id="email"></label>
				</div>
			</div>
		  </div>
		  
			<div class="manual-line">
				<div class="left no-white-border">
					<p>Пароль:</p>
				</div>
				<div class="right">
					<div class="right-input-text">
						<label for="password"><input placeholder="Введите пароль..." type="password" name="password" id="password"></label>
					</div>
				</div>
			</div>
			
			<div class="manual-line">
			  <div >
				<input type="hidden" name="action" value="login">
				<input class="vhod" type="submit" value="Войти">
			  </div>
		  </div>
		</form>
		</div>
		<a class="back" href="/qw/"></a>
	</div>
  </body>
</html>
