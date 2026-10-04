<?php

function userIsLoggedIn()
{
  if (isset($_POST['action']) and $_POST['action'] == 'login')
  {
    if (!isset($_POST['email']) or $_POST['email'] == '' or
      !isset($_POST['password']) or $_POST['password'] == '')
    {
      $GLOBALS['loginError'] = 'Ошибка авторизации: Заполните оба поля';
      return FALSE;
    }

    
	
	$password = hash('sha256', $_POST['password']);
	
    if (databaseContainsAdmin($_POST['email'], $password) && $_COOKIE['counter'] < 3)
    {
      session_start();
      $_SESSION['loggedIn'] = TRUE;
      $_SESSION['email'] = $_POST['email'];
      $_SESSION['password'] = $password;
      return TRUE;
    }
    else
    {
		
		$count = 0;
		if (isset($_COOKIE['counter'])) $count = $_COOKIE['counter']; $count++;
		setcookie("counter",$count, time()+60);
				
		if($_COOKIE['counter']>2)
		{
			session_start();
			unset($_SESSION['loggedIn']);
			unset($_SESSION['email']);
			unset($_SESSION['password']);
			$GLOBALS['loginError'] = 'Превышено количество попыток! Повторите запрос через несколько минут!';
			
			return FALSE;
		} 
		else 
		{
			session_start();
			unset($_SESSION['loggedIn']);
			unset($_SESSION['email']);
			unset($_SESSION['password']);
			$GLOBALS['loginError'] = 'Ошибка: Указанный логин или пароль неверный';
			
			return FALSE;
		}
	

    }
  }

  if (isset($_POST['action']) and $_POST['action'] == 'logout')
  {
    session_start();
    unset($_SESSION['loggedIn']);
    unset($_SESSION['email']);
    unset($_SESSION['password']);
    header('Location: index.php');
    exit();
  }

  session_start();
  if (isset($_SESSION['loggedIn']))
  {
    return databaseContainsAdmin($_SESSION['email'], $_SESSION['password']);
  }
}

function databaseContainsAdmin($email, $password)
{
  include 'db.inc.php';

  try
  {
    $sql = 'SELECT COUNT(*) FROM users
        WHERE email = :email AND password = :password';
    $s = $pdo->prepare($sql);
    $s->bindValue(':email', $email);
    $s->bindValue(':password', $password);
    $s->execute();
  }
  catch (PDOException $e)
  {
    $error = 'Ошибка: Администратор не найден';
    include 'error.html.php';
    exit();
  }

  $row = $s->fetch();

  if ($row[0] > 0)
  {
    return TRUE;
  }
  else
  {
    return FALSE;
  }
}

function userHasRole($role)
{
  include 'db.inc.php';

  try
  {
    $sql = "SELECT COUNT(*) FROM users
        INNER JOIN usersrole ON users.id = usersid
        INNER JOIN role ON roleid = role.id
        WHERE email = :email AND role.id = :roleId";
    $s = $pdo->prepare($sql);
    $s->bindValue(':email', $_SESSION['email']);
    $s->bindValue(':roleId', $role);
    $s->execute();
  }
  catch (PDOException $e)
  {
    $error = 'Ошибка: Привелегии не найдены';
    include 'error.html.php';
    exit();
  }

  $row = $s->fetch();

  if ($row[0] > 0)
  {
    return TRUE;
  }
  else
  {
    return FALSE;
  }
}
