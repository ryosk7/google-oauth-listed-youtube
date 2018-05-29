<?php

// Call set_include_path() as needed to point to your client library.
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = new Dotenv\Dotenv(__DIR__);
$dotenv->load();
session_start();

/*
 * You can acquire an OAuth 2.0 client ID and client secret from the
 * Google Developers Console <https://console.developers.google.com/>
 * For more information about using OAuth 2.0 to access Google APIs, please see:
 * <https://developers.google.com/youtube/v3/guides/authentication>
 * Please ensure that you have enabled the YouTube Data API for your project.
 */
$OAUTH2_CLIENT_ID = $_ENV['CLIENT_ID'];
$OAUTH2_CLIENT_SECRET = $_ENV['CLIENT_SECRET'];

$client = new Google_Client();
$client->setClientId($OAUTH2_CLIENT_ID);
$client->setClientSecret($OAUTH2_CLIENT_SECRET);
$client->setScopes('https://www.googleapis.com/auth/youtube');
$redirect = filter_var('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'],
  FILTER_SANITIZE_URL);
$client->setRedirectUri($redirect);

// Define an object that will be used to make all API requests.
$youtube = new Google_Service_YouTube($client);

if (isset($_GET['code'])) {
  if (strval($_SESSION['state']) !== strval($_GET['state'])) {
    die('The session state did not match.');
  }

  $client->authenticate($_GET['code']);
  $_SESSION['token'] = $client->getAccessToken();
  header('Location: ' . $redirect);
}

if (isset($_SESSION['token'])) {
  $client->setAccessToken($_SESSION['token']);
}

// Check to ensure that the access token was successfully acquired.
if ($client->getAccessToken()) {
  try {
    // Call the channels.list method to retrieve information about the
    // currently authenticated user's channel.
    $channelsResponse = $youtube->channels->listChannels('contentDetails', array(
      'mine' => 'true',
    ));

    $htmlBody = '';
    $count=0;
    foreach ($channelsResponse['items'] as $channel) {
      // Extract the unique playlist ID that identifies the list of videos
      // uploaded to the channel, and then call the playlistItems.list method
      // to retrieve that list.
      $uploadsListId = $channel['contentDetails']['relatedPlaylists']['uploads'];

      $playlistItemsResponse = $youtube->playlistItems->listPlaylistItems('snippet', array(
        'playlistId' => $uploadsListId,
        'maxResults' => 50
      ));

      $htmlBody .= "<h3>Videos in list $uploadsListId</h3><ul>";
      foreach ($playlistItemsResponse['items'] as $playlistItem) {
        $htmlBody .= sprintf('
        <form method="post">ジャンル選んでね<br>
        PV<input type="checkbox" name="tag" value=1>
        you tuber<input type="checkbox" name="tag" value=2>
        GAME<input type="checkbox" name="tag" value=3>
        <input type="submit" name=send%s value="登録">
        <li>%s (%s)</li>
        </form>', $count , $playlistItem['snippet']['title'],
          $playlistItem['snippet']['resourceId']['videoId']);
        $htmlBody .= sprintf('<li><iframe width="560" height="315"
                  src="https://www.youtube.com/embed/%s"
                  frameborder="0" allow="autoplay; encrypted-media"
                  allowfullscreen></iframe></li>',
                  $playlistItem['snippet']['resourceId']['videoId']);
        $mvid[$count]=$playlistItem['snippet']['resourceId']['videoId'];
        $count=$count+1;
      }
      $htmlBody .= '</ul>';
    }
  } catch (Google_Service_Exception $e) {
    $htmlBody .= sprintf('<p>A service error occurred: <code>%s</code></p>',
      htmlspecialchars($e->getMessage()));
  } catch (Google_Exception $e) {
    $htmlBody .= sprintf('<p>An client error occurred: <code>%s</code></p>',
      htmlspecialchars($e->getMessage()));
  }

  $_SESSION['token'] = $client->getAccessToken();
} else {
  $state = mt_rand();
  $client->setState($state);
  $_SESSION['state'] = $state;

  $authUrl = $client->createAuthUrl();
  $htmlBody = <<<END
  <h3>Authorization Required</h3>
  <p>You need to <a href="$authUrl">authorize access</a> before proceeding.<p>
END;
}
?>

<!doctype html>
<html>
  <head>
    <title>My Uploads</title>
  </head>
  <body>
    <?=$htmlBody;
    if(isset($_POST["tag"])){
      if(isset($_POST["send0"])){
        $mvid=$mvid[0];
        $tag=$_POST["tag"];
      }
      $pdo = new PDO( "mysql:dbname=mvlist;host=localhost;charset=utf8mb4","root", "");
    if(!$pdo){echo "接続失敗";}

    $sent = $pdo -> prepare("INSERT INTO movie(plid , mvid , tag)values(?,?,?)");

      $sent -> bindParam("plid",$uploadsListId);
      $sent -> bindParam("mvid",$mvid);
      $sent -> bindParam("tag",$tag);

    $sent -> execute(array($uploadsListId,$mvid,$tag));

    if(!$sent){echo "ERROR";}
    else{echo "登録完了";}
}else{echo "タグ付けなし！";}
    ?>
  </body>
</html>
