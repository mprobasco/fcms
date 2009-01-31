<?php
session_start();
if (get_magic_quotes_gpc()) {
	$_REQUEST = array_map('stripslashes', $_REQUEST);
	$_GET = array_map('stripslashes', $_GET);
	$_POST = array_map('stripslashes', $_POST);
	$_COOKIE = array_map('stripslashes', $_COOKIE);
}
include_once('inc/config_inc.php');
include_once('inc/util_inc.php');
include_once('inc/language.php');
if (isset($_SESSION['login_id'])) {
	if (!isLoggedIn($_SESSION['login_id'], $_SESSION['login_uname'], $_SESSION['login_pw'])) {
		displayLoginPage();
		exit();
	}
} elseif (isset($_COOKIE['fcms_login_id'])) {
	if (isLoggedIn($_COOKIE['fcms_login_id'], $_COOKIE['fcms_login_uname'], $_COOKIE['fcms_login_pw'])) {
		$_SESSION['login_id'] = $_COOKIE['fcms_login_id'];
		$_SESSION['login_uname'] = $_COOKIE['fcms_login_uname'];
		$_SESSION['login_pw'] = $_COOKIE['fcms_login_pw'];
	} else {
		displayLoginPage();
		exit();
	}
} else {
	displayLoginPage();
	exit();
}
header("Cache-control: private");
include_once('inc/messageboard_class.php');
$mboard = new MessageBoard($_SESSION['login_id'], 'mysql', $cfg_mysql_host, $cfg_mysql_db, $cfg_mysql_user, $cfg_mysql_pass);
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php echo $LANG['lang']; ?>" lang="<?php echo $LANG['lang']; ?>">
<head>
<title><?php echo getSiteName() . " - " . $LANG['poweredby'] . " " . getCurrentVersion(); ?></title>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="author" content="Ryan Haudenschilt" />
<link rel="stylesheet" type="text/css" href="<?php getTheme($_SESSION['login_id']); ?>" />
<link rel="shortcut icon" href="themes/images/favicon.ico"/>
</head>
<body id="body-messageboard">
	<div><a name="top"></a></div>
	<div id="header"><?php echo "<h1 id=\"logo\">" . getSiteName() . "</h1><p>".$LANG['welcome']." <a href=\"profile.php?member=".$_SESSION['login_id']."\">"; echo getUserDisplayName($_SESSION['login_id']); echo "</a> | <a href=\"settings.php\">".$LANG['link_settings']."</a> | <a href=\"logout.php\" title=\"".$LANG['link_logout']."\">".$LANG['link_logout']."</a></p>"; ?></div>
	<?php displayTopNav(); ?>
	<div id="pagetitle"><?php echo $LANG['link_board']; ?></div>
	<div id="leftcolumn">
		<h2><?php echo $LANG['navigation']; ?></h2>
		<?php
		displaySideNav();
		if(checkAccess($_SESSION['login_id']) < 3) { 
			echo "\t<h2>".$LANG['admin']."</h2>\n\t"; 
			displayAdminNav("fix");
		} ?></div>
	<div id="content">
		<div id="messageboard" class="centercontent">
			<?php
			$show_threads = true;
			if (isset($_POST['post_submit'])) {
				$show_threads = false;
				$subject = addslashes($_POST['subject']);
				if (empty($subject)) { $subject = "subject"; }
				if (isset($_POST['sticky'])) { $subject = "#ANOUNCE#" . $subject; }
				if (isset($_POST['username'])) { $username = $_POST['username']; }
				$post = addslashes($_POST['post']);
				$sql = "INSERT INTO `fcms_board_threads`(`subject`, `started_by`, `updated`, `updated_by`) VALUES ('$subject', " . $_SESSION['login_id'] . ", NOW(), " . $_SESSION['login_id'] . ")";
				mysql_query($sql) or displaySQLError('New Thread Error', 'messageboard.php [' . __LINE__ . ']', $sql, mysql_error());
				$new_thread_id = mysql_insert_id();
				$sql = "INSERT INTO `fcms_board_posts`(`date`, `thread`, `user`, `post`) VALUES (NOW(), $new_thread_id, " . $_SESSION['login_id'] . ", '$post')";
				mysql_query($sql) or displaySQLError('New Post Error', 'messageboard.php [' . __LINE__ . ']', $sql, mysql_error());
				echo "<meta http-equiv='refresh' content='0;URL=messageboard.php?thread=" . $new_thread_id . "'>";
			}
			if (isset($_POST['reply_submit'])) {
				$show_threads = false;
				$post = addslashes($_POST['post']);
				$thread_id = $_POST['thread_id'];
				$sql = "UPDATE `fcms_board_threads` SET `updated` = NOW(), `updated_by` = " . $_SESSION['login_id'] . " WHERE `id` = $thread_id";
				mysql_query($sql) or displaySQLError('Update Thread Error', 'messageboard.php [' . __LINE__ . ']', $sql, mysql_error());
				$sql = "INSERT INTO `fcms_board_posts`(`date`, `thread`, `user`, `post`) VALUES (NOW(), $thread_id, " . $_SESSION['login_id'] . ", '$post')";
				mysql_query($sql) or displaySQLError('Reply Error', 'messageboard.php [' . __LINE__ . ']', $sql, mysql_error());
				echo "<meta http-equiv='refresh' content='0;URL=messageboard.php?thread=" . $thread_id . "'>";
			}
			if (isset($_POST['edit_submit'])) {
				$show_threads = false;
				$id = $_POST['id'];
				$thread_id = $_POST['thread_id'];
				$post = addslashes($_POST['post']);
				$pos = strpos($post, "[size=small][i]".$LANG['edited']);
				if($pos === false) {
					$post = $post . "\n\n[size=small][i]".$LANG['edited']." " . fixDST(gmdate('n/d/Y g:ia', strtotime($mboard->tz_offset)), $_SESSION['login_id'], 'n/d/Y g:ia') . "[/i][/size]";
				} else {
					$post = substr($post, 0, $pos);
					$post = $post . "[size=small][i]".$LANG['edited']." " . fixDST(gmdate('n/d/Y g:ia', strtotime($mboard->tz_offset)), $_SESSION['login_id'], 'n/d/Y g:ia') . "[/i][/size]";
				}
				$sql = "UPDATE `fcms_board_posts` SET `post` = '$post' WHERE `id` = $id";
				mysql_query($sql) or displaySQLError('Edit Post Error', 'messageboard.php [' . __LINE__ . ']', $sql, mysql_error());
				echo "<meta http-equiv='refresh' content='0;URL=messageboard.php?thread=" . $thread_id . "'>";
			}
			if (isset($_POST['delpost'])) {
				$show_threads = false;
				$id = $_POST['id'];
				$thread = $_POST['thread'];
				$sql = "SELECT MAX(`id`) AS max FROM `fcms_board_posts` WHERE `thread` = $thread";
				$result = mysql_query($sql) or displaySQLError('Last Thread Error', 'messageboard.php [' . __LINE__ . ']', $sql, mysql_error());
				$found = mysql_fetch_array($result);
				$max = $found['max'];
				mysql_free_result($result);
				$sql = "SELECT * FROM `fcms_board_posts` WHERE `thread` = $thread";
				$result = mysql_query($sql) or displaySQLError('Post Count Error', 'messageboard.php [' . __LINE__ . ']', $sql, mysql_error());
				$total = mysql_num_rows($result);
				mysql_free_result($result);
				if($total < 2) {
					$sql = "DELETE FROM `fcms_board_threads` WHERE `id` = $thread";
					mysql_query($sql) or displaySQLError('Delete Thread Error', 'messageboard.php [' . __LINE__ . ']', $sql, mysql_error());
					echo "<meta http-equiv='refresh' content='0;URL=messageboard.php'>";
				} elseif($id == $max) {
					$sql = "DELETE FROM `fcms_board_posts` WHERE `id` = $id";
					mysql_query($sql) or displaySQLError('Delete Post Error', 'messageboard.php [' . __LINE__ . ']', $sql, mysql_error());
					$sql = "SELECT MAX(`id`) AS max FROM `fcms_board_posts` WHERE `thread` = $thread";
					$result = mysql_query($sql) or displaySQLError('Last Post Error', 'messageboard.php [' . __LINE__ . ']', $sql, mysql_error());
					$found = mysql_fetch_array($result);
					$newmax = $found['max'];
					mysql_free_result($result);
					$sql = "SELECT `date`, `user` FROM `fcms_board_posts` WHERE `id` = $newmax";
					$result = mysql_query($sql) or displaySQLError('Last Post Info Error', 'messageboard.php [' . __LINE__ . ']', $sql, mysql_error());
					$e = mysql_fetch_array($result);
					$sql = "UPDATE `fcms_board_threads` SET `updated` = '" . $e['date'] . "', `updated_by` = " . $e['user'] . " WHERE `id` = $thread";
					mysql_query($sql) or displaySQLError('Update Thread Error', 'messageboard.php [' . __LINE__ . ']', $sql, mysql_error());
					echo "<meta http-equiv='refresh' content='0;URL=messageboard.php?thread=" . $thread . "'>";
				} else {
					$sql = "DELETE FROM fcms_board_posts WHERE id=$id";
					mysql_query($sql) or displaySQLError('Delete Post Error', 'messageboard.php [' . __LINE__ . ']', $sql, mysql_error());
					echo "<meta http-equiv='refresh' content='0;URL=messageboard.php?thread=" . $thread . "'>";
				}
			}
			if (isset($_POST['editpost'])) {
				$show_threads = false;
				$id = $_POST['id'];
				$sql = "SELECT * FROM `fcms_board_posts` WHERE `id` = $id";
				$result = mysql_query($sql) or displaySQLError('Get Post Error', 'messageboard.php [' . __LINE__ . ']', $sql, mysql_error());
				while($r=mysql_fetch_array($result)) {
					$post = $r['post'];
					$thread_id = $r['thread'];
				}
				$mboard->displayForm('edit', $thread_id, $id, $post);
			}
			if (isset($_GET['thread'])) {
				$show_threads = false;
				$page = 1;
				if (isset($_GET['page'])) { $page = $_GET['page']; }
				$thread_id = $_GET['thread'];
				$mboard->showPosts($thread_id, $page);
			}
			if (isset($_GET['reply'])) {
				if (checkAccess($_SESSION['login_id']) < 8 && checkAccess($_SESSION['login_id']) != 5) {
					$show_threads = false;
					if ($_GET['reply'] == 'new') {
						$mboard->displayForm('new');
					} elseif ($_GET['reply'] > 0) {
						if (isset($_POST['quote'])) { $mboard->displayForm('reply', $_GET['reply'], 0, $_POST['quote']); } else { $mboard->displayForm('reply', $_GET['reply']); }
					}
				}
			}
			if ($show_threads) {
				$page = 1;
				if (isset($_GET['page'])) { $page = $_GET['page']; }
				$mboard->showThreads('announcement');
				$mboard->showThreads('thread', $page);
			} ?>
		</div><!-- #messageboard .centercontent -->
	</div><!-- #content -->
	<?php displayFooter(); ?>
</body>
</html>