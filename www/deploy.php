<?php
error_reporting(E_ERROR);

const DEPLOY_LOG = "/var/www/deploy-log";

function is_building($safe = true) {
  $online = shell_exec("sudo pm2 ls | grep \"online\" | awk \"{print $4}\"");
  if ($online === false || $online === null) {
    if ($safe) {
      return false;
    }
    throw new Exception("Cannot detect building state.");
  }
  return str_contains($online, "ansible");
}

if ($_GET["req"] === "ping") {
  header("Content-Type: application/json; charset=UTF-8", true);

  try {
    if (is_building(false)) {
      $obj = array(
        "code" => 1,
        "log" => shell_exec("[ -r \"" . DEPLOY_LOG . "\" ] && cat \"" . DEPLOY_LOG . "\"")
      );
    } else {
      $obj = array(
        "code" => 0,
        "log" => file_exists(DEPLOY_LOG) ?
          fread(fopen(DEPLOY_LOG, "r"), filesize(DEPLOY_LOG)) :
          "Never run."
      );
    }
  } catch (Exception $e) {
    $obj = array(
      "code" => -1,
      "log" => $e->getMessage()
    );
  }

  echo json_encode($obj);
} elseif ($_GET["req"] === "start") {
  header("Content-Type: text/plain; charset=UTF-8", true);

  if (is_building()) {
    echo "A build process is still running.";
  } else {
    shell_exec("sudo pm2 delete ansible; sudo unlink \"" . DEPLOY_LOG . "\"");
    exec(
      "sudo pm2 start \"/home/ubuntu/sd-ec2/scripts/playbook.sh\" --output \"" . DEPLOY_LOG . "\" --name ansible --no-autorestart 2>&1",
      $log,
      $started
    );
    echo $started === 0 ?
      "Started the build process. Refresh the page when it's done to see new commit hash." :
      ("Could not start the build process.\n" . join("\n", $log));
  }
} else {
  header("Content-Type: text/html; charset=UTF-8", true);
?>
<!doctype html>

<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>EC2 Deployment: Sport Dashboard</title>
  <meta name="description" content="A deployment portal for Sports Dashboard.">
  <meta name="author" content="Quyen Dinh from Wanchester">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300&display=swap" rel="stylesheet">

  <style>
    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      min-width: 480px;
      font-size: 16px;
      font-family: 'Roboto', sans-serif;
      flex-direction: column;
    }

    .f {
      width: 480px;
      margin-bottom: 16px;
      padding: 12px 20px;
    }

    @media (min-width: 576px) {
      .f {
        width: 576px;
      }
    }

    @media (min-width: 768px) {
      .f {
        width: 768px;
      }
    }

    @media (min-width: 992px) {
      .f {
        width: 992px;
      }
    }

    @media (min-width: 1200px) {
      .f {
        width: 1200px;
      }
    }

    .l {
      font-weight: bold;
      padding: 8px;
      user-select: none;
    }

    .f table {
      margin: 0 auto;
      border-collapse: collapse;
    }

    .b {
      padding-right: 8px;
      text-align: right;
    }

    .h {
      padding-left: 8px;
      font-weight: bold;
      font-family: monospace;
    }

    .s {
      display: block;
      width: 100%;
      text-align: center;
      user-select: none;
      margin-bottom: 8px;
    }

    .s span {
      font-weight: bold;
    }

    .p {
      color: #0aa1dd;
    }

    .p::after {
      content: 'Processing';
    }

    .r {
      color: #eb5353;
    }

    .r::after {
      content: 'Error';
    }

    .i {
      color: #112b3c;
    }

    .i::after {
      content: 'Idle';
    }

    .t {
      display: block;
      font-family: monospace;
      font-size: 14px;
      padding: 8px;
      width: 100%;
      margin-bottom: 8px;
      background: #eee;
    }

    .d {
      text-align: center;
    }

    .d button {
      padding: 8px 16px;
      border: 2px solid #333;
      transition: all 0.3s ease;
      position: relative;
      background: #333;
      color: #fff;
      z-index: 1;
      font-family: inherit;
      cursor: pointer;
    }

    .d button::after {
      position: absolute;
      content: '';
      width: 100%;
      height: 0;
      bottom: 0;
      left: 0;
      z-index: -1;
      background: #eee;
      transition: all 0.3s ease;
    }

    .d button:hover {
      color: #000;
    }

    .d button:hover::after {
      top: 0;
      height: 100%;
    }

    .d button:active {
      top: 2px;
    }

    .n {
      display: block;
      text-align: center;
      font-size: 12px;
      opacity: 0.8;
      margin-top: 8px;
    }

    .n span {
      font-weight: bold;
    }
  </style>
</head>

<body>
  <fieldset class="f">
    <legend class="l">Running at</legend>
    <table>
      <tr>
        <td class="b"><a href="https://github.com/Wanchester/sd-front-ec2">Front-end</a></td>
        <td class="h"><?php echo file_get_contents("/var/www/front_hash.txt") ?: "N/A"; ?></td>
      </tr>
      <tr>
        <td class="b"><a href="https://github.com/Wanchester/sd-back">Back-end</a></td>
        <td class="h"><?php echo file_get_contents("/var/www/back_hash.txt") ?: "N/A"; ?></td>
      </tr>
    </table>
  </fieldset>
  <fieldset class="f">
    <legend class="l">Deploy</legend>
    <div class="s">Status: <span class="i"></span></div>
    <textarea class="t" rows="15" readonly></textarea>
    <div class="d"><button>Deploy it!</button></div>
    <noscript>
      <div class="n"><span>Note:</span> Please enable JavaScript to deploy.</div>
    </noscript>
  </fieldset>

  <script>
    var button = document.getElementsByClassName('d')[0].children[0];
    var span = document.getElementsByClassName('i')[0];
    var textarea = document.getElementsByClassName('t')[0];
    var timeout;

    function load() {
      if (timeout != null) {
        clearTimeout(timeout);
      }

      fetch('/deploy?req=ping').then(function (value) {
        if (value.status !== 200) {
          throw new Error('Ping failed. Exited with code=' + value.status + '.');
        }
        return value.json();
      }).then(function (value) {
        switch (value.code) {
          case 0:
            span.className = 'i';
            break;
          case 1:
            span.className = 'p';
            break;
          default:
            span.className = 'r';
        }
        textarea.value = value.log;
      }).catch(function (reason) {
        span.className = 'r';
        textarea.value = reason.message;
      }).then(function () {
        timeout = setTimeout(load, 10000);
      });
    }
    load();

    function deploy() {
      fetch('/deploy?req=start').then(function (value) {
        return value.status !== 200 ?
          'Start failed. Exited with code=' + value.status + '.' :
          value.text();
      }).then(function (value) {
        alert(value);
        load();
      });
    }
    button.addEventListener('click', deploy);
  </script>
</body>

</html>
<?php
}
?>