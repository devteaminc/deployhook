<?php
// webhook file called by a commit to github
// Create a stream
$opts = array(
  'http'=>array(
    'method'=>"GET",
    'user_agent'=>  "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1)" 
  )
);
$context = stream_context_create($opts);
$ghmeta = file_get_contents('https://api.github.com/meta', false, $context);
$meta = json_decode($ghmeta);

function ipCIDRCheck($ip, $cidr)
{
  list ($net, $mask) = explode('/', $cidr);
  $ipNet = ip2long($net);
  $ipMask = ~((1 << (32 - $mask)) - 1); 
  $ipIp = ip2long($ip);
  $ipIpNet = $ipIp & $ipMask;
  return ($ipIpNet == $ipNet);
}

function debug($message)
{
  error_log($message);
  echo $message.PHP_EOL;
}

$match = false;
foreach ($meta->hooks as $cidr) 
{
  if (ipCIDRCheck($_SERVER['REMOTE_ADDR'], $cidr)) 
  {   
    $match = true;
    break;
  }   
}

if (!$match || !isset($_POST['payload'])) 
{
  debug('no payload or matching webhook IP');
  die;
}

$payload = json_decode($_POST['payload']);

// Init vars
$LOCAL_ROOT         = "/var/www/devteaminc.co";
$LOCAL_REPO_NAME    = "public";
$LOCAL_REPO         = "{$LOCAL_ROOT}/{$LOCAL_REPO_NAME}";
$REMOTE_REPO        = "git@github.com:devteaminc/devteaminc.co.git";
$DESIRED_BRANCH     = "build";

if($payload->ref == "refs/heads/$DESIRED_BRANCH")
{
  debug('deploy started');

  // Delete local repo if it exists
  if (file_exists($LOCAL_REPO)) 
  {   
    shell_exec("rm -rf {$LOCAL_REPO}");
  }   

  // Clone fresh repo from github using desired local repo name and checkout the desired branch 
  echo shell_exec("cd {$LOCAL_ROOT} && git clone -b {$DESIRED_BRANCH} {$REMOTE_REPO} {$LOCAL_REPO_NAME}");

  debug('deploy complete ' . mktime());
}
else
{
  debug("$DESIRED_BRANCH not committed");
}