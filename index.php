<?php
require_once 'vendor/autoload.php';

$self = "http://{$_SERVER['HTTP_HOST']}/oauth-youtube-google/index.php";
$success = "$self?mode=success";
$secretsJson = 'client_secret.json';

$p = $_GET;
$mode = @$p['mode'];

$client = new Google_Client();
$_SESSION['client'] = $client;
$client->setAuthConfig($secretsJson);
$client->setScopes("https://www.googleapis.com/auth/plus.profile.emails.read");
$client->addScope('https://www.googleapis.com/auth/youtube');
$client->addScope(Google_Service_Plus::USERINFO_PROFILE);
$client->setAccessType('offline');
$client->setApprovalPrompt('force');
$client->setRedirectUri($success);
$authUrl = $client->createAuthUrl();
echo "<p><a href='$authUrl'>Auth</a></p>";
echo "<pre>";

session_start();

if ($mode == 'clear') {
 $_SESSION['accessToken'] = '';
}
elseif ($mode == 'success') {
 echo "<p>SUCCESS: {$p['code']}</p>";
 $client->authenticate($p['code']);
 $accessToken = $client->getAccessToken();
 if ($accessToken) { $_SESSION['accessToken'] = $accessToken; }
}

if (@$_SESSION['accessToken']) {
 // アクセストークンを出力
 var_export($_SESSION['accessToken']);
 $client->setAccessToken($_SESSION['accessToken']);
 $plus = new Google_Service_Plus($client);
 $me = $plus->people->get('me');
 echo "<p><img src='{$me['image']['url']}'></p>";
 echo "<p>NAME: {$me['displayName']}</p>";
 echo "<p>MAIL: {$me->emails[0]->value}</p>";
 echo "<p>GENDER: {$me['gender']}</p>";
 echo "<p>URL: {$me['url']}</p>";
}

echo "</pre>";

$youtube = new Google_Service_YouTube($client);


if (isset($_SESSION['accessToken'])) {
  $client->setAccessToken($_SESSION['accessToken']);
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
    $res_con=0;
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
        <input type="submit" name=%s value="登録">
        <li>%s (%s)</li>
        </form>', $count , $playlistItem['snippet']['title'],
          $playlistItem['snippet']['resourceId']['videoId']);
        $htmlBody .= sprintf('<li><iframe width="560" height="315"
                  src="https://www.youtube.com/embed/%s"
                  frameborder="0" allow="autoplay; encrypted-media"
                  allowfullscreen></iframe></li>',
                  $playlistItem['snippet']['resourceId']['videoId']);
        $mvid=$playlistItem['snippet']['resourceId']['videoId'];
        $res_con = $count;
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

  $_SESSION['accessToken'] = $client->getAccessToken();
}

 ?>
 <!doctype html>
<html>
  <head>
    <title>My Uploads</title>
  </head>
  <body>
    <p>-------YouTube List--------</p>
    <?=$htmlBody;
    if(isset($_POST["tag"])){
      if(isset($_POST[$res_con])){
        $mvid=$mvid;
        $tag=$_POST["tag"];
      }
      $pdo = new PDO( "mysql:dbname=youtube_db;host=localhost;charset=utf8mb4","root", "");
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
    <p>-------Select List--------</p>

  </body>
</html>
