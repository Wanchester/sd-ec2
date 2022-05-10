<?php
error_reporting(E_ERROR);

const DEPLOY_LOG = "/var/www/deploy-log";
const NODE_LOG = "/var/www/node-log";
const ENV = "/var/www/.env";

function is_building($safe = true) {
  exec("sudo pm2 ls | grep \"online\" | awk \"{print $4}\"", $output, $code);
  if ($code !== 0) {
    if ($safe) {
      return false;
    }
    throw new Exception("Cannot detect building state.");
  }
  return str_contains(implode("\n", $output), "ansible");
}

function parse_env($env) {
  $line = '/(?:^|^)\s*(?:export\s+)?([\w.-]+)(?:\s*=\s*?|:\s+?)(\s*\'(?:\\\'|[^\'])*\'|\s*"(?:\\"|[^"])*"|\s*`(?:\\`|[^`])*`|[^#\r\n]+)?\s*(?:#.*)?(?:$|$)/m';
  $num_matches = intval(preg_match_all($line, preg_replace("/\r\n?/m", "\n", strval($env)), $matches));
  $env = array();
  for ($i = 0; $i < $num_matches; ++$i) {
    $key = $matches[1][$i];
    $value = trim($matches[2][$i]);
    $first_char = $value[0] === '"';
    $value = preg_replace('/^([\'"`])([\s\S]*)\1$/m', "$2", $value);
    if ($first_char) {
      $value = preg_replace("/\\r/", "\r", preg_replace("/\\n/", "\n", $value));
    }
    $env[$key] = $value;
  }
  return $env;
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
    shell_exec("sudo pm2 delete ansible; sudo rm -f \"" . DEPLOY_LOG . "\"");
    exec(
      "sudo pm2 start \"/home/ubuntu/sd-ec2/scripts/playbook.sh\" --output \"" . DEPLOY_LOG . "\" --name ansible --no-autorestart 2>&1",
      $log,
      $started
    );
    echo $started === 0 ?
      "Started the build process. Refresh the page if the logs are not refreshing or to see new commit hash." :
      ("Could not start the build process.\n" . join("\n", $log));
  }
} elseif ($_GET["req"] === "updateVars") {
  header("Content-Type: application/json; charset=UTF-8", true);

  if (is_building()) {
    echo "Cannot update while a build process is running.";
  } else {
    if (!isset($_POST["env"]) || !is_array($_POST["env"])) {
      $_POST["env"] = array();
    }

    $env = "";
    foreach ($_POST["env"] as $key => $value) {
      if (!preg_match("/^[A-Z_][A-Z0-9_]*$/", $key)) {
        echo "Keys should consist only of uppercase letters, digits, and underscores, and NOT start with a digit (POSIX.1-2017).";
        return;
      }
      if (!preg_match("/^SD_SERVER_.?$/", $key)) {
        echo "Keys should always start with SD_SERVER_ and NOT be empty.";
        return;
      }
      $env .= "$key=\"" . addslashes(str_replace(array("\r\n", "\n", "\r"), "\\n", $value)) . "\"";
    }

    file_put_contents(ENV, $env);
    shell_exec("sudo rm -f \"" . NODE_LOG . "\"");
    exec(
      "eval $([ -r \"" . ENV . "\" ] && cat \"" . ENV . "\") sudo pm2 restart sd --update-env 2>&1",
      $log,
      $updated
    );
    echo $updated === 0 ?
      "Successfully restarted the server with new environment variables." :
      ("Could not restart the server.\n" . join("\n", $log));
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
      text-align: center;
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

    .f table tr {
      line-height: 1.5em;
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
      background: #eee;
    }

    .d {
      text-align: center;
      margin-top: 8px;
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

    small {
      font-size: 14px;
      opacity: 0.8;
      margin-top: 8px;
      display: inline-block;
    }

    #vars {
      width: 100%;
      border: 1px solid #000;
      table-layout: fixed;
    }

    #vars th, #vars td {
      border: 1px solid #000;
      padding: 4px;
    }

    #vars tr.head {
      background: #ddd;
    }

    #vars tr.empty td {
      text-align: center;
      font-size: 14px;
      opacity: 0.8;
    }

    #vars input {
      width: 100%;
    }
  </style>
</head>

<body>
  <fieldset class="f">
    <legend class="l">Running</legend>
    <table>
      <tr>
        <td class="b"><a href="https://github.com/Wanchester/sd-front-ec2">Front-end</a></td>
        <td class="h"><?php echo file_get_contents("/var/www/front_hash.txt") ?: "N/A"; ?></td>
      </tr>
      <tr>
        <td class="b"><a href="https://github.com/Wanchester/sd-back">Back-end</a></td>
        <td class="h"><?php echo file_get_contents("/var/www/back_hash.txt") ?: "N/A"; ?></td>
      </tr>
      <tr>
        <td class="b"><a href="https://github.com/Wanchester/sd-ec2">Portal</a></td>
        <td class="h"><?php echo file_get_contents("/var/www/portal_hash.txt") ?: "N/A"; ?></td>
      </tr>
    </table>
  </fieldset>
  <fieldset class="f">
    <legend class="l">Variables</legend>
    <button id="new-variable" style="margin-bottom: 8px;">Add new variable</button>
    <table id="vars">
      <tr class="head">
        <th style="width: 30%;">Key</th>
        <th style="width: 40%;">Value</th>
        <th style="width: 30%;">Action</th>
      </tr>
      <tr class="empty">
        <td colspan="3">No variables defined.</td>
      </tr>
    </table>
    <div class="d"><button id="update">Update!</button></div>
    <noscript>
      <div class="n"><span>Note:</span> Please enable JavaScript to update.</div>
    </noscript>
  </fieldset>
  <fieldset class="f">
    <legend class="l">Logs</legend>
    <textarea class="t" rows="15" readonly><?php echo shell_exec("tail --lines=50 \"" . NODE_LOG . "\""); ?></textarea>
    <small>Showing the last 50 lines. Refresh to see new logs.</small>
  </fieldset>
  <fieldset class="f">
    <legend class="l">Deploy</legend>
    <div class="s">Status: <span id="status" class="i"></span></div>
    <textarea id="deploy-logs" class="t" rows="15" readonly></textarea>
    <div class="d"><button id="deploy">Deploy!</button></div>
    <noscript>
      <div class="n"><span>Note:</span> Please enable JavaScript to deploy.</div>
    </noscript>
  </fieldset>

  <script src="https://code.jquery.com/jquery-3.6.0.slim.min.js" integrity="sha256-u7e5khyithlIdTpu22PHhENmPcRdFiHRjhAuHcs05RI=" crossorigin="anonymous"></script>
  <script>
    var tokenInput = $('#token');
    var urlInput = $('#url');
    var updateButton = $('#update');
    var deployButton = $('#deploy');
    var span = $('#status');
    var textarea = $('#deploy-logs');
    var tableElm = $('#vars');
    var timeout;

    function load() {
      if (timeout != null) {
        clearTimeout(timeout);
        timeout = null;
      }

      var refreshing = true;
      fetch('/deploy?req=ping').then(function (value) {
        if (value.status !== 200) {
          throw new Error('Ping failed. Exited with code=' + value.status + '.');
        }
        return value.json();
      }).then(function (value) {
        switch (value.code) {
          case 0:
            span.attr('class', 'i');
            refreshing = false;
            break;
          case 1:
            span.attr('class', 'p');
            break;
          default:
            span.attr('class', 'r');
        }
        textarea.val(value.log);
      }).catch(function (reason) {
        span.attr('class', 'r');
        textarea.val(reason.message);
      }).then(function () {
        textarea[0].scrollTop = textarea[0].scrollHeight;
        if (refreshing) {
          setTimeout(load, 5000);
        }
      });
    }
    load();

    function deploy() {
      fetch('/deploy?req=start').then(function (value) {
        return value.status !== 200 ?
          'Failed to start the deployment. Exited with code=' + value.status + '.' :
          value.text();
      }).then(function (value) {
        alert(value);
        load();
      });
    }
    deployButton.click(deploy);

    function update() {
      var env = {};

      tableElm.find('tr').not('.head').each(function () {
        var td = $(this).find('td');
        env[td.eq(0).find('input').val()] = td.eq(1).find('input').val();
      });

      fetch('/deploy?req=updateVars', {
        method: 'POST',
        cache: 'no-cache',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ env })
      }).then(function (value) {
        if (value.status !== 200) {
          throw new Error('Update failed. Exited with code=' + value.status + '.');
        }
        return value.text();
      }).then(function (value) {
        try {
          tableRefresh(JSON.parse(value));
        } catch (err) {
          alert(value);
        }
      }).catch(function (reason) {
        alert(reason.message);
      });
    }
    updateButton.click(update);

    var addButton = $('#new-variable');
    function table() {
      addButton.click(function () {
        var tr = $('<tr><td><input /></td><td><input /></td><td><button>Remove</button></td></tr>');
        tr.find('button').click(function () {
          if (confirm('Are you sure to delete this key:' + tr.find('td').eq(0).find('input').val() + '?')) {
            tr.remove();
            if (!tableElm.children('tr').not('.head, .empty').length) {
              tableElm.find('tr.empty').css('display', '');
            }
          }
        });
        tableElm.find('tr.empty').css('display', 'none');
        tableElm.append(tr);
      });
      addButton.insertBefore(tableElm);
    }
    table();

    function tableRefresh(data) {
      tableElm.children('tr').not('.head').remove();
      tableElm.find('tr.empty').css('display', '');

      for (var key in data) {
        addButton.trigger('click');
        var tr = tableElm.children().last();
        tr.find('td').eq(0).find('input').val(key);
        tr.find('td').eq(1).find('input').val(data[key]);
      }
    }
    tableRefresh(<?php echo json_encode(parse_env(file_get_contents(ENV))); ?>);
  </script>
</body>

</html>
<?php
}
?>